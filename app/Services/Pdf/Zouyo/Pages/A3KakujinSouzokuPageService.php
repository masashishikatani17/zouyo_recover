<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use App\Models\FutureGiftRecipient;
use App\Models\ProposalFamilyMember;
use App\Models\InheritanceDistributionHeader;
use App\Models\InheritanceDistributionMember;
use TCPDF;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class A3KakujinSouzokuPageService implements ZouyoPdfPageInterface
{

    public function render(TCPDF $pdf, array $payload): void
    {
        $dataId = (int)($payload['data_id'] ?? 0);

        //Log::info('Payload for results data: ' . json_encode($payload));

        /*
        if ($dataId <= 0) {
            Log::warning('[KakujinsouzokuPageService] data_id missing in payload');
            return;
        }
        */

        $wakusen = 0;
        $templatePath = resource_path('/views/pdf/A3_05_pr_kakusouzoku.pdf');

        $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);

        $pdf->AddPage();
        $pdf->useTemplate($tpl);

        // $family を $payload から取得
        $familyRows = $payload['family'] ?? [];
        
        // row_no が正しく設定されていない場合、1から連番を付ける
        $familyRows = array_map(function ($item, $index) {
            $item['row_no'] = $item['row_no'] ?? ($index + 1); // row_no がない場合、index+1 を設定
            return $item;
        }, $familyRows, array_keys($familyRows));
        
        // コレクションに変換して row_no でキーを設定
        $familyRows = collect($familyRows)->keyBy('row_no');
        
        // family データを $family に設定
        $family = $familyRows->toArray(); // これで $family が設定される
        
        //dd($familyRows, $family);  // 確認用

        // $prefillInheritance と $prefillFamily を取得
        $prefillInheritance = $payload['prefillInheritance'] ?? [];
        // prefillFamily が無ければ payload の family をフォールバックとして利用
        $prefillFamily      = $payload['prefillFamily']      ?? ($payload['family'] ?? []);


//dd($prefillFamily);

        
        /**
         * ▼ 遺産分割(現時点)プレフィル（payloadに無ければ Controller と同等ロジックで再構築）
         *
         *  - method_code : 0 = 法定相続割合(auto), 9 = 手入力(manual)
         *  - members[no] : ['taxable_auto' => 千円, 'taxable_manu' => 千円]
         *  - other_credit[no] : その他税額控除（千円）
         */
        if (empty($prefillInheritance)) {
            $prefillInheritance = [
                'method_code'  => null,
                'members'      => [],
                'other_credit' => [],
            ];

            // ヘッダ 1:1
            if (class_exists(InheritanceDistributionHeader::class)) {
                if ($ih = InheritanceDistributionHeader::where('data_id', $dataId)->first()) {
                    $prefillInheritance['method_code'] = $ih->method_code;
                }
            }

            // 明細 2..10
            if (class_exists(InheritanceDistributionMember::class)) {
                $rows = InheritanceDistributionMember::where('data_id', $dataId)->get();
                foreach ($rows as $r) {
                    $no = (int) $r->recipient_no;
                    if ($no < 2 || $no > 10) {
                        continue;
                    }
                    $prefillInheritance['members'][$no] = [
                        'taxable_auto' => $r->taxable_auto_value_thousand,
                        'taxable_manu' => $r->taxable_manu_value_thousand,
                    ];
                    $prefillInheritance['other_credit'][$no] = $r->other_tax_credit_thousand;
                }
            }
        }

        // header の取得
        $header = $payload['header'] ?? [];
        
        /**
         * ▼ 相続税計算結果（ZouyotaxCalc）の取得
         *   - payload['resultsData'] があれば最優先
         *   - なければ Session / Cache から SouzokukazeikakakuPageService と同様にフォールバック
         */
        $resultsData = $payload['resultsData'] ?? [];
        if (empty($resultsData)) {
            // 従来のセッション直格納
            $resultsData = Session::get('zouyo.results', []);
        }
        if (empty($resultsData)) {
            // セッションキーを経由して Cache から取得
            $resultsKey = Session::get('zouyo.results_key');
            if ($resultsKey) {
                $resultsData = Cache::get($resultsKey, []);
            }
        }
        
        /*
        Log::info('[KakujinsouzokuPageService] Results Data: ' . json_encode($resultsData));
        */

        // before（対策前）を優先し、無ければ after を使用（isanbunkatu.blade と同じ思想）
        $root = is_array($resultsData) ? $resultsData : [];
        $calc = [];
        if (isset($root['before']) && is_array($root['before'])) {
            $calc = $root['before'];
        } elseif (isset($root['after']) && is_array($root['after'])) {
            $calc = $root['after'];
        }
        $summary       = $calc['summary'] ?? [];
        $basicDedKyen  = (int) round(((int)($summary['basic_deduction'] ?? 0)) / 1000); // 基礎控除額（千円）
        

        // method_code: 0=法定相続割合(auto), 9=手入力(manual)
        $methodCode = (int) ($prefillInheritance['method_code'] ?? 0);

        /**
         * ▼ heirs 配列を row_index で引ける形に整形
         *    （isanbunkatu.blade と同じ構造を再現）
         */
        $heirsByIdx = [];
        if (isset($calc['heirs']) && is_iterable($calc['heirs'])) {
            foreach ($calc['heirs'] as $h) {
                if (isset($h['row_index'])) {
                    $heirsByIdx[(int) $h['row_index']] = $h;
                }
            }
        }
        

        /**
         * ▼ title.blade / isanbunkatu.blade と同じく
         *    prefillFamily → family の順で bunsi / bunbo を採用
        */
        
        
        $lawShareByNo = [];
        $legalHeirCount = 0;
        for ($no = 2; $no <= 10; $no++) {
            $bunsi = $prefillFamily[$no]['bunsi'] ?? null;
            $bunbo = $prefillFamily[$no]['bunbo'] ?? null;

            //if (($bunsi === null || $bunsi === '') && ($bunbo === null || $bunbo === '')) {
            //    $bunsi = $family[$no]['bunsi'] ?? null;
            //    $bunbo = $family[$no]['bunbo'] ?? null;
            //}

            $bunsiInt = ($bunsi === null || $bunsi === '') ? null : (int)preg_replace('/[^\d\-]/u', '', (string)$bunsi);
            $bunboInt = ($bunbo === null || $bunbo === '') ? null : (int)preg_replace('/[^\d\-]/u', '', (string)$bunbo);

            $lawShareByNo[$no] = [
                'bunsi' => $bunsiInt,
                'bunbo' => $bunboInt,
            ];

            if ($bunsiInt !== null && $bunboInt !== null && $bunsiInt >= 1 && $bunboInt >= 1) {
                $legalHeirCount++;
            }
        }
        

        $basicDeductionFormulaLabel = $this->resolveBasicDeductionFormulaLabel(
            $payload,
            $summary,
            $legalHeirCount
        );        

        /**
         * ▼ 課税遺産総額（合計)
         */
        $taxableEstate = (int) round(((int)($summary['taxable_estate'] ?? 0)) / 1000); // 千円
        $taxableEstateShareKByHeir = [];
        for ($no = 2; $no <= 10; $no++) {
            $taxableEstateShareKByHeir[$no] = 0;
        }



        // 被相続人の「所有財産」（千円）＝ isanbunkatu.blade の $___basePropertyKyen 相当
        $basePropertyKyen = (int) Arr::get($prefillFamily, '1.property', 0);

        // Blade 側と同様に「課税価格 合計」＝ 所有財産 + 生前贈与加算合計（いずれも千円）
        // ※ ここではまだ $sumLifetimeGiftKyen 未算出のため、後段で再代入する
        $taxableTotalKyen = $basePropertyKyen;



        /**
         * ▼ 各相続人の「所有財産（千円）」を算出
         * Blade の最終表示に合わせるため、
         * auto 時はまず split 行（cash_share + other_share）を優先する。
         * これがあれば、JSで見えている所有財産に最も近い。
         */
        $propertyShareKByHeir = [];
        for ($no = 2; $no <= 10; $no++) {
            $propertyShareKByHeir[$no] = 0;
        }


        $civilShareTargets = [];

        for ($no = 2; $no <= 10; $no++) {

            // ▼ [法定相続割合] の按分対象は、現在画面で使っている civil_share のみを採用する
            //    family へのフォールバックをすると過去値を拾ってしまい、
            //    本来 0 の相続人（例: idx:3）が対象に入ることがある
            $civilBunsi = $prefillFamily[$no]['civil_share_bunsi'] ?? null;
            $civilBunbo = $prefillFamily[$no]['civil_share_bunbo'] ?? null;


            $civilBunsiInt = ($civilBunsi === null || $civilBunsi === '') ? 0 : (int)preg_replace('/[^\d\-]/u', '', (string)$civilBunsi);
            $civilBunboInt = ($civilBunbo === null || $civilBunbo === '') ? 0 : (int)preg_replace('/[^\d\-]/u', '', (string)$civilBunbo);

            if ($civilBunsiInt >= 1 && $civilBunboInt >= 1) {


                $civilShareTargets[$no] = [
                    'bunsi' => $civilBunsiInt,
                    'bunbo' => $civilBunboInt,
                ];


            }
        }


        // auto（法定相続割合）は Blade の computeAutoShares() と同じく
        // civil_share_bunsi / civil_share_bunbo だけで所有財産を再計算する
        if ($methodCode !== 9 && $basePropertyKyen > 0) {
            
            
            // 念のため、対象外は明示的に 0 初期化しておく
            for ($no = 2; $no <= 10; $no++) {
                if (!isset($civilShareTargets[$no])) {
                    $propertyShareKByHeir[$no] = 0;
                }
            }


            $propertySumShares = 0;
            $lastPropertyNo = null;

            foreach ($civilShareTargets as $no => $share) {
                $bunsi = (int)($share['bunsi'] ?? 0);
                $bunbo = (int)($share['bunbo'] ?? 0);
                if ($bunsi <= 0 || $bunbo <= 0) {
                    continue;
                 }
 
                $shareK = (int) round($basePropertyKyen * ($bunsi / $bunbo));
                $propertyShareKByHeir[$no] = $shareK;
                $propertySumShares += $shareK;

                if ($shareK > 0) {
                    $lastPropertyNo = $no;
                }
             }

            $diff = $basePropertyKyen - $propertySumShares;
            if ($diff !== 0 && $lastPropertyNo !== null) {
                $propertyShareKByHeir[$lastPropertyNo] = (int)($propertyShareKByHeir[$lastPropertyNo] ?? 0) + $diff;
            }
        }
         
         
        /**
         * ▼ 課税遺産総額（相続人別）
         *
         * isanbunkatu.blade と同じく、
         * 税法上の法定相続割合（heirsByIdx[$no]['bunsi'] / ['bunbo']）で按分する。
         * ここは課税価格の民法上割合ロジックとは別。
         */
         $taxableEstateSumShares = 0;
         $lastTaxableEstateNo = null;

         if ($taxableEstate > 0) {

            // 念のため初期化
             for ($no = 2; $no <= 10; $no++) {
                $taxableEstateShareKByHeir[$no] = 0;
             }
 
            $gcd = function (int $a, int $b): int {
                $a = abs($a); $b = abs($b);
                while ($b !== 0) {
                    $t = $a % $b;
                    $a = $b;
                    $b = $t;
                }
                return $a === 0 ? 1 : $a;
            };
            $lcm = function (int $a, int $b) use ($gcd): int {
                $a = abs($a); $b = abs($b);
                if ($a === 0 || $b === 0) {
                    return 0;
                }
                return (int)($a / $gcd($a, $b) * $b);
            };

            $bunboLcm = 1;
            $targets  = [];
            for ($no = 2; $no <= 10; $no++) {
                $bunsi = (int)($heirsByIdx[$no]['bunsi'] ?? 0);
                $bunbo = (int)($heirsByIdx[$no]['bunbo'] ?? 0);
                if ($bunsi >= 1 && $bunbo >= 1) {
                    $targets[] = $no;
                    $bunboLcm  = $lcm($bunboLcm, $bunbo);
                }
            }

            $weights = [];
            $sumW    = 0;
            foreach ($targets as $no) {
                $bunsi = (int)($heirsByIdx[$no]['bunsi'] ?? 0);
                $bunbo = (int)($heirsByIdx[$no]['bunbo'] ?? 0);
                $w = ($bunbo > 0) ? (int)($bunsi * ($bunboLcm / $bunbo)) : 0;
                $weights[$no] = $w;
                $sumW += $w;
            }

            if ($sumW > 0) {
                foreach ($targets as $no) {
                    $w = (int)($weights[$no] ?? 0);
                    if ($w <= 0) continue;
                    $shareK = (int) floor($taxableEstate * $w / $sumW);
                     $taxableEstateShareKByHeir[$no] = $shareK;
                     $taxableEstateSumShares += $shareK;
                     if ($shareK > 0) {
                         $lastTaxableEstateNo = $no;
                     }
                 }
             }
 
            $diff = $taxableEstate - $taxableEstateSumShares;
             if ($diff !== 0 && $lastTaxableEstateNo !== null) {
                 $taxableEstateShareKByHeir[$lastTaxableEstateNo] = (int)($taxableEstateShareKByHeir[$lastTaxableEstateNo] ?? 0) + $diff;
             }
         }


        /**
         * ▼ 生前贈与加算（過去贈与の加算分）を千円で集計
         *    - 各相続人ごと：heirs[row_index].past_gift_included_yen / 1000（四捨五入）
         *    - 合計欄：2〜10の合計値
         */
        $lifetimeGiftKyenByNo = [];
        $sumLifetimeGiftKyen  = 0;
        for ($no = 2; $no <= 10; $no++) {
            $yen = (int) ($heirsByIdx[$no]['past_gift_included_yen'] ?? 0);
            if ($yen === 0) {
                $lifetimeGiftKyenByNo[$no] = 0;
                continue;
            }
            $kyen = (int) round($yen / 1000);
            $lifetimeGiftKyenByNo[$no] = $kyen;
            $sumLifetimeGiftKyen      += $kyen;
        }



        /**
         * ▼ 左上「課税価格の計算」表（対策前・1年目）
         *
         *  - 所有財産の額(贈与前)      = 被相続人の所有財産（千円）
         *  - 贈与加算累計額（暦年贈与） = before結果の暦年分加算額（千円）
         *  - 贈与加算累計額（精算贈与） = before結果の精算分加算額（千円）
         *  - 課税価格                  = 相続財産の額(贈与後) + 上記2加算
         *
         *  ※ 対策前・1年目なので「贈与による財産の減少」は 0 とし、
         *     「相続財産の額(贈与後)」は「所有財産の額(贈与前)」と同額にする。
         *  ※ 暦年/精算の分割キーは resultsData の構造差異に備えて候補キーを順に参照する。
         *     もし split が取れず total しか取れない場合は、課税価格との整合を優先して
         *     total を暦年側へ寄せるフォールバックにしている。
         */
        $pickFirstNumeric = function (array $source, array $keys): ?int {
            foreach ($keys as $key) {
                $val = Arr::get($source, $key);
                if ($val === null || $val === '') {
                    continue;
                }
                if (is_numeric($val)) {
                    return (int) $val;
                }
            }
            return null;
        };

        $sumHeirYenByKeys = function (array $keys) use ($heirsByIdx, $pickFirstNumeric): int {
            $sum = 0;
            for ($i = 2; $i <= 10; $i++) {
                $row = is_array($heirsByIdx[$i] ?? null) ? $heirsByIdx[$i] : [];
                $val = $pickFirstNumeric($row, $keys);
                if ($val !== null) {
                    $sum += $val;
                }
            }
            return $sum;
        };

        $calendarGiftAddYen = $pickFirstNumeric($summary, [
            'calendar_gift_addition_yen',
            'calendar_gift_included_yen',
            'past_gift_calendar_included_yen',
            'total_calendar_gift_included_yen',
        ]);
        if ($calendarGiftAddYen === null) {
            $calendarGiftAddYen = $sumHeirYenByKeys([
                'calendar_gift_addition_yen',
                'calendar_gift_included_yen',
                'past_gift_calendar_included_yen',
                'past_gift_included_calendar_yen',
                'lifetime_gift_addition_calendar_yen',
            ]);
        }

        $settlementGiftAddYen = $pickFirstNumeric($summary, [
            'settlement_gift_addition_yen',
            'settlement_gift_included_yen',
            'past_gift_settlement_included_yen',
            'total_settlement_gift_included_yen',
        ]);
        if ($settlementGiftAddYen === null) {
            $settlementGiftAddYen = $sumHeirYenByKeys([
                'settlement_gift_addition_yen',
                'settlement_gift_included_yen',
                'past_gift_settlement_included_yen',
                'past_gift_included_settlement_yen',
                'lifetime_gift_addition_settlement_yen',
            ]);
        }

        $calendarGiftAddKyen   = (int) round(((int) $calendarGiftAddYen) / 1000);
        $settlementGiftAddKyen = (int) round(((int) $settlementGiftAddYen) / 1000);

        if ($calendarGiftAddKyen === 0 && $settlementGiftAddKyen === 0 && $sumLifetimeGiftKyen > 0) {
            $calendarGiftAddKyen = $sumLifetimeGiftKyen;
        }

        $propertyBeforeGiftKyen      = $basePropertyKyen;
        $calendarGiftReductionKyen   = 0;
        $settlementGiftReductionKyen = 0;
        $propertyAfterGiftKyen       = max(
            0,
            $propertyBeforeGiftKyen - $calendarGiftReductionKyen - $settlementGiftReductionKyen
        );
        $taxablePriceCalcKyen = $propertyAfterGiftKyen + $calendarGiftAddKyen + $settlementGiftAddKyen;



        // 課税価格 合計（所有財産 + 生前贈与加算合計）をここで確定
        $taxableTotalKyen = $basePropertyKyen + $sumLifetimeGiftKyen;


        // family テーブルから氏名を取得
        $familyRows = ProposalFamilyMember::query()
            ->where('data_id', $dataId)
            ->orderBy('row_no')
            ->get()
            ->keyBy('row_no');
        
        // 被相続人氏名をPDFに描画
        // 受贈者情報取得
        $donorName  = (string)(
            // 被相続人の名前が空でない場合にprefillInheritanceを確認
            ($familyRows[1]->name ?? null)
            ?? ($header['customer_name'] ?? '')
        );

        // 被相続人氏名をPDFに描画
        $pdf->SetFont('mspgothic03', '', 12);
        $xx =  56.0;
        $yy = 121.5;
        $pdf->MultiCell(40, 10, $donorName , $wakusen, 'L', 0, 0, $xx, $yy);


        // 続柄マスタ
        $relationships = config('relationships');

        $heirNames        = [];
        $heirRels         = [];
        $heirTaxablePrice = []; // 課税価格（千円）

        // 相続人情報取得
        $recipients = FutureGiftRecipient::query()
            ->where('data_id', $dataId)  // 計算済み相続人情報を取得
            ->orderBy('recipient_no')    // recipient_no でソート
            ->get();                      // 結果を取得


        foreach ($familyRows as $rowNo => $familyMember) {
            // 相続人の氏名・続柄
            // 仕様：氏名が空欄のときは続柄も必ず空欄にする
            $name = trim((string)($familyMember['name'] ?? ''));
            $heirNames[$rowNo] = $name;

            if ($name === '') {
                $heirRels[$rowNo] = '';
            } else {
                $relCode = $familyMember['relationship_code'] ?? null;
                $heirRels[$rowNo] = $relationships[$relCode] ?? '';
            }
        }




        // 相続人情報をPDFに描画
        $pdf->SetFont('mspgothic03', '', 11);
        $xx = 139.0;
        $yy += 17.0;


        // ▼ 合計欄の「課税価格（千円）」：isanbunkatu.blade の「課税価格 合計（合計）」と同額にする
        //   Controller 側で $prefillFamily[1]['taxable'] として渡す想定。
        //   無い場合は family[1]['property'] をフォールバックとして使用。
        $baseTaxableKyen = (int) Arr::get(
            $prefillFamily,
            '1.taxable',
            Arr::get($prefillFamily, '1.property', 0)
        );

        // 相続税の総額（合計）: summary.sozoku_tax_total（円）→千円
        $sozokuTaxTotalKyen  = (int) round(((int)($summary['sozoku_tax_total'] ?? 0)) / 1000); // 相続税の総額（千円）


        /**
         * ▼ 算出税額 合計
         *  - heirs[2..10].sanzutsu_tax_yen（円）の合計 → 千円（四捨五入）
         *  - isanbunkatu.blade の「算出税額」行・合計欄と同じ値
         */
        $sumSanzutsuYen = 0;
        for ($__i = 2; $__i <= 10; $__i++) {
            $sumSanzutsuYen += (int) ($heirsByIdx[$__i]['sanzutsu_tax_yen'] ?? 0);
        }
        $sumSanzutsuTotalKyen = (int) round($sumSanzutsuYen / 1000);

        /**
         * ▼ ２割加算（合計 ＋ 相続人別）
         *  - 各人ごとの差額 = final_tax_yen - sanzutsu_tax_yen（円）
         *  - マイナスの場合は 0 とみなす
         *  - 合計欄は差額の合計（円）→ 千円
         */
        $twoWarYenByNo   = [];
        $twoWarSumYen    = 0;
        for ($__i = 2; $__i <= 10; $__i++) {
            $inc = ((int)($heirsByIdx[$__i]['final_tax_yen'] ?? 0)) - ((int)($heirsByIdx[$__i]['sanzutsu_tax_yen'] ?? 0));
            $inc = $inc > 0 ? $inc : 0;
            $twoWarYenByNo[$__i] = $inc;
            $twoWarSumYen       += $inc;
        }
        $twoWarSumKyen = (int) round($twoWarSumYen / 1000);



        /**
         * ▼ 暦年課税分の贈与税額控除額（合計）
         *  - summary.total_gift_tax_credits（円）→ 千円
         */
        $totalGiftTaxCreditsKyen = (int) round(((int)($summary['total_gift_tax_credits'] ?? 0)) / 1000);

        /**
         * ▼ 配偶者の税額軽減額（合計）
         *  - summary.total_spouse_relief（円）→ 千円
         */
        $totalSpouseReliefKyen = (int) round(((int)($summary['total_spouse_relief'] ?? 0)) / 1000);

        /**
         * ▼ その他の税額控除額（合計）
         *  - summary.total_other_credits（円）→ 千円
         */
        $totalOtherCreditsKyen = (int) round(((int)($summary['total_other_credits'] ?? 0)) / 1000);

        /**
         * ▼ 控除税額合計（合計）
         *  - 暦年課税分の贈与税額控除額 ＋ 配偶者の税額軽減額 ＋ その他の税額控除額
         *  - すべて千円単位で加算
         */
        $totalCreditsAllKyen = $totalGiftTaxCreditsKyen + $totalSpouseReliefKyen + $totalOtherCreditsKyen;


        /**
         * ▼ 差引税額（合計）
         *  - summary.total_sashihiki_tax（円）→ 千円
         *  - isanbunkatu.blade の「差引税額」行・合計欄に対応
         */
        $totalSashihikiTaxKyen = (int) round(((int)($summary['total_sashihiki_tax'] ?? 0)) / 1000);

        /**
         * ▼ 相続時精算課税分の贈与税額控除額（合計）
         *  - 各人欄と同じく heirs[*].settlement_gift_tax_yen を合算して表示する
         *  - summary.total_settlement_gift_taxes は appliedSetCredit 合計で意味が異なるため使わない
         */
        $totalSettlementGiftYen = 0;
        for ($__i = 2; $__i <= 10; $__i++) {
            $totalSettlementGiftYen += (int)($heirsByIdx[$__i]['settlement_gift_tax_yen'] ?? 0);
        }
        $totalSettlementGiftKyen = (int) round($totalSettlementGiftYen / 1000);


        /**
         * ▼ 小計（合計）
         *  - No17 = No15 - No16 の生値
         *  - summary.total_raw_final_after_settlement（円）を最優先
         *  - 無ければ summary.final_after_settlement_yen をフォールバック
         */
        $totalFinalAfterSettlementKyen = (int) round(((int)(
            $summary['total_raw_final_after_settlement']
                ?? $summary['final_after_settlement_yen']
                ?? 0
        )) / 1000);


        /**
         * ▼ 納付税額（合計）
         *
         *  - heirs[2..10].payable_tax_yen（円）の合計 → 千円（四捨五入）
         *  - isanbunkatu.blade の「納付税額」行・合計欄に対応
         */
        $sumPayableYen = 0;
        for ($__i = 2; $__i <= 10; $__i++) {
            $sumPayableYen += (int) ($heirsByIdx[$__i]['payable_tax_yen'] ?? 0);
        }
        $sumPayableKyen = (int) round($sumPayableYen / 1000);


        /**
         * ▼ 還付税額（合計）
         *
         *  - heirs[2..10].refund_tax_yen（円）の合計 → 千円（四捨五入）
         *  - isanbunkatu.blade の「還付税額」行・合計欄に対応
         */
        $sumRefundYen = 0;
        for ($__i = 2; $__i <= 10; $__i++) {
            $sumRefundYen += (int) ($heirsByIdx[$__i]['refund_tax_yen'] ?? 0);
        }
        $sumRefundKyen = (int) round($sumRefundYen / 1000);


        // A3版は1ページ構成
        $pdf->SetFont('mspgothic03', '', 10);
        $wakusen = 0;
        $x = 375;
        $y = 277;
        $pdf->MultiCell(30, 6, '(5ページ)', $wakusen, 'R', 0, 0, $x, $y);


        /**
         * ▼ 左上「課税価格の計算」表の金額印字（対策前・1年目）
         * 右端の金額欄に表示する
         */
        $pdf->SetFont('mspgothic03', '', 11);
        $leftCalcValueX = 142.0;
        $leftCalcValueW = 22.0;
        $drawLeftCalcValue = function ($value, float $yPos) use ($pdf, $wakusen, $leftCalcValueX, $leftCalcValueW): void {
            if ($value === null || $value === '') {
                return;
            }
            $pdf->MultiCell($leftCalcValueW, 6, number_format((int) $value), $wakusen, 'R', 0, 0, $leftCalcValueX, $yPos);
        };

        $pdf->SetFont('mspgothic03', '', 11);
        $drawLeftCalcValue($propertyBeforeGiftKyen, 58.0);   // 所有財産の額(贈与前)
        $drawLeftCalcValue($propertyAfterGiftKyen,  78.0);    // 相続財産の額(贈与後)
        $drawLeftCalcValue($calendarGiftAddKyen,    84.0);      // 贈与加算累計額　暦年贈与
        $drawLeftCalcValue($settlementGiftAddKyen,  91.0);    // 贈与加算累計額　精算贈与
        $drawLeftCalcValue($taxablePriceCalcKyen,   97.3);     // 課税価格


        for ($no = 1; $no <= 10; $no++) {

            $hasHeirName = trim((string)($heirNames[$no] ?? '')) !== '';

            if ($no === 1) {
                $pdf->SetFont('mspgothic03', '', 11);
            }



            // 相続人の名前と続柄をPDFに描画
            if ($no !== 1 && $hasHeirName) {
                $pdf->MultiCell(28, 10, (string)($heirNames[$no] ?? ''), $wakusen, 'C', 0, 0, $xx + 1.5, $yy);


                $relLabel = (string)($heirRels[$no] ?? '');
                $relFontSize = $this->resolveRelationshipFontSize($relLabel);
                $relCellHeightRatio = $this->resolveRelationshipCellHeightRatio($relLabel);

                $pdf->SetFont('mspgothic03', '', $relFontSize);
                $pdf->setCellHeightRatio($relCellHeightRatio);
                $pdf->MultiCell(28, 10, $relLabel, $wakusen, 'C', 0, 0, $xx + 1.5, $yy + 6.5);
                $pdf->setCellHeightRatio(1.25);
                $pdf->SetFont('mspgothic03', '', 11);

            }
             

            $pdf->SetFont('mspgothic03', '', 12);

            if ($no === 1) {
                // → 仕様変更：isanbunkatu.blade の「課税価格 合計（合計）」と同じく
                //    「所有財産（合計）」＋「生前贈与加算（合計）」を表示する
                if ($taxableTotalKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($taxableTotalKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 13.2);
                }
                
                // ▼ 基礎控除額（合計）を印字
                //   isanbunkatu.blade では summary.basic_deduction を千円換算して合計欄に表示している。
                //   個人別の内訳は空欄なので、ここでも合計欄のみ印字する。
                if ($basicDedKyen > 0) {

                    //法定相続人　人数
                    $pdf->SetFont('mspgothic03', '', 10);
                    if ($basicDeductionFormulaLabel !== '') {
                        $pdf->SetFont('mspgothic03', '', 10);
                        $pdf->MultiCell(
                            120,
                            10,
                            $basicDeductionFormulaLabel,
                            $wakusen,
                            'R',
                            0,
                            0,
                            $xx - 130,
                            $yy + 20.0
                        );
                    }

                    $pdf->SetFont('mspgothic03', '', 12);
                    $pdf->MultiCell(28, 10, number_format($basicDedKyen),$wakusen, 'R', 0, 0, $xx - 2.0 , $yy + 19.5);
                }
                        
                // 課税遺産総額をPDFに描画
                if ($taxableEstate > 0) {
                    $pdf->SetFont('mspgothic03', '', 12);
                    $pdf->MultiCell(28, 10, number_format($taxableEstate), $wakusen, 'R', 0, 0, $xx - 2.0 , $yy + 26.2);
                }



                // ▼ 相続税の総額（合計）を印字
                //   isanbunkatu.blade の「相続税の総額」行（summary.sozoku_tax_total）と同じ値（千円）を表示
                if ($sozokuTaxTotalKyen > 0) {
                    $pdf->SetFont('mspgothic03', '', 12);
                    // 「相続税の総額」行の合計欄
                    $pdf->MultiCell(28, 10, number_format($sozokuTaxTotalKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 39.5);
                }


                /**
                 * ▼ 按分割合（合計）
                 *  - isanbunkatu.blade の「あん分割合」行の合計欄と同じく 1.0000 固定
                 */
                $pdf->SetFont('mspgothic03', '', 12);
                // 「あん分割合」行の合計欄（相続税の総額の 1 行下）
                $pdf->MultiCell(28, 10, '1.0000', $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 45.7);

                // ▼ 算出税額 合計（= heirs[].sanzutsu_tax_yen の合計 / 1000）
                if ($sumSanzutsuTotalKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($sumSanzutsuTotalKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 52.5);
                }


                // ▼ ２割加算 合計（= Σ max(final_tax_yen − sanzutsu_tax_yen, 0) / 1000）
                if ($twoWarSumKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($twoWarSumKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 59.0);
                }



                // ▼ 暦年課税分の贈与税額控除額（合計）
                //   isanbunkatu.blade の「暦年課税分の贈与税額控除額」行（summary.total_gift_tax_credits）と同じ値（千円）
                if ($totalGiftTaxCreditsKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($totalGiftTaxCreditsKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 65.4);
                }


                // ▼ 配偶者の税額軽減額（合計）
                //   isanbunkatu.blade の「配偶者の税額軽減額」行（summary.total_spouse_relief）と同じ値（千円）
                if ($totalSpouseReliefKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($totalSpouseReliefKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 72.0);
                }
                

                // ▼ その他の税額控除額（合計）
                //   isanbunkatu.blade の「その他の税額控除額」行（summary.total_other_credits）と同じ値（千円）
                if ($totalOtherCreditsKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($totalOtherCreditsKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 78.4);
                }


                // ▼ 控除税額合計（合計）
                //   isanbunkatu.blade の「控除税額合計」行（暦年＋配偶者＋その他）の合計欄に対応
                if ($totalCreditsAllKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($totalCreditsAllKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 85.1);
                }
            

                // ▼ 差引税額（合計）
                //   isanbunkatu.blade の「差引税額」行（summary.total_sashihiki_tax）と同じ値（千円）
                if ($totalSashihikiTaxKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($totalSashihikiTaxKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 91.6);
                }


                // ▼ 相続時精算課税分の贈与税額控除額（合計）
                //   （isanbunkatu.blade の「相続時精算課税分の贈与税額控除額」行・合計欄）
                if ($totalSettlementGiftKyen !== 0) {
                    $pdf->MultiCell(28, 10, number_format($totalSettlementGiftKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 98.3);
                }


                // ▼ 小計（合計）
                //   isanbunkatu.blade の「小計」行（summary.total_final_after_settlement）と同じ値（千円）
                if ($totalFinalAfterSettlementKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($totalFinalAfterSettlementKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 104.9);
                }


                // ▼ 納付税額（合計）
                //   isanbunkatu.blade の「納付税額」行・合計欄に対応
                if ($sumPayableKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($sumPayableKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 111.5);
                }



                // ▼ 還付税額（合計）
                //   isanbunkatu.blade の「還付税額」行・合計欄に対応
                if ($sumRefundKyen !== 0) {
                    $pdf->MultiCell(28, 10, number_format($sumRefundKyen), $wakusen, 'R', 0, 0, $xx - 2.0, $yy + 118.1);
                }


                // 合計列の割合 ＝ 納付税額合計 / 課税価格合計 × 100
                if ($sumPayableKyen > 0 && $taxableTotalKyen > 0) {
                    $wari = ($sumPayableKyen / $taxableTotalKyen) * 100;
                    $pdf->MultiCell(28, 10, number_format($wari, 2) . '％', $wakusen, 'R', 0, 0, $xx, $yy + 124.0);
                }

            }
            

            if ($no !== 1 && $hasHeirName) {
                        
                $member = $prefillInheritance['members'][$no] ?? [];

                // ▼ 所有財産（千円）
                if ($methodCode === 9) {
                    $heirPropertyKyen = (int)($member['taxable_manu'] ?? $member['taxable_auto'] ?? 0);
                } else {                    
                    $heirPropertyKyen = (int)($propertyShareKByHeir[$no] ?? 0);
                }

                // 生前贈与加算（千円）…isanbunkatu.blade の $heirsByIdx[$no]['lifetime_gift_addition'] 相当
                $heirLifetimeGiftKyen = (int) ($lifetimeGiftKyenByNo[$no] ?? 0);

                // 課税価格 合計（千円）＝ 所有財産 + 生前贈与加算
                $heirTaxableTotalKyen = $heirPropertyKyen + $heirLifetimeGiftKyen;
                $heirTaxablePrice[$no] = $heirTaxableTotalKyen;

                $val = $heirTaxableTotalKyen > 0
                    ? number_format($heirTaxableTotalKyen)
                    : '';
                if ($heirTaxableTotalKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirTaxableTotalKyen), $wakusen, 'R', 0, 0, $xx, $yy + 13.2);
                }


                // 課税遺産総額（相続人別）
                if ($methodCode === 9) {
                    $heirTaxableEstateKyen = (int)round(((int)($heirsByIdx[$no]['manual_taxable_share_yen'] ?? 0)) / 1000);
                    if ($heirTaxableEstateKyen <= 0) {
                        $heirTaxableEstateKyen = (int)($taxableEstateShareKByHeir[$no] ?? 0);
                    }
                } else {
                    $heirTaxableEstateKyen = (int)($taxableEstateShareKByHeir[$no] ?? 0);
                }

                // 課税遺産総額（相続人ごと・千円）をPDFに描画
                if ($heirTaxableEstateKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirTaxableEstateKyen), $wakusen, 'R', 0, 0, $xx, $yy + 26.2);
                }

//dd($prefillFamily);
//dd($family);


                 /**
                   * ▼ 法定相続分
                   *
                   *  - title.blade.php で入力・保存した税法上の法定相続割合（bunsi / bunbo）をそのまま表示
                   *  - 表示元は prefillFamily → family の順
                   *  - ただし souzokunin = 「法定相続人」の人だけ表示
                   *  - heirsByIdx にはフォールバックしない
                   *    （計算結果側を使うと、法定相続人以外にも 1/1 などが出るため）
                   *  - bunsi >= 1 且つ bunbo >= 1 のときだけ表示し、それ以外は空欄
                 */
                 $lawShareVal = '';
 

                $souzokunin = $prefillFamily[$no]['souzokunin'] ?? null;
                if ($souzokunin === null || $souzokunin === '') {
                    $souzokunin = $family[$no]['souzokunin'] ?? null;
                }
                $souzokunin = trim((string)$souzokunin);

                 $bunsi = 0;
                 $bunbo = 0;

                 $bunsiInt = 0;
                 $bunboInt = 0;

                 $bunsi = $prefillFamily[$no]['bunsi'] ?? 0;
                 $bunbo = $prefillFamily[$no]['bunbo'] ?? 0;
 

                 if (($bunsi === null || $bunsi === '') && ($bunbo === null || $bunbo === '')) {
                      $bunsi = $family[$no]['bunsi'] ?? null;
                      $bunbo = $family[$no]['bunbo'] ?? null;
                 }
 

                 $bunsiInt = ($bunsi === null || $bunsi === '') ? null : (int)preg_replace('/[^\d\-]/u', '', (string)$bunsi);
                 $bunboInt = ($bunbo === null || $bunbo === '') ? null : (int)preg_replace('/[^\d\-]/u', '', (string)$bunbo);
  

                 if (
                     $souzokunin === '法定相続人' && $bunsiInt !== null && $bunboInt !== null && $bunsiInt >= 1 && $bunboInt >= 1
                 ) {
                      $lawShareVal = '   ' . $bunsiInt . '/' . $bunboInt; // 例: "1/2"
                 }
 

                 if ($lawShareVal !== '') {
                     $pdf->MultiCell(28, 10, $lawShareVal, $wakusen, 'C', 0, 0, $xx + 2.0, $yy + 32.5);
                 }
                 

                /**
                 * ▼ 相続税の総額（相続人別）
                 *
                 *  - isanbunkatu.blade の「相続税の総額」行と同じロジック
                 *  - heirs[row_index].legal_tax_yen を千円換算して表示
                */
                $heirSozokuTaxKyen = 0;
                if (isset($heirsByIdx[$no]['legal_tax_yen'])) {
                    $heirSozokuTaxKyen = (int) round(((int)$heirsByIdx[$no]['legal_tax_yen']) / 1000);
                }

                if ($heirSozokuTaxKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirSozokuTaxKyen), $wakusen, 'R', 0, 0, $xx, $yy + 39.5);
                }


                /**
                 * ▼ 按分割合（相続人別）
                 *
                 *  - isanbunkatu.blade の「あん分割合」行と同じロジック
                 *  - heirs[row_index].anbun_ratio を小数第4位まで表示（例: 0.3750）
                 */
                $heirAnbunRatio = $heirsByIdx[$no]['anbun_ratio'] ?? null;
                if ($heirAnbunRatio !== null && $heirTaxableTotalKyen > 0) {
                    $ratioStr = number_format((float) $heirAnbunRatio, 4, '.', '');
                    $pdf->MultiCell(28, 10, $ratioStr, $wakusen, 'R', 0, 0, $xx, $yy + 45.7);
                }

                $heirSanzutsuKyen = 0;
                if (isset($heirsByIdx[$no]['sanzutsu_tax_yen'])) {
                    // 算出税額（円）→ 千円（四捨五入）
                    $heirSanzutsuKyen = (int) round(((int) $heirsByIdx[$no]['sanzutsu_tax_yen']) / 1000);
                }

                // ▼ 算出税額（相続人別）…isanbunkatu.blade の「算出税額」行に対応
                if ($heirSanzutsuKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirSanzutsuKyen), $wakusen, 'R', 0, 0, $xx, $yy + 52.5);
                }

                /**
                 * ▼ ２割加算（相続人別）
                 *
                 *  - diff = final_tax_yen - sanzutsu_tax_yen（円）
                 *  - diff > 0 のときだけ千円換算して表示
                 *  - isanbunkatu.blade の「２割加算」行と同じロジック
                 */
                $heirTwoWarKyen = 0;
                $incYen = $twoWarYenByNo[$no] ?? 0;
                if ($incYen > 0) {
                    $heirTwoWarKyen = (int) round($incYen / 1000);
                }

                if ($heirTwoWarKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirTwoWarKyen), $wakusen, 'R', 0, 0, $xx, $yy + 59.0);
                }



                /**
                 * ▼ 暦年課税分の贈与税額控除額（相続人別）
                 *
                 *  - isanbunkatu.blade の「暦年課税分の贈与税額控除額」行と同じロジック
                 *  - heirs[row_index].gift_tax_credit_calendar_yen（円）→ 千円
                 */
                $heirGiftCreditCalKyen = 0;
                if (isset($heirsByIdx[$no]['gift_tax_credit_calendar_yen'])) {
                    $heirGiftCreditCalKyen = (int) round(((int) $heirsByIdx[$no]['gift_tax_credit_calendar_yen']) / 1000);
                }

                if ($heirGiftCreditCalKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirGiftCreditCalKyen), $wakusen, 'R', 0, 0, $xx, $yy + 65.4);
                }

                /**
                 * ▼ 配偶者の税額軽減額（相続人別）
                 *
                 *  - isanbunkatu.blade の「配偶者の税額軽減額」行と同じロジック
                 *  - heirs[row_index].spouse_relief_yen（円）→ 千円
                 */
                $heirSpouseReliefKyen = 0;
                if (isset($heirsByIdx[$no]['spouse_relief_yen'])) {
                    $heirSpouseReliefKyen = (int) round(((int) $heirsByIdx[$no]['spouse_relief_yen']) / 1000);
                }

                if ($heirSpouseReliefKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirSpouseReliefKyen), $wakusen, 'R', 0, 0, $xx, $yy + 72.0);
                }


                /**
                 * ▼ その他の税額控除額（相続人別）
                 *
                 *  - isanbunkatu.blade の「その他の税額控除額」行と同じロジック
                 *  - heirs[row_index].other_credit_yen（円）→ 千円
                 */
                $heirOtherCreditKyen = 0;
                if (isset($heirsByIdx[$no]['other_credit_yen'])) {
                    $heirOtherCreditKyen = (int) round(((int) $heirsByIdx[$no]['other_credit_yen']) / 1000);
                }

                if ($heirOtherCreditKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirOtherCreditKyen), $wakusen, 'R', 0, 0, $xx, $yy + 78.4);
                }


                /**
                 * ▼ 控除税額合計（相続人別）
                 *
                 *  - 暦年課税分の贈与税額控除額 ＋ 配偶者の税額軽減額 ＋ その他の税額控除額
                 *  - すべて千円単位で加算
                 *  - isanbunkatu.blade の「控除税額合計」行・相続人別欄に対応
                 */
                $heirTotalCreditsKyen = $heirGiftCreditCalKyen
                    + $heirSpouseReliefKyen
                    + $heirOtherCreditKyen;

                if ($heirTotalCreditsKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirTotalCreditsKyen), $wakusen, 'R', 0, 0, $xx, $yy + 85.1);
                }

                /**
                 * ▼ 差引税額（相続人別）
                 *  - heirs[row_index].sashihiki_tax_yen（円）→ 千円
                 *  - isanbunkatu.blade の「差引税額」行・相続人別欄に対応
                 */
                $heirSashihikiKyen = 0;
                if (isset($heirsByIdx[$no]['sashihiki_tax_yen'])) {
                    $heirSashihikiKyen = (int) round(((int) $heirsByIdx[$no]['sashihiki_tax_yen']) / 1000);
                }

                if ($heirSashihikiKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirSashihikiKyen), $wakusen, 'R', 0, 0, $xx, $yy + 91.6);
                }


                /**
                 * ▼ 相続時精算課税分の贈与税額控除額（相続人別）
                 *  - heirs[row_index].settlement_gift_tax_yen（円）→ 千円
                 *  - isanbunkatu.blade の「相続時精算課税分の贈与税額控除額」行・相続人別欄に対応
                 */
                $heirSettlementGiftKyen = 0;
                if (isset($heirsByIdx[$no]['settlement_gift_tax_yen'])) {
                    $heirSettlementGiftKyen = (int) round(((int) $heirsByIdx[$no]['settlement_gift_tax_yen']) / 1000);
                }

                if ($heirSettlementGiftKyen !== 0) {                    
                    $pdf->MultiCell(28, 10, number_format($heirSettlementGiftKyen), $wakusen, 'R', 0, 0, $xx, $yy + 98.3);
                }


                /**
                 * ▼ 小計（相続人別）
                 *
                 *  - No17 = No15 - No16 の生値
                 *  - raw_final_after_settlement_yen を最優先
                 *  - 無ければ final_after_settlement_yen をフォールバック
                 */
                $heirFinalAfterSettlementKyen = 0;
                $heirFinalAfterSettlementYen = (int) (
                    $heirsByIdx[$no]['raw_final_after_settlement_yen']
                        ?? $heirsByIdx[$no]['final_after_settlement_yen']
                        ?? 0
                );
                if ($heirFinalAfterSettlementYen !== 0) {
                    $heirFinalAfterSettlementKyen = (int) round($heirFinalAfterSettlementYen / 1000);
                }


                if ($heirFinalAfterSettlementYen !== 0) {
                    $pdf->MultiCell(28, 10, number_format($heirFinalAfterSettlementKyen), $wakusen, 'R', 0, 0, $xx, $yy + 104.9);
                }
 
 
 

                /**
                 * ▼ 納付税額（相続人別）
                 *
                 *  - heirs[row_index].payable_tax_yen（円）→ 千円
                 *  - isanbunkatu.blade の「納付税額」行・相続人別欄に対応
                 */
                $heirPayableKyen = 0;
                if (isset($heirsByIdx[$no]['payable_tax_yen'])) {
                    $heirPayableKyen = (int) round(((int) $heirsByIdx[$no]['payable_tax_yen']) / 1000);
                }

                if ($heirPayableKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirPayableKyen), $wakusen, 'R', 0, 0, $xx, $yy + 111.5);
                }


                /**
                 * ▼ 還付税額（相続人別）
                 *
                 *  - heirs[row_index].refund_tax_yen（円）→ 千円
                 *  - isanbunkatu.blade の「還付税額」行・相続人別欄に対応
                 */
                $heirRefundKyen = 0;
                if (isset($heirsByIdx[$no]['refund_tax_yen'])) {
                    $heirRefundKyen = (int) round(((int) $heirsByIdx[$no]['refund_tax_yen']) / 1000);
                }

                if ($heirRefundKyen > 0) {
                    $pdf->MultiCell(28, 10, number_format($heirRefundKyen), $wakusen, 'R', 0, 0, $xx, $yy + 118.1);
                }

                // 合計列の割合 ＝ 納付税額合計 / 課税価格合計 × 100
                if ($heirPayableKyen > 0 && $heirTaxableTotalKyen > 0) {
                    $wari = $heirPayableKyen / $heirTaxableTotalKyen * 100;
                    $pdf->MultiCell(28, 10, number_format($wari, 2) . '％', $wakusen, 'R', 0, 0, $xx, $yy + 124.0);
                }


            }
            
            $xx += 25.0;


        }
    }


 
    private function resolveRelationshipFontSize(string $label): float
    {
        $label = trim($label);
        if ($label === '') {
            return 11.0;
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($label, 'UTF-8')
            : strlen($label);

        return match (true) {
            $length <= 3 => 11.0,
            $length === 4 => 10.5,
            $length === 5 => 10.0,
            $length === 6 => 9.0,
            $length === 7 => 8.5,
            default      => 8.0,
        };
    }

    private function resolveRelationshipCellHeightRatio(string $label): float
    {
        $label = trim($label);
        if ($label === '') {
            return 1.25;
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($label, 'UTF-8')
            : strlen($label);

        return match (true) {
            $length >= 30 => 0.90,
            $length >= 20 => 0.95,
            $length >= 10 => 1.00,
            default       => 1.25,
        };
        
    }



    private function formatYenCell($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return (string)$value;
        }

        $int = (int)$value;
        return number_format($int);
    }



    /**
     * 基礎控除額の算式ラベルを返す
     *
     * 優先順位:
     * 1. payload['basic_deduction_formula_label']
     * 2. summary['basic_deduction_formula_label']
     * 3. summary の内訳（base/per_heir）から組み立て
     */
    private function resolveBasicDeductionFormulaLabel(array $payload, array $summary, int $legalHeirCount): string
    {
        $label = trim((string)(
            $payload['basic_deduction_formula_label']
            ?? $summary['basic_deduction_formula_label']
            ?? ''
        ));

        if ($label !== '') {
            return $label;
        }

        $baseKyen = $summary['basic_deduction_base_kyen']
            ?? $summary['basic_deduction_base_thousand']
            ?? null;

        $perHeirKyen = $summary['basic_deduction_per_heir_kyen']
            ?? $summary['basic_deduction_per_heir_thousand']
            ?? null;

        if (is_numeric($baseKyen) && is_numeric($perHeirKyen) && $legalHeirCount > 0) {
            return number_format((int)$baseKyen) . '千円＋'
                . number_format((int)$perHeirKyen) . '千円× '
                . $legalHeirCount . '人';
        }

        return '';
    }    
    
    
    
}
