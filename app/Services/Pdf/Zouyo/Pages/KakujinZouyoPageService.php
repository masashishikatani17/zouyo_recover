<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;
use App\Models\FutureGiftRecipient;
use App\Models\FutureGiftPlanEntry;
use App\Models\ProposalFamilyMember;
use App\Models\PastGiftCalendarEntry;
use App\Models\PastGiftSettlementEntry;
use App\Models\ZouyoGeneralRate;
use App\Models\ZouyoTokureiRate;

use Illuminate\Support\Facades\Log;

/**
 * 5: 各人別贈与額および贈与税 ページ
 *
 * 仕様：
 *  - DB上で「これからの贈与」の入力がある受贈者ごとに 1 ページずつ出力
 *  - ページレイアウトは 05_pr_kakujin_zouyo.pdf を背景テンプレとして利用
 *  - 今の段階ではテンプレのみ（数値の印字は後続ステップで追加）
 */
class KakujinZouyoPageService implements ZouyoPdfPageInterface
{


    /** @var array<int,array<int,array{lower:int,upper:?int,rate:float,ded:int}>> */
    private array $giftRateCache = [
        0 => [], // general  year => rows
        1 => [], // tokurei  year => rows
    ];



    public function render(TCPDF $pdf, array $payload): void
    {

    /*
    // ログで呼び出しを確認
    \Log::debug('[KakujinZouyoPageService] render start', ['payload_data_id' => $payload['data_id'] ?? null]);
    */

    // ★画面と同じ data_id を必ず使う（固定しない）
    $dataId = (int)($payload['data_id'] ?? 0);


    // ------------------------------------------------------------
    // ★TCPDFに渡す値は「必ず文字列」に寄せる（0(int)を空扱いされるケース回避）
    //  - null は空文字
    //  - 数値は3桁カンマの文字列
    // ------------------------------------------------------------
    $fmtStr = static function ($v): string {
        if ($v === null) return '';
        return number_format((int)$v);
    };

    // ------------------------------------------------------------
    // ★DBの数値カラムが「カンマ入り/全角/空白/文字混在」でも 0 化させないためのサニタイズ
    // 例: "1,100" / "１１００" / " 1100 " / "1100千円" など → 1100
    // ------------------------------------------------------------
    $toInt = static function ($v): int {
        if ($v === null) return 0;
        if (is_int($v)) return $v;
        if (is_float($v)) return (int)$v;
        $s = (string)$v;
        // 全角数字→半角（ord はUTF-8で壊れるので mb_convert_kana を使う）
        $s = mb_convert_kana($s, 'n', 'UTF-8');
        // カンマ・空白除去
        $s = str_replace([',', ' ', '　'], '', $s);
        // 数字とマイナス以外除去（"1100千円" 等に対応）
        $s = preg_replace('/[^\d\-]/', '', $s) ?? '';
        if ($s === '' || $s === '-') return 0;
        return (int)$s;
    };




    if ($dataId <= 0) {
        //\Log::warning('[KakujinZouyoPageService] data_id missing in payload');
        return;
    }

    $wakusen = 0;

    // 精算課税側のプリセット（future_zouyo.blade の $prefillFuture 相当）
    $prefillFuture = (array)($payload['prefillFuture'] ?? []);



    // ------------------------------------------------------------
    // ★税率表は年で変わらない前提：DBにある最新 kih u_year を使用
    //    （Seederを固定年で入れてもOK）
    // ------------------------------------------------------------
    $rateYearGeneral = (int)(ZouyoGeneralRate::query()->whereNull('company_id')->max('kihu_year') ?: 2026);
    $rateYearTokurei = (int)(ZouyoTokureiRate::query()->whereNull('company_id')->max('kihu_year') ?: 2026);


    // ◆ family テーブル（proposal_family_members）から氏名を取得
    $familyRows = ProposalFamilyMember::query()
        ->where('data_id', $dataId)
        ->orderBy('row_no')
        ->get()
        ->keyBy('row_no');

    // Blade の $donorName と同じ優先順位に揃える：
    $header     = $payload['header']  ?? [];
    $donorName  = (string)(
        ($familyRows[1]->name ?? null)
        ?? ($header['customer_name'] ?? '')
    );

    // 対象受贈者（将来贈与タブで使っている recipient_no 2..10）
    $recipients = FutureGiftRecipient::query()
        ->where('data_id', $dataId)
        ->orderBy('recipient_no')
        ->get();

    if ($recipients->isEmpty()) {
        //\Log::info('[KakujinZouyoPageService] no FutureGiftRecipient found', ['data_id' => $dataId]);
        return;
    }


    // 「入力内容がある受贈者」だけを抽出 → 全員を印字するように変更
    $targets = [];
    foreach ($recipients as $r) {
        $no = (int)$r->recipient_no;
        if ($no < 2 || $no > 10) {
            continue;
        }
    
        // 受贈者名は画面入力（familyRows）を唯一の基準にする
        // 仕様：受贈者の氏名が空欄のときはページを追加しない
        $recipientName = trim((string)($familyRows[$no]->name ?? ''));
        if ($recipientName === '') {
            continue;
        }

        $targets[] = [
            'recipient_no'   => $no,
            'recipient_name' => $recipientName,
        ];


    }
    
    // 受贈者のページ描画
    foreach ($targets as $info) {
        // ここで recipient_no を取り出す
        $recipientNo   = $info['recipient_no'];
        $recipientName = trim((string)($info['recipient_name'] ?? ''));
        // ProposalFamilyMember の tokurei_zouyo（0/1）を取得（なければ 0＝一般税率）
        $tokureiFlag   = (int)($familyRows[$recipientNo]->tokurei_zouyo ?? 0);


        // 仕様：受贈者の氏名が空欄のときはページを追加しない
        if ($recipientName === '') {
            continue;
        }

        /*
        \Log::info('[KakujinZouyoPageService] 2025.12.02 0001 ', [
            'recipient_no'  => $recipientNo,
            'recipientName' => $recipientName,
            'tokureiFlag'   => $tokureiFlag,
        ]);
        */
        
    
        // 過年度分（暦年贈与）の印字処理を行う前に PastGiftCalendarEntry のデータを取得
        $pastRows = PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)  // ここで $recipientNo を使う
            ->whereNotNull('gift_year')
            ->orderBy('gift_year')
            ->get(['gift_year', 'amount_thousand', 'tax_thousand']);
        
        // 過年度データが無い場合でも0を印字
        $zoyoByYear = [];
        $kojoByYear = [];
        foreach ($pastRows as $row) {
            $y = (int) $row->gift_year;
            $z = (int) ($row->amount_thousand ?? 0);
            $k = (int) ($row->tax_thousand    ?? 0);
            if ($y <= 0 || ($z === 0 && $k === 0)) {
                continue;
            }
            $zoyoByYear[$y] = ($zoyoByYear[$y] ?? 0) + $z;
            $kojoByYear[$y] = ($kojoByYear[$y] ?? 0) + $k;
        }
    
        // ここから続きの処理（年別データの印字など）
    }

    if (empty($targets)) {
        //\Log::info('[KakujinZouyoPageService] no recipients with future plan rows', ['data_id' => $dataId]);
        return;
    }

    // テンプレートPDFのパス
    $templatePath = resource_path('/views/pdf/05_pr_kakuzoyo.pdf');

    // テンプレートの存在確認
    if (!file_exists($templatePath)) {
        throw new \RuntimeException("KakujinZouyo template not found: {$templatePath}");
    }

    // テンプレ 1回読み込み → 1ページ目をインポート
    $pdf->setSourceFile($templatePath);
    $tplId = $pdf->importPage(1);

    // 各受贈者の生年月日（年齢計算用）を取得
    $birthByRow = [];
    foreach ($familyRows as $rowNo => $row) {
        $birthByRow[$rowNo] = [
            'year'  => $row->birth_year  !== null ? (int) $row->birth_year  : null,
            'month' => $row->birth_month !== null ? (int) $row->birth_month : null,
            'day'   => $row->birth_day   !== null ? (int) $row->birth_day   : null,
        ];
    }
    
    $pageNo = 0;

    // 各受贈者のページを描画
    foreach ($targets as $info) {
        $recipientNo   = $info['recipient_no'];
        $recipientName = (string)($info['recipient_name'] ?? '');
        $tokureiFlag   = (int)($familyRows[$recipientNo]->tokurei_zouyo ?? 0);
    
        // 1受贈者ごとに 1 ページ追加
        $pdf->AddPage(); // 必ずページを追加する
        $pdf->useTemplate($tplId);
    

        $pageNo = $pageNo + 1;
        $pdf->SetFont('mspgothic03', '',10);
        $wakusen = 0;
        $x = 255;
        $y = 200;
        $pdf->MultiCell(30, 5, '(5 - ' . $pageNo . 'ページ)', $wakusen, 'R', 0, 0, $x, $y);


        $pdf->SetFont('mspgothic03', '', 10);
        $pdf->SetTextColor(0, 0, 0);
    
        // ヘッダ部：贈与者 / 受贈者
        $x = 60;
        if ($donorName !== '') {
            $y = 22.5;
            $pdf->MultiCell(40, 6, $donorName, $wakusen, 'L', 0, 0, $x, $y);
        }
    
        $label = $recipientName !== '' ? $recipientName : ('受贈者 ' . $recipientNo);
        $y     = 29.0;
        $pdf->MultiCell(40, 6, $label, $wakusen, 'L', 0, 0, $x, $y);
    

        // 税率種類選択ラベル：tokurei_zouyo = 1 なら「(特例税率)」、それ以外は「(一般税率)」
        $tokureiLabel = $tokureiFlag === 1 ? '(特例税率)' : '(一般税率)';
        $pdf->SetFont('mspmincho02', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $x   = 114.0;
        $y   =  49.5;
        $pdf->MultiCell(20, 6, $tokureiLabel, $wakusen, 'C', 0, 0, $x, $y);
        $pdf->SetFont('mspgothic03', '', 10);

        /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // ▼ 過年度分（暦年贈与）の印字
        //
        //  - PastGiftCalendarEntry から受贈者ごとに年別集計
        //  - 表示する3年は固定 2022/2023/2024 ではなく、
        //    「相続開始年の直前3年」= deathYear-3, deathYear-2, deathYear-1
        //    例: 相続開始年が 2026 年なら 2023 / 2024 / 2025
        //  - それより前は 1 行にまとめて「過年度合計」として表示
        /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $deathYear = $this->resolveDisplayDeathYear($payload, $dataId);
        $pastDisplayYears = [
            $deathYear - 3,
            $deathYear - 2,
            $deathYear - 1,
        ];

        $pastAggregateBeforeYear = $pastDisplayYears[0];
        // 過年度分（暦年贈与）の印字処理を行う前に PastGiftCalendarEntry のデータを取得
        $pastRows = PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->whereNotNull('gift_year')
            ->orderBy('gift_year')
            ->get(['gift_year', 'amount_thousand', 'tax_thousand']);
        
        // 初期値をゼロとして設定（年別：贈与額／基礎控除／贈与税額）
        $zoyoByYear = []; // 暦年：贈与額（千円）
        $kojoByYear = []; // 暦年：基礎控除累計（千円）
        $taxByYear  = []; // 暦年：贈与税額（千円）
        foreach ($pastRows as $row) {
            $y = $toInt($row->gift_year);
            $z = $toInt($row->amount_thousand);
            $k = $toInt($row->tax_thousand);
            if ($y <= 0 || ($z === 0 && $k === 0)) {
                continue;
            }
            $zoyoByYear[$y] = ($zoyoByYear[$y] ?? 0) + $z;
            // 年ごとの「基礎控除適用額」：贈与額が 1,100 千円未満ならその額、以上なら 1,100
            $kojoByYear[$y] = ($kojoByYear[$y] ?? 0) + min($z, 1100);
            // 贈与税額は単純に年別に加算
            $taxByYear[$y]  = ($taxByYear[$y]  ?? 0) + $k;
        }



        ////////////////////////////////////////////////////////////////////////
        // ▼ 過年度分（精算課税）の印字用データ
        //
        // PastGiftSettlementEntry から年別に
        //   - 贈与額（amount_thousand）
        //   - 贈与税額（tax_thousand）
        // を集計し、
        //   〜2021年 … 過年度合計
        //   2022〜2024年 … 各年別
        // として表示する（データが無い場合も 0 を印字）。
        ////////////////////////////////////////////////////////////////////////
        $seisanZoyoByYear = []; // 精算課税：贈与額（千円）
        $seisanTaxByYear  = []; // 精算課税：贈与税額（千円）
        $seisanRows = PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->whereNotNull('gift_year')
            ->orderBy('gift_year')
            ->get(['gift_year', 'amount_thousand', 'tax_thousand']);
        foreach ($seisanRows as $row) {
            $y = $toInt($row->gift_year);
            $z = $toInt($row->amount_thousand);
            $k = $toInt($row->tax_thousand);
            if ($y <= 0 || ($z === 0 && $k === 0)) {
                continue;
            }
            $seisanZoyoByYear[$y] = ($seisanZoyoByYear[$y] ?? 0) + $z;
            $seisanTaxByYear[$y]  = ($seisanTaxByYear[$y]  ?? 0) + $k;
        }


            $colX = [
                'index'         => 15.0,  // 回数
                'year'          => 30.0,  // 贈与年
                'age'           => 42.0,  // 年齢

                'cal_amount'    => 55.0,
                'cal_basic'     => 75.0,
                'cal_after'     => 95.0,
                'cal_tax'       => 118.0,
                'cal_cum'       => 137.0,

                'set_amount'    => 159.0,
                'set_basic110'  => 180.0,
                'set_after'     => 200.0,
                'set_after_25m' => 220.0,
                'set_tax20'     => 240.0,
                'set_cum'       => 262.0,
            ];
    

        // 表示対象3年より前を「過年度合計」としてまとめる
        $before2022Z    = $before2022K    = $before2022T    = 0; // 暦年
        $before2022SetZ = $before2022SetK = 0;                   // 精算課税
        foreach ($zoyoByYear as $year => $z) {
            if ($year < $pastAggregateBeforeYear) {
                $before2022Z += $z;
                $before2022K += ($kojoByYear[$year] ?? 0);
                $before2022T += ($taxByYear[$year] ?? 0);
            }
        }
        foreach ($seisanZoyoByYear as $year => $z) {
            if ($year < $pastAggregateBeforeYear) {
                $before2022SetZ += $z;
                $before2022SetK += ($seisanTaxByYear[$year] ?? 0);
            }
        }

        // 表示対象3年より前の合計行を表示
        $yBase = 58.0;      // 表示開始 Y 座標（テンプレに合わせて微調整）
        $lineH = 4.5;       // 行高さ
        $rowIdx = 0;

        // まず 〜2021年の合計行（「データが無い場合も 0 を印字」するため、常に 1 行出力）
        // 「データが無い場合も 0 を印字」するため、常に 1 行出力する
        $x = $colX['cal_amount'];
        $y = $yBase + $lineH * $rowIdx;
        $pdf->MultiCell(15, 6, number_format($before2022Z), $wakusen, 'R', 0, 0, $x, $y);

        $x = $colX['cal_basic'];
        $pdf->MultiCell(15, 6, number_format(-$before2022K), $wakusen, 'R', 0, 0, $x, $y);

        $x = $colX['cal_after'];
        $pdf->MultiCell(15, 6, number_format($before2022Z - $before2022K), $wakusen, 'R', 0, 0, $x, $y);

        $x = $colX['cal_tax'];
        $pdf->MultiCell(15, 6, number_format($before2022T), $wakusen, 'R', 0, 0, $x, $y);

        $x = $colX['cal_cum'];
        $pdf->MultiCell(15, 6, number_format(0), $wakusen, 'R', 0, 0, $x, $y);



        // ▼ 精算課税（過年度合計：〜2021年）
        $fmt = static fn($v) => $v === null ? '' : number_format((int)$v);

        // 贈与額
        $x = $colX['set_amount'];
        $pdf->MultiCell(15, 6, $fmt($before2022SetZ), $wakusen, 'R', 0, 0, $x, $y);
        // 基礎控除（精算課税は 2,500 万控除が別枠なのでここは 0）
        $x = $colX['set_basic110'];
        $pdf->MultiCell(15, 6, $fmt(0), $wakusen, 'R', 0, 0, $x, $y);
        // 基礎控除後
        $x = $colX['set_after'];
        $pdf->MultiCell(15, 6, $fmt($before2022SetZ), $wakusen, 'R', 0, 0, $x, $y);
        // 2,500 万控除後
        $x = $colX['set_after_25m'];
        $pdf->MultiCell(15, 6, $fmt(max(0, $before2022SetZ - 25000)), $wakusen, 'R', 0, 0, $x, $y);
        // 贈与税額（20%）
        $x = $colX['set_tax20'];
        $pdf->MultiCell(15, 6, $fmt($before2022SetK), $wakusen, 'R', 0, 0, $x, $y);
        // 贈与加算累計額
        $x = $colX['set_cum'];
        $pdf->MultiCell(15, 6, $fmt(0), $wakusen, 'R', 0, 0, $x, $y);


        $rowIdx += 2;

        // 2022–2024 のみ独立行として表示
        // （該当年にデータが無い場合も 0 を印字する）
        $yBase = 62.5;      // 表示開始 Y 座標（テンプレに合わせて微調整）
        $lineH = 4.5;       // 行高さ
        $rowIdx = 0;



        // ★合計行の暦年贈与税は「明細行で実際に表示した税額」をそのまま積み上げる
        //   - 明細行と合計行で別ロジックにしない
        //   - floor((float)...) ではなく、明細と同じ $toInt() / calcGiftTaxKyen() の結果を使う
        $sumCalTax = 0;



        // 次に「相続開始年の直前3年」を年別に出力
        foreach ($pastDisplayYears as $year) {
            $zYear = $zoyoByYear[$year] ?? 0;
            $basicYear = $kojoByYear[$year] ?? 0;
            $kYear     = $taxByYear[$year] ?? 0;
            $y = $yBase + $lineH * $rowIdx;

            // データが無い年も「0」を印字したいので、
            // 贈与額・税額がすべて 0 の場合は基礎控除も 0 とする
            if ($zYear === 0 && $basicYear === 0 && $kYear === 0) {
                $basicKForYear     = 0;
                $afterBasicForYear = 0;
            } else {
                // データがある場合は 110 万円控除を適用
                $basicKForYear     = $basicYear;                
                $afterBasicForYear = max($zYear - $basicKForYear, 0);
            }

                // 贈与年（gift_year）
                //if ($year>0) {
                    $x = $colX['year'];
                    $pdf->MultiCell(10, 6, $year, $wakusen, 'R', 0, 0, $x, $y);
                //}

                // 年齢（基準年＝贈与年の1月1日時点）
                $baseYear = $year;
                $birth    = $birthByRow[$recipientNo] ?? ['year' => null, 'month' => null, 'day' => null];
                $age      = $this->calcAgeAtJan1($birth['year'], $birth['month'], $birth['day'], $baseYear);
        
                //if ($age !== null) {
                    $x = $colX['age'];
                    $pdf->MultiCell(10, 6, (string)$age . '歳', $wakusen, 'R', 0, 0, $x, $y);
                //}

                // 暦年：贈与額ほか（千円表示）
                // ★ TCPDF に 0(int) を渡さないため、必ず string を返す
                $fmt = $fmtStr;

                //贈与額
                $x = $colX['cal_amount'];
                $pdf->MultiCell(15, 6, $fmt($zYear), $wakusen, 'R', 0, 0, $x, $y);
        
                //基礎控除
                $x = $colX['cal_basic'];
                $pdf->MultiCell(15, 6, $fmt(-$basicKForYear), $wakusen, 'R', 0, 0, $x, $y);
        
                //基礎控除後
                $x = $colX['cal_after'];
                $pdf->MultiCell(15, 6, $fmt($afterBasicForYear), $wakusen, 'R', 0, 0, $x, $y);
        
                //贈与税額
                $x = $colX['cal_tax'];
                $pdf->MultiCell(15, 6, $fmt($kYear), $wakusen, 'R', 0, 0, $x, $y);
        
                //贈与加算累計額
                $x = $colX['cal_cum'];
                $pdf->MultiCell(15, 6, $fmt(0), $wakusen, 'R', 0, 0, $x, $y);
        
            // ▼ 精算課税：贈与額ほか（千円表示）
                $setZYear = $seisanZoyoByYear[$year] ?? 0;
                $setKYear = $seisanTaxByYear[$year] ?? 0;

                // 贈与額
                $x = $colX['set_amount'];
                $pdf->MultiCell(15, 6, $fmt($setZYear), $wakusen, 'R', 0, 0, $x, $y);
                // 基礎控除
                $x = $colX['set_basic110'];
                $pdf->MultiCell(15, 6, $fmt(0), $wakusen, 'R', 0, 0, $x, $y);
                // 基礎控除後
                $x = $colX['set_after'];
                $pdf->MultiCell(15, 6, $fmt($setZYear), $wakusen, 'R', 0, 0, $x, $y);
                // 2,500 万控除後
                $x = $colX['set_after_25m'];
                $pdf->MultiCell(15, 6, $fmt(max(0, $setZYear - 25000)), $wakusen, 'R', 0, 0, $x, $y);
                // 贈与税額（20%）
                $x = $colX['set_tax20'];
                $pdf->MultiCell(15, 6, $fmt($setKYear), $wakusen, 'R', 0, 0, $x, $y);
                // 贈与加算累計額
                $x = $colX['set_cum'];
                $pdf->MultiCell(15, 6, $fmt(0), $wakusen, 'R', 0, 0, $x, $y);


            // ★明細行で表示した税額を、そのまま合計へ加算
            $sumCalTax += $kYear;


            $rowIdx += 1;


        }
        
    


        // 受贈者の詳細データ（贈与額など）を追加
        $plans = FutureGiftPlanEntry::query()        
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->orderBy('row_no')
            ->get();

        // row_no ごとに引けるようにして、
        // DB行が存在しない年も 1〜20 行を固定表示できるようにする
        $plansByRowNo = $plans->keyBy(function ($row) {
            return (int) $row->row_no;
        });

    
        // ▼ 表形式で印字（これからの贈与：future_zouyo.blade の明細相当）
        $rowStartY = 75.8;
        $rowHeight = 4.5;



        // 受贈者別：過去の精算課税贈与額の累計（千円）を初期値にする
        // ※「贈与加算累計額」を“過去＋未来の累計”として出す前提（Blade側と合わせやすい）
        $runningSetCum = (int) PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->sum('amount_thousand');
    


        for ($i = 1; $i <= 20; $i++) {
            /** @var \App\Models\FutureGiftPlanEntry|null $plan */
            $plan = $plansByRowNo->get($i);

    
            $y = $rowStartY + $rowHeight * ($i - 1);
    
            // 回数
            //$pdf->SetXY($colX['index'], $y);
            //$pdf->MultiCell(15, 6, (string)$i, $wakusen, 'R', 0, 0, $x, $y);
            
            // その他の項目（贈与年、年齢など）もここに追加します
            // ...


            // gift_year が null の場合でも「開始年＋行番号」で補完して 2044 年まで必ず印字する
            // 例：i=1 → 2025, i=20 → 2044
            $giftYear = $plan && $plan->gift_year ? (int)$plan->gift_year : (2024 + $i);            
            $x = $colX['year'];
            $pdf->MultiCell(10, 6, (string)$giftYear, $wakusen, 'R', 0, 0, $x, $y);
    
    
            // 年齢（基準年＝贈与年の1月1日時点）
            $baseYear = $giftYear;            
            $birth    = $birthByRow[$recipientNo] ?? ['year' => null, 'month' => null, 'day' => null];
            $age      = $this->calcAgeAtJan1($birth['year'], $birth['month'], $birth['day'], $baseYear);
    
            if ($age !== null) {
                $x = $colX['age'];
                $pdf->MultiCell(10, 6, (string)$age . '歳', $wakusen, 'R', 0, 0, $x, $y);
            }
    
            // 暦年：贈与額ほか（千円表示）
            // ★必ず string を返す
            $fmt = $fmtStr;



            // ------------------------------------------------------------
            // ★2025〜2044（未来行）は、この foreach 内で $plan から必ず数値化→再計算する
            //   after = amount - min(amount, basic)
            // ------------------------------------------------------------

            $calAmountK  = $toInt($plan ? $plan->calendar_amount_thousand : null);
            $calBasicInK = $toInt($plan ? $plan->calendar_basic_deduction_thousand : null);

            $calBasicK   = (int)min($calAmountK, $calBasicInK);
            $calAfterK   = max(0, $calAmountK - $calBasicK);

            // ------------------------------------------------------------
            // ★暦年贈与の贈与税額（千円）は DB値を信用せず、税率表から再計算する
            //   （画面はJS計算で表示しており、DBに保存されていないケースがあるため）
            // ------------------------------------------------------------
            $isTokurei = ($tokureiFlag === 1);
            $rateYear  = $isTokurei ? $rateYearTokurei : $rateYearGeneral;
            $calTaxK   = $this->calcGiftTaxKyen($calAfterK, $isTokurei, $rateYear);


            // ★明細行で表示した税額を、そのまま合計へ加算
            $sumCalTax += $calTaxK;


            // ★暦年課税の贈与加算累計額は、DB未保存(NULL)でも
            //   画面プリセット(prefillFuture)に載っていればそちらを使って印字する。
            //   これを入れないと、DBはNULL・画面では表示あり、というケースでPDFだけ空欄になる。
            $calCumRaw = $plan ? $plan->calendar_add_cum_thousand : null;            
            if ($calCumRaw === null || $calCumRaw === '') {
                $calCumRaw = $this->getPrefillFutureValue(
                    $prefillFuture,
                    $recipientNo,
                    $i,
                    ['calendar_add_cum_thousand', 'calendar_add_cum', 'add_cum_thousand']
                );
            }
            $calCumK = $toInt($calCumRaw);


            $setAmountK  = $toInt($plan ? $plan->settlement_amount_thousand : null);
            $setBasicInK = $toInt($plan ? $plan->settlement_110k_basic_thousand : null);            
            $setBasicK   = (int)min($setAmountK, $setBasicInK);
            $setAfterK   = $toInt($plan ? $plan->settlement_after_basic_thousand : null);
            $setAfter25K = $toInt($plan ? $plan->settlement_after_25m_thousand : null);
            $setTax20K   = $toInt($plan ? $plan->settlement_tax20_thousand : null);


            //贈与額
            $x = $colX['cal_amount'];
            $pdf->MultiCell(15, 6, $fmt($calAmountK), $wakusen, 'R', 0, 0, $x, $y);
    
            //基礎控除
            $x = $colX['cal_basic'];
            $pdf->MultiCell(15, 6, $fmt(-$calBasicK), $wakusen, 'R', 0, 0, $x, $y);

            //基礎控除後
            $x = $colX['cal_after'];
            $pdf->MultiCell(15, 6, $fmt($calAfterK), $wakusen, 'R', 0, 0, $x, $y);

            //贈与税額
            $x = $colX['cal_tax'];
            $pdf->MultiCell(15, 6, $fmt($calTaxK), $wakusen, 'R', 0, 0, $x, $y);
    
            //贈与加算累計額
            $x = $colX['cal_cum'];
            $pdf->MultiCell(15, 6, $fmt($calCumK), $wakusen, 'R', 0, 0, $x, $y);
    
            // 精算課税：贈与額ほか
            // 贈与額
            $x = $colX['set_amount'];
            $pdf->MultiCell(15, 6, $fmt($setAmountK), $wakusen, 'R', 0, 0, $x, $y);
    
            $x = $colX['set_basic110'];
            $pdf->MultiCell(15, 6, $fmt(-$setBasicK), $wakusen, 'R', 0, 0, $x, $y);
    
            $x = $colX['set_after'];
            $pdf->MultiCell(15, 6, $fmt($setAfterK), $wakusen, 'R', 0, 0, $x, $y);

            $x = $colX['set_after_25m'];
            $pdf->MultiCell(15, 6, $fmt($setAfter25K), $wakusen, 'R', 0, 0, $x, $y);

            $x = $colX['set_tax20'];
            $pdf->MultiCell(15, 6, $fmt($setTax20K), $wakusen, 'R', 0, 0, $x, $y);

            $x = $colX['set_cum'];

            // ★ここが本題：
            // DBの settlement_add_cum_thousand が NULL の場合は、PDF側で累計を自前計算して印字する
            $setCumFromDb = $plan ? $plan->settlement_add_cum_thousand : null;            
            if ($setCumFromDb === null) {
                // 精算課税の累計は「贈与額（amount_thousand）の累計」として計算（千円）
                $runningSetCum += $setAmountK;
                $pdf->MultiCell(15, 6, number_format($runningSetCum), $wakusen, 'R', 0, 0, $x, $y);
            } else {
                // DBに入っている場合はそれを優先
                $pdf->MultiCell(15, 6, number_format((int)$setCumFromDb), $wakusen, 'R', 0, 0, $x, $y);
                // running も同期（後続行の整合のため）
                $runningSetCum = (int)$setCumFromDb;
            }

        }
    
        /*
        \Log::info('[KakujinZouyoPageService] rendered page for recipient', [
            'data_id'       => $dataId,
            'recipient_no'  => $recipientNo,
            'recipientName' => $recipientName,
            'plan_rows'     => $plans->count(),
        ]);
        */
        

        ////////////////////////////////////////////////////////////////////
        // ▼ 未来贈与（1〜20行）の合計行を表示
        ////////////////////////////////////////////////////////////////////

        // 20行目の次の行に表示する
        $sumY = $rowStartY + $rowHeight * 20 + 0.5;

        // 合計値の算出
        // ▼ 端数切捨てしてから合計する（千円単位の floor）
        $sumCalAmount = $plans->sum(fn($p) => floor((float)$p->calendar_amount_thousand));
        $sumCalBasic  = $plans->sum(fn($p) => -min(
            floor((float)$p->calendar_amount_thousand),
            floor((float)$p->calendar_basic_deduction_thousand)
        ));

        // ★基礎控除後も必ず再計算（DB列を信用しない）
        $sumCalAfter  = $plans->sum(function ($p) {
            $amt  = (int)floor((float)($p->calendar_amount_thousand ?? 0));
            $base = (int)floor((float)($p->calendar_basic_deduction_thousand ?? 0));
            $ded  = min($amt, $base);
            return max(0, $amt - $ded);
        });

        // ★sumCalTax は上の明細 foreach 内で、表示した税額($calTaxK)をそのまま積み上げ済み
        //   ここで再計算しないことで、明細行と合計行のズレを防ぐ


        // ★合計行も同様に、DBの calendar_add_cum_thousand が NULL の場合は
        //   prefillFuture 側の値を拾って合算する。
        $sumCalCum = 0;
        foreach ($plans as $p) {
            $rowNo = (int)($p->row_no ?? 0);
            $raw   = $p->calendar_add_cum_thousand;
            if ($raw === null || $raw === '') {
                $raw = $this->getPrefillFutureValue(
                    $prefillFuture,
                    $recipientNo,
                    $rowNo,
                    ['calendar_add_cum_thousand', 'calendar_add_cum', 'add_cum_thousand']
                );
            }
            $sumCalCum += $toInt($raw);
        }



        $sumSetAmount = $plans->sum(fn($p) => floor((float)$p->settlement_amount_thousand));
        $sumSetBasic  = $plans->sum(fn($p) => -min(
            floor((float)$p->settlement_amount_thousand),
            floor((float)$p->settlement_110k_basic_thousand)
        ));
        $sumSetAfter   = $plans->sum(fn($p) => floor((float)$p->settlement_after_basic_thousand));
        $sumSetAfter25 = $plans->sum(fn($p) => floor((float)$p->settlement_after_25m_thousand));

        // ★修正：PDFは「受贈者ごとのページ」であるため、
        //   過年度の精算課税税額は「その受贈者の tax_thousand のみ」を合計する。

        $sumSetTax20 = (int) PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->sum('tax_thousand');   // ← 受贈者別に正しく集計できる



        // ② これからの精算課税（FutureGiftPlanEntry）
        //    settlement_tax20_thousand（千円単位）
        $futureSetTaxK = (int) FutureGiftPlanEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->sum('settlement_tax20_thousand');  // 千円



// ★デバッグ用：
//   合計値(sum)ではなく、過年度・未来の各行の税額を1件ずつログに出す
//   どの行が +1 千円を作っているか切り分けるための一時ログ

// ① 過年度の精算課税（PastGiftSettlementEntry）
$pastSetTaxRows = PastGiftSettlementEntry::query()
    ->where('data_id', $dataId)
    ->where('recipient_no', $recipientNo)
    ->orderBy('gift_year')
    ->orderBy('id')
    ->get(['id', 'gift_year', 'amount_thousand', 'tax_thousand']);

/*
foreach ($pastSetTaxRows as $row) {
    Log::debug('[KakujinZouyoPageService][sumSetTax20][past_row]', [
        'data_id'         => $dataId,
        'recipient_no'    => $recipientNo,
        'id'              => $row->id,
        'gift_year'       => $row->gift_year,
        'amount_thousand' => $row->amount_thousand,
        'tax_thousand'    => $row->tax_thousand,
    ]);
}
*/

$sumSetTax20 = (int) $pastSetTaxRows->sum(function ($row) use ($toInt) {
    return $toInt($row->tax_thousand);
});

// ② これからの精算課税（FutureGiftPlanEntry）
$futureSetTaxRows = FutureGiftPlanEntry::query()
    ->where('data_id', $dataId)
    ->where('recipient_no', $recipientNo)
    ->orderBy('row_no')
    ->orderBy('id')
    ->get(['id', 'row_no', 'gift_year', 'settlement_tax20_thousand']);

/*
foreach ($futureSetTaxRows as $row) {
    Log::debug('[KakujinZouyoPageService][sumSetTax20][future_row]', [
        'data_id'                    => $dataId,
        'recipient_no'               => $recipientNo,
        'id'                         => $row->id,
        'row_no'                     => $row->row_no,
        'gift_year'                  => $row->gift_year,
        'settlement_tax20_thousand'  => $row->settlement_tax20_thousand,
    ]);
}
*/

$futureSetTaxK = (int) $futureSetTaxRows->sum(function ($row) use ($toInt) {
    return $toInt($row->settlement_tax20_thousand);
});



        // ③ 過去 + 未来 の千円合計
        $sumSetTax20 = (int)$futureSetTaxK;



        $sumSetCum   = $plans->sum(fn($p) => floor((float)$p->settlement_add_cum_thousand));


        // 印字処理（暦年）
        $pdf->MultiCell(15, 6, number_format($sumCalAmount), 0, 'R', 0, 0, $colX['cal_amount'], $sumY);
        //$pdf->MultiCell(15, 6, number_format($sumCalBasic),  0, 'R', 0, 0, $colX['cal_basic'],  $sumY);
        //$pdf->MultiCell(15, 6, number_format($sumCalAfter),  0, 'R', 0, 0, $colX['cal_after'],  $sumY);
        $pdf->MultiCell(15, 6, number_format($sumCalTax),    0, 'R', 0, 0, $colX['cal_tax'],    $sumY);
        //$pdf->MultiCell(15, 6, number_format($sumCalCum),    0, 'R', 0, 0, $colX['cal_cum'],    $sumY);

        // 印字処理（精算課税）
        $pdf->MultiCell(15, 6, number_format($sumSetAmount),   0, 'R', 0, 0, $colX['set_amount'],    $sumY);
        //$pdf->MultiCell(15, 6, number_format($sumSetBasic),    0, 'R', 0, 0, $colX['set_basic110'],  $sumY);
        //$pdf->MultiCell(15, 6, number_format($sumSetAfter),    0, 'R', 0, 0, $colX['set_after'],     $sumY);
        //$pdf->MultiCell(15, 6, number_format($sumSetAfter25),  0, 'R', 0, 0, $colX['set_after_25m'], $sumY);
        // ★ 精算課税分の贈与税合計は、
        //    PastGiftSettlementEntry::tax_thousand の合計のみを表示する
        //    （これからの贈与分は行明細で見る。合計行では「過年度分の入力値」を優先）
        $pdf->MultiCell(15, 6, number_format($sumSetTax20),    0, 'R', 0, 0, $colX['set_tax20'],     $sumY);
       //$pdf->MultiCell(15, 6, number_format($sumSetCum),      0, 'R', 0, 0, $colX['set_cum'],       $sumY);

        
    }   
    
}


    
    /**
     * 年齢を「基準年の1月1日時点の満年齢」で計算する。
     *
     * Blade / JS 側の calcAgeAsOfJan1(by,bm,bd,baseYear) と同じロジック：
     *   base = baseYear年1月1日
     *   age = baseYear - birthYear
     *   誕生日(当年) が 1/1 より後なら age-1
     *   0〜130歳の範囲内だけ有効、それ以外は null
    */
    private function calcAgeAtJan1(?int $birthYear, ?int $birthMonth, ?int $birthDay, ?int $baseYear): ?int
    {
        if (!$birthYear || !$birthMonth || !$birthDay || !$baseYear) {
            return null;
        }

        try {
            // 基準日: baseYear-01-01
            $base = new \DateTimeImmutable(sprintf('%04d-01-01', $baseYear));
            $by   = max(1, $birthYear);
            $bm   = max(1, min(12, $birthMonth));
            $bd   = max(1, min(31, $birthDay));
            $birth = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $by, $bm, $bd));

            $age = $baseYear - $by;

            // その年の誕生日を作り、1月1日より後ならまだ誕生日が来ていないので 1 減らす
            $birthdayThisYear = $birth->setDate($baseYear, (int)$birth->format('m'), (int)$birth->format('d'));
            if ($birthdayThisYear > $base) {
                $age--;
            }

            if ($age < 0 || $age > 130) {
                return null;
            }
            return $age;
        } catch (\Throwable $e) {
            return null;
        }
    }


    /**
     * 過年度表示用の相続開始年を解決する。
     *
     * 優先順位:
     *  1) payload.header_year
     *  2) payload.header['year']
     *  3) ProposalHeader.proposal_year
     *  4) ProposalHeader.proposal_date の年
     *  5) 当年
     */
    private function resolveDisplayDeathYear(array $payload, int $dataId): int
    {
        $candidates = [
            $payload['header_year'] ?? null,
            $payload['header']['year'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $year = (int)preg_replace('/[^\d]/', '', (string)$candidate);
            if ($year >= 1900) {
                return $year;
            }
        }

        $header = ProposalHeader::query()
            ->where('data_id', $dataId)
            ->first();

        if ($header) {
            $proposalYear = (int)preg_replace('/[^\d]/', '', (string)($header->proposal_year ?? ''));
            if ($proposalYear >= 1900) {
                return $proposalYear;
            }

            $proposalDate = (string)($header->proposal_date ?? '');
            if ($proposalDate !== '') {
                try {
                    return (int)(new \DateTimeImmutable($proposalDate))->format('Y');
                } catch (\Throwable $e) {
                    // no-op
                }
            }
        }

        return (int)date('Y');
    }



    /**
     * 暦年贈与の贈与税額を「課税価格（基礎控除後）= afterK（千円）」から計算して「千円」で返す。
     * - 税率表は ZouyoGeneralRate / ZouyoTokureiRate を使用
     * - rate は 10.000 のような「%」想定（>1 なら /100）
     */
    private function calcGiftTaxKyen(int $afterK, bool $tokurei, int $kihuYear): int
    {
        if ($afterK <= 0) return 0;
        $baseYen = $afterK * 1000;
        $rows = $this->loadGiftRateRows($tokurei, $kihuYear);
        if (!$rows) return 0;

        foreach ($rows as $r) {
            $lower = (int)($r['lower'] ?? 0);
            $upper = $r['upper'] ?? null;
            $upperVal = $upper === null ? PHP_INT_MAX : (int)$upper;

            if ($baseYen < $lower || $baseYen > $upperVal) continue;

            $rate = (float)($r['rate'] ?? 0.0);
            if ($rate > 1.0) $rate = $rate / 100.0; // 10.000 → 0.10
            $ded  = (int)($r['ded'] ?? 0);

            $taxYen = (int)round($baseYen * $rate) - $ded;
            if ($taxYen < 0) $taxYen = 0;

            return (int)round($taxYen / 1000);
        }
        return 0;
    }

    /**
     * 税率表の行をキャッシュして返す
     *
     * @return array<int,array{lower:int,upper:?int,rate:float,ded:int}>
     */
    private function loadGiftRateRows(bool $tokurei, int $kihuYear): array
    {
        $key = $tokurei ? 1 : 0;
        if (isset($this->giftRateCache[$key][$kihuYear])) {
            return $this->giftRateCache[$key][$kihuYear];
        }

        if ($tokurei) {
            $ver = (int)(ZouyoTokureiRate::query()->whereNull('company_id')->where('kihu_year', $kihuYear)->max('version') ?: 1);
            $list = ZouyoTokureiRate::query()
                ->whereNull('company_id')
                ->where('kihu_year', $kihuYear)
                ->where('version', $ver)
                ->orderBy('seq')
                ->get(['lower','upper','rate','deduction_amount']);
        } else {
            $ver = (int)(ZouyoGeneralRate::query()->whereNull('company_id')->where('kihu_year', $kihuYear)->max('version') ?: 1);
            $list = ZouyoGeneralRate::query()
                ->whereNull('company_id')
                ->where('kihu_year', $kihuYear)
                ->where('version', $ver)
                ->orderBy('seq')
                ->get(['lower','upper','rate','deduction_amount']);
        }

        $rows = [];
        foreach ($list as $r) {
            $rows[] = [
                'lower' => (int)($r->lower ?? 0),
                'upper' => $r->upper === null ? null : (int)$r->upper,
                'rate'  => (float)($r->rate ?? 0),
                'ded'   => (int)($r->deduction_amount ?? 0),
            ];
        }

        $this->giftRateCache[$key][$kihuYear] = $rows;
        return $rows;
    }
    



    /**
     * future_zouyo.blade 相当の prefillFuture から、受贈者・行番号・候補キーで値を拾う。
     *
     * 想定される配列形の揺れ：
     * - $prefillFuture[recipient_no][row_no][key]
     * - $prefillFuture[recipient_no]['rows'][row_no][key]
     * - $prefillFuture[recipient_no][row_no - 1][key]
     *
     * @param array<string|int,mixed> $prefillFuture
     * @param array<int,string>       $candidateKeys
     * @return mixed|null
     */
    private function getPrefillFutureValue(array $prefillFuture, int $recipientNo, int $rowNo, array $candidateKeys)
    {
        if ($recipientNo <= 0 || $rowNo <= 0) {
            return null;
        }

        $recipientBlock = $prefillFuture[$recipientNo] ?? null;
        if (!is_array($recipientBlock)) {
            return null;
        }

        $candidates = [];

        if (isset($recipientBlock[$rowNo]) && is_array($recipientBlock[$rowNo])) {
            $candidates[] = $recipientBlock[$rowNo];
        }

        if (isset($recipientBlock['rows']) && is_array($recipientBlock['rows'])) {
            if (isset($recipientBlock['rows'][$rowNo]) && is_array($recipientBlock['rows'][$rowNo])) {
                $candidates[] = $recipientBlock['rows'][$rowNo];
            }
            if (isset($recipientBlock['rows'][$rowNo - 1]) && is_array($recipientBlock['rows'][$rowNo - 1])) {
                $candidates[] = $recipientBlock['rows'][$rowNo - 1];
            }
        }

        if (isset($recipientBlock[$rowNo - 1]) && is_array($recipientBlock[$rowNo - 1])) {
            $candidates[] = $recipientBlock[$rowNo - 1];
        }

        foreach ($candidates as $row) {
            foreach ($candidateKeys as $key) {
                if (!array_key_exists($key, $row)) {
                    continue;
                }
                $value = $row[$key];
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }




    
}
