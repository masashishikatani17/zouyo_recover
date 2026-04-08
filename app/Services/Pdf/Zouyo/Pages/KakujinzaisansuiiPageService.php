<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;
use App\Models\FutureGiftRecipient;
use App\Models\FutureGiftPlanEntry;
use App\Models\ProposalFamilyMember;
use App\Models\PastGiftCalendarEntry;
use App\Models\PastGiftSettlementEntry;

use App\Models\ProposalHeader;         // ★ 追加
use Illuminate\Support\Arr;            // ★ 追加

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

/**
 * 5: 各人別贈与額および贈与税 ページ
 *
 * 仕様：
 *  - DB上で「これからの贈与」の入力がある受贈者ごとに 1 ページずつ出力
 *  - ページレイアウトは 05_pr_kakujin_zouyo.pdf を背景テンプレとして利用
 *  - 今の段階ではテンプレのみ（数値の印字は後続ステップで追加）
 */
class KakujinzaisansuiiPageService implements ZouyoPdfPageInterface
{
    public function render(TCPDF $pdf, array $payload): void
    {

    /*
    // ログで呼び出しを確認
    \Log::debug('[KakujinzaisansuiiPageService] render start', ['payload_data_id' => $payload['data_id'] ?? null]);
    */

    // ★ data_id は payload を正とする（固定しない）
    $dataId = (int)($payload['data_id'] ?? 0);

    if ($dataId <= 0) {
        \Log::warning('[KakujinzaisansuiiPageService] data_id missing in payload');
        return;
    }

    $wakusen = 0;

    // 精算課税側のプリセット（future_zouyo.blade の $prefillFuture 相当）
    $prefillFuture = (array)($payload['prefillFuture'] ?? []);


    // ============================================================
    // ★ 追加：prefillFamily / prefillHeader の取得
    // ============================================================

    // ▼ prefillFamily（property_thousand / cash_thousand を含む）
    $prefillFamily = [];
    $familyDB = ProposalFamilyMember::query()
        ->where('data_id', $dataId)
        ->orderBy('row_no')
        ->get();

    foreach ($familyDB as $row) {
        $i = (int)$row->row_no;
        $prefillFamily[$i] = [
            'property' => (int)($row->property_thousand ?? 0),   // 千円
            'cash'     => (int)($row->cash_thousand      ?? 0),   // 千円
        ];
    }

    // ▼ prefillHeader（per：利回り）
    $prefillHeader = [];
    $ph = ProposalHeader::query()
        ->where('data_id', $dataId)
        ->first();

    if ($ph) {
        $prefillHeader['per'] = (float)$ph->after_tax_yield_percent;  // 例：2.3
    }


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

    // 対象受贈者（2..10）
    // ★ FutureGiftRecipient が欠けていてもページが出るよう、familyRows（ProposalFamilyMember）を主ソースにする
    $recipientsByNo = FutureGiftRecipient::query()
        ->where('data_id', $dataId)
        ->whereBetween('recipient_no', [2, 10])
        ->get()
        ->keyBy('recipient_no');

    $targets = [];
    for ($no = 2; $no <= 10; $no++) {
        $nameFromFamily = is_object($familyRows->get($no)) ? (string)($familyRows[$no]->name ?? '') : '';
        $nameFromFamily = trim($nameFromFamily);
        $r = $recipientsByNo->get($no);
        $nameFromRecipient = $r ? trim((string)($r->recipient_name ?? '')) : '';
        $name = $nameFromFamily !== '' ? $nameFromFamily : $nameFromRecipient;

        // 名前が完全に空なら対象外（必要ならここを外して「全員出す」でもOK）
        if ($name === '') {
            continue;
        }

        $relationshipCode = (int)($familyRows[$no]->relationship_code ?? 0);
        $targets[] = [
            'recipient_no'        => $no,
            'recipient_name'      => $name,
            'relationship_code'   => $relationshipCode,
        ];
    }


        $relationships = config('relationships');

    
    // テンプレートPDFのパス
    $templatePath = resource_path('/views/pdf/09_pr_gosuii.pdf');

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
        
        // 受贈者のページ描画
        foreach ($targets as $info) {
            // ここで recipient_no を取り出す
            $recipientNo   = $info['recipient_no'];
            $recipientName = (string)($info['recipient_name'] ?? '');
            $relCode       = $info['relationship_code'] ?? null;
            
            //dd($info);

            // ProposalFamilyMember の tokurei_zouyo（0/1）を取得（なければ 0＝一般税率）
            $tokureiFlag   = (int)($familyRows[$recipientNo]->tokurei_zouyo ?? 0);

            // 続柄の取得（家族情報から）
            $relationLabel = (string)($familyRows[$recipientNo]->relation ?? '');

            //ヘッダとフッタの削除
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);

            $pdf->SetMargins( 0, 0, 0 );
            $pdf->SetAutoPageBreak(true,0);

            // 受贈者のページ描画（氏名と続柄を一緒に印字）
            $pdf->AddPage(); // 必ずページを追加する
            $pdf->useTemplate($tplId);


            $pageNo = $pageNo + 1;
            $pdf->SetFont('mspgothic03', '',10);
            $wakusen = 0;
            $x = 255;
            $y = 195;
            $pdf->MultiCell(30, 5, '(9 - ' . $pageNo . 'ページ)', $wakusen, 'R', 0, 0, $x, $y);
    

            $pdf->SetFont('mspgothic03', '', 10);
            $pdf->SetTextColor(0, 0, 0);

            // ヘッダ部：受贈者/続柄
            $x = 28;
            $y = 25;
            // 受贈者名と続柄を印字（続柄は氏名の右側に配置）
            $pdf->MultiCell(29, 10, $recipientName, $wakusen, 'C', 0, 0, $x, $y);
            
            $relLabel = $relCode !== null && array_key_exists($relCode, $relationships)
                ? $relationships[$relCode]
                : '';


            $relFontSize = $this->resolveRelationshipFontSize($relLabel);
            $relCellHeightRatio = $this->resolveRelationshipCellHeightRatio($relLabel);

            $pdf->SetFont('mspgothic03', '', $relFontSize);
            $pdf->setCellHeightRatio($relCellHeightRatio);
            $pdf->MultiCell(29, 10, $relLabel, $wakusen, 'C', 0, 0, $x + 32, $y);
            $pdf->setCellHeightRatio(1.25);
            $pdf->SetFont('mspgothic03', '', 10);


            $colX = [
                'index'         =>  0.0,  // 回数
                'year'          => 10.0,  // 贈与年
                'age'           => 35.0,  // 年齢

                'assetTotal'      =>  50.0,
                
                'giftCalReceived' =>  77.0,
                'giftNetBefore'   => 102.0,
                
                'giftSetReceived' => 128.0,
                'giftCalTax'      => 153.0,
                
                'inheritNet'      => 178.0,
                'inheritTax'      => 204.0,
                
                'investGain'      => 230.0,
                
                'assetAfter'      => 258.0,
    
            ];
    

        // ▼ 表形式で印字（これからの贈与：future_zouyo.blade の明細相当）
        $rowStartY = 58.0;
        $rowHeight =  6.1;
    

    // 計算結果のフォールバックをセッションからも拾う
    $resultsData = $results ?? [];
    if (empty($resultsData)) {
    // まず従来のセッション直格納を確認（互換）
      $resultsData = Session::get('zouyo.results', []);
    }
    if (empty($resultsData)) {
      // 推奨：セッションに入っているキーで Cache から取得
      $key = Session::get('zouyo.results_key');
      if ($key) {
          $resultsData = Cache::get($key, []);
      }
    }
    
    //dd($resultsData);
    
    
  // 返却結果（input.blade 側で Cache/Session から解決済みの $resultsData を最優先）
  $r = $resultsData ?? [];

    if ($recipientNo === 6){
        //dd($r);
    }


    
  $benefIndex = (string)$recipientNo;

//dd($benefIndex); // ここで $benefIndex の中身を確認

//dd($r['after']['persons'][$benefIndex]); // ここで persons 配列内の情報を確認
//dd($r['after']['persons'][$benefIndex]['timeline']); // timeline が存在するか確認


  $personTimeline = \Illuminate\Support\Arr::get($r, 'after.persons.' . $benefIndex . '.timeline', []);
  
foreach ($personTimeline as $index => $timelineData) {
    if ($index === 2 && $recipientNo === 6){
        //dd($index, $timelineData); // インデックスとそのデータを確認
    }

}

    
        // t=0..20 固定
        $age0 = null;
        for ($i = 0; $i <= 20; $i++) {
            $y = $rowStartY + $rowHeight * $i;

            // 回数
            //$pdf->SetXY($colX['index'], $y);
            //$pdf->MultiCell(15, 6, (string)$i, $wakusen, 'R', 0, 0, $x, $y);
            
            // その他の項目（贈与年、年齢など）もここに追加します
            // ...

                  // Service 側から受け取る 1人分のタイムライン行（受贈者No）
                  $pRow = $personTimeline[$i] ?? [];
                  
                  //dd($pRow);

                  $assetTotal          = Round( (int)($pRow['asset_total_yen']              ?? 0) / 1000 , 0);
                  
                  // 暦年贈与：受領額
                  $giftCalReceived     = Round( (int)($pRow['gift_calendar_received_yen']   ?? 0) / 1000 , 0);
                  // 暦年贈与：贈与税（※この列は「税」を出す列。純増(gift_net_before_yen)を入れない）
                  $giftCalTax          = Round( (int)($pRow['gift_calendar_tax_yen']        ?? 0) / 1000 , 0);

                  $giftSetReceived     = Round( (int)($pRow['gift_settlement_received_yen'] ?? 0) / 1000 , 0);
                  // 精算課税：贈与税
                  $giftSetTax          = Round( (int)($pRow['gift_settlement_tax_yen']       ?? 0) / 1000 , 0);

                  $inheritNet          = Round( (int)($pRow['inherit_net_yen']              ?? 0) / 1000 , 0);
                  $inheritTax          = Round( (int)($pRow['inherit_tax_yen']              ?? 0) / 1000 , 0);
                  
                  $investGain          = Round( (int)($pRow['investment_gain_yen']          ?? 0) / 1000 , 0);

                  $assetAfter          = Round( (int)($pRow['asset_after_yen']              ?? 0) / 1000 , 0);


            // ★このページは timeline(t=0..20) が SoT のため $plan は使わない
            // 贈与年を表示したい場合は固定ロジックで作る（t=0は空欄、t>=1は2024+t）
            // ※現状は年の印字自体がコメントアウトなので、変数も不要
            // $giftYear = ($i === 0) ? null : (2024 + $i);
            // $x = $colX['year'];
            // if ($giftYear !== null) $pdf->MultiCell(10, 6, (string)$giftYear, $wakusen, 'R', 0, 0, $x, $y);

            //$pdf->MultiCell(10, 6, (string)$giftYear, $wakusen, 'R', 0, 0, $x, $y);
    
    
            // 年齢（基準年＝贈与年の1月1日時点）
            // 年齢（基準年＝(2025+i)年の1月1日時点）
            $baseYear = 2025 + $i;
            $birth    = $birthByRow[$recipientNo] ?? ['year' => null, 'month' => null, 'day' => null];
            if ($age0 === null) {
                $age0 = $this->calcAgeAtJan1($birth['year'], $birth['month'], $birth['day'], 2025);
            }
            $age = $age0 !== null ? ($age0 + $i) : null;

            if ($age !== null) {
                $x = $colX['age'];
                $pdf->MultiCell(10, 6, (string)$age . '歳', $wakusen, 'R', 0, 0, $x, $y);
            }
    
            // ★TCPDFは int 0 を空扱いするケースがあるので string に寄せる
            $fmt = static fn($v) => $v === null ? '' : number_format((int)$v);
    
            $x = $colX['assetTotal'];
            $pdf->MultiCell(20, 6, $fmt($assetTotal), $wakusen, 'R', 0, 0, $x, $y);
            
            
          if ($i !== 0){
    
            $x = $colX['giftCalReceived'];
            $pdf->MultiCell(20, 6, $fmt($giftCalReceived), $wakusen, 'R', 0, 0, $x, $y);

            $x = $colX['giftNetBefore'];
            // ★暦年贈与税（税はマイナス表示）
            $pdf->MultiCell(20, 6, $fmt(-$giftCalTax), $wakusen, 'R', 0, 0, $x, $y);


            
            $x = $colX['giftSetReceived'];
            $pdf->MultiCell(20, 6, $fmt($giftSetReceived), $wakusen, 'R', 0, 0, $x, $y);

            $x = $colX['giftCalTax'];
            // ★精算課税贈与税（税はマイナス表示）
            $pdf->MultiCell(20, 6, $fmt(-$giftSetTax), $wakusen, 'R', 0, 0, $x, $y);
    
    
          }


            $x = $colX['inheritNet'];
            $pdf->MultiCell(20, 6, $fmt($inheritNet), $wakusen, 'R', 0, 0, $x, $y);
    
            $x = $colX['inheritTax'];
            $pdf->MultiCell(20, 6, $fmt(-$inheritTax), $wakusen, 'R', 0, 0, $x, $y);
    
            
            
          if ($i !== 0){

            $x = $colX['investGain'];
            $pdf->MultiCell(20, 6, $fmt($investGain), $wakusen, 'R', 0, 0, $x, $y);
    
          }            
            
            
            $x = $colX['assetAfter'];
            $pdf->MultiCell(20, 6, $fmt($assetAfter), $wakusen, 'R', 0, 0, $x, $y);
    
        }
    
        /*
        \Log::info('[KakujinzaisansuiiPageService] rendered page for recipient', [
            'data_id'       => $dataId,
            'recipient_no'  => $recipientNo,
            'recipientName' => $recipientName,
            'plan_rows'     => $plans->count(),
        ]);
        */




        $comString = [];
        
        $strStar = "★";
        
        $comString[0] = "(" . $strStar . ")";
        
        $comString[1] = "資産運用による増加額とは対策前の所有財産のうち金融資産である";

        // ★ ここを prefillFamily[1]['cash'] に差し替える（千円 → カンマ付）
        $prop1 = (int)($prefillFamily[$recipientNo]['cash'] ?? 0);

        $comString[2] = number_format($prop1);

        $comString[3] = "千円と贈与による財産の額から贈与税を控除した額を運用した場合の運用益です。";
        
        $comString[4] = "ここでは運用利回り(税引後)を";

        // ★ per を使用（例：2.3）
        $comString[5] = number_format((float)($prefillHeader['per'] ?? 0), 1);

        $comString[6] = "%として計算しています。";
        
                $comString[11] = $comString[1] . $comString[2] . $comString[3];
                $comString[12] = $comString[4] . $comString[5] . $comString[6];


                $comString[21] = $comString[11] . $comString[12];

        $pdf->SetFont('mspmincho02', '', 10);

        $x =  20;
        $y = 186;
        $pdf->MultiCell( 10, 15, $comString[0] , $wakusen, 'L', 0, 0, $x, $y);
        //$pdf->MultiCell(300, 6, $comString[11] , $wakusen, 'L', 0, 0, $x, $y);
        $x =  27;
        $pdf->MultiCell(250, 5, $comString[11] , $wakusen, 'L', 0, 0, $x, $y);

        $x =  27;
        $y = 190;
        $pdf->MultiCell(300, 5, $comString[12] , $wakusen, 'L', 0, 0, $x, $y);



    }   


    
}


    private function resolveRelationshipFontSize(string $label): float
    {
        $label = trim($label);
        if ($label === '') {
            return 10.0;
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($label, 'UTF-8')
            : strlen($label);

        return match (true) {
            $length <= 3 => 10.0,
            $length === 4 => 9.5,
            $length === 5 => 9.0,
            $length === 6 => 8.0,
            $length === 7 => 7.5,
            default      => 7.0,
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


}
