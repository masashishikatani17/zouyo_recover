<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use App\Models\FutureGiftRecipient;
use App\Models\ProposalFamilyMember;
use TCPDF;


use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class ZouyosetuzeihikakuPageService implements ZouyoPdfPageInterface
{
    public function render(TCPDF $pdf, array $payload): void
    {
        $dataId = (int)($payload['data_id'] ?? 0);
        

        /*
        \Log::info('Payload for results data: ' . json_encode($payload)); // payloadの内容を確認
        
        
        if ($dataId <= 0) {
            \Log::warning('[ZouyosetuzeihikakuPageService] data_id missing in payload');
            return;
        }
        */
    
        $wakusen = 0;
        $templatePath = resource_path('/views/pdf/08_pr_jikeiretsu.pdf');
        
        $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);

        $pdf->AddPage();
        $pdf->useTemplate($tpl);

        $pdf->SetFont('mspgothic03', '',10);
        $wakusen = 0;
        $x = 255;
        $y = 190;
        $pdf->MultiCell(30, 6, '８ページ', $wakusen, 'R', 0, 0, $x, $y);

        // family テーブルから氏名を取得
        $familyRows = ProposalFamilyMember::query()
            ->where('data_id', $dataId)
            ->orderBy('row_no')
            ->get()
            ->keyBy('row_no');
    
        $header     = $payload['header']  ?? [];
        $donorName  = (string)(
            ($familyRows[1]->name ?? null)
            ?? ($header['customer_name'] ?? '')
        );
    
        // 受贈者情報取得
        $recipients = FutureGiftRecipient::query()
            ->where('data_id', $dataId)
            ->orderBy('recipient_no')
            ->get();

        /*
        if ($recipients->isEmpty()) {
            \Log::info('[ZouyosetuzeihikakuPageService] no FutureGiftRecipient found', ['data_id' => $dataId]);
            return;
        }
        */

        // 被相続人氏名をPDFに描画
        $pdf->SetFont('mspgothic03', '', 11);
        $xx = 62;
        $yy = 25;
        //$pdf->MultiCell(40, 10, $donorName , $wakusen, 'L', 0, 0, $xx, $yy);

        // 計算結果の取得
        // ▼ 対策前/対策後の税額推移（贈与による節税効果）の行ビルダー
        //   ・t は Service 側の projections の添字と 1:1（t=0..20）
        
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
    
        $r = $resultsData ?? [];
        $beforeSummary = $r['before']['summary'] ?? [];
        $after = $r['after']['summary'] ?? [];
        
        $projBefore = $resultsData['projections']['before'] ?? [];
        $projAfter = $resultsData['projections']['after'] ?? [];        

        $baseAge = $header['age'] ?? null; // baseAgeをheaderから取得（もしくはfamilyから）

        // familyRows からも年齢を取得（headerにない場合）
        if ($baseAge === null && isset($familyRows[1])) {
            $baseAge = $familyRows[1]->age ?? null;
        }



        // $baseGiftBefore を設定（ここでは仮に $header['gift_tax_cum_yen'] としていますが、適切なデータを取得してください）
        $baseGiftBefore = (int)(
            $beforeSummary['gift_tax_cum_yen']
            ?? $beforeSummary['gift_tax_total_yen']
            ?? (
                (int)($beforeSummary['total_gift_tax_credits'] ?? 0)
              + (int)($beforeSummary['total_settlement_gift_taxes'] ?? 0)
            )
        );
        
        $sozokuBefore = (int)(
            $before['final_after_settlement_yen']
            ?? $before['total_final_after_settlement']
            ?? $before['sozoku_tax_total']
            ?? 0
        );
        
        $sozokuAfter = (int)(
            $afterSummary['final_after_settlement_yen']
            ?? $afterSummary['total_final_after_settlement']
            ?? $afterSummary['sozoku_tax_total']
            ?? 0
        );




         // 計算結果のフォールバックをセッションからも拾う
         $resultsData = $results ?? [];

        $rows = $this->getSouzokuKazeikakakuRows($dataId, $familyRows, $resultsData, $header);

         
         /*
         if (empty($rows)) {
             \Log::warning('[ZouyosetuzeihikakuPageService] No rows found to display', ['data_id' => $dataId]);
             return;
         }
         */


         
        $buildEffectRow = function (int $t) use ($beforeSummary, $after, $projBefore, $projAfter, $baseAge, $baseGiftBefore) {
            // --- 対策前サマリ ---
            $before = ($t === 0)
                ? $beforeSummary
                : (($projBefore[$t]['summary'] ?? []) ?: []);
        
            // --- 対策後サマリ ---
            $afterSummary = ($t === 0)
                ? $after
                : (($projAfter[$t]['summary'] ?? []) ?: []);
        
            /*
            if (empty($before)) {
                \Log::warning('Before Summary is empty for t=' . $t);
            }
            if (empty($afterSummary)) {
                \Log::warning('After Summary is empty for t=' . $t);
            }
            */
        
            // --- 相続税（精算課税贈与税控除後） ---
            $sozokuBefore = (int)(
                $before['final_after_settlement_yen']
                ?? $before['total_final_after_settlement']
                ?? $before['sozoku_tax_total']
                ?? 0
            );
        
            $sozokuAfter = (int)(
                $afterSummary['final_after_settlement_yen']
                ?? $afterSummary['total_final_after_settlement']
                ?? $afterSummary['sozoku_tax_total']
                ?? 0
            );
        
            // --- 贈与税累計額 ---
            $giftBefore = $baseGiftBefore;
        
            // 対策後の贈与税累計額
            $giftAfter = (int)(
                $afterSummary['gift_tax_cum_yen']
                ?? $afterSummary['calendar_gift_tax_cum_yen']                
                ?? (
                    (int)($afterSummary['total_gift_tax_credits']      ?? 0)
                  + (int)($afterSummary['total_settlement_gift_taxes'] ?? 0)
                )
            );
        
            $totalBefore = $sozokuBefore + $giftBefore;
            $totalAfter  = $sozokuAfter  + $giftAfter;
        
            // 差額（対策後 − 対策前）…単位：円
            $diff = $totalAfter - $totalBefore;
        
            //表示単位　千円
            return [
                'nenji'         => ($t === 0) ? '現時点' : ($t . '年後'),
                'age'           => is_int($baseAge) ? ($baseAge + $t) . '歳' : '—',
                'sozoku_before' => number_format($sozokuBefore / 1000),
                'gift_before'   => number_format($giftBefore / 1000),
                'total_before'  => number_format($totalBefore / 1000),
                'sozoku_after'  => number_format($sozokuAfter / 1000),
                'gift_after'    => number_format($giftAfter / 1000),
                'total_after'   => number_format($totalAfter / 1000),
                'diff'          => number_format($diff / 1000),
            ];
        };
        



        // テーブル描画位置の設定
        $startX     =  0.0;
        $startY     = 42.0;
        $rowHeight  =  6.1;

        $colX = [
            'index'        => 0.0, 
            'year'         => 5.0, 
            'age'          => 32.0, 
            'sozoku_before'=> 50.0, 
            'gift_before'  => 80.0, 
            'total_before' => 111.0, 
            'sozoku_after' => 146.0, 
            'gift_after'   => 178.0, 
            'total_after'  => 209.0, 
            'diff'         => 241.0, 
        ];

        $colWidths = [
            'index'        => 10.0, 
            'year'         => 10.0, 
            'age'          => 20.0, 
            'sozoku_before'=> 30.0, 
            'gift_before'  => 30.0, 
            'total_before' => 30.0, 
            'sozoku_after' => 30.0, 
            'gift_after'   => 30.0, 
            'total_after'  => 30.0, 
            'diff'         => 30.0, 
        ];

         
         $effectRows = [];
         for ($t = 0; $t <= 20; $t++) {
             $effectRows[] = $buildEffectRow($t);
         }


             $xx = $colX['year'];
 
 

            // 対策前後の税額推移（贈与による節税効果）の表示
             for ($i = 0; $i <= 20; $i++) {
                 
                $yy = $startY + $rowHeight * $i;
                 

                if (isset($effectRows[$i])) {

                    // 年齢（baseAgeがnullの場合、"—"を表示）
                    $xx = $colX['age'];
                    $age = is_int($baseAge) ? ($baseAge + $i) . '歳' : '—';
                    $pdf->MultiCell($colWidths['age'], $rowHeight, $age, $wakusen, 'R', 0, 0, $xx, $yy);
                                        
                    $effectRow = $effectRows[$i];
                    $xx = $colX['sozoku_before'];
                    $pdf->MultiCell($colWidths['sozoku_before'], $rowHeight, $this->formatYenCell($effectRow['sozoku_before']), $wakusen, 'R', 0, 0, $xx, $yy);
                    
                    $xx = $colX['gift_before'];
                    $pdf->MultiCell($colWidths['gift_before'], $rowHeight, $this->formatYenCell($effectRow['gift_before']), $wakusen, 'R', 0, 0, $xx, $yy);
                    
                    $xx = $colX['total_before'];
                    $pdf->MultiCell($colWidths['total_before'], $rowHeight, $this->formatYenCell($effectRow['total_before']), $wakusen, 'R', 0, 0, $xx, $yy);
                    
                    $xx = $colX['sozoku_after'];
                    $pdf->MultiCell($colWidths['sozoku_after'], $rowHeight, $this->formatYenCell($effectRow['sozoku_after']), $wakusen, 'R', 0, 0, $xx, $yy);
                    
                    $xx = $colX['gift_after'];
                    $pdf->MultiCell($colWidths['gift_after'], $rowHeight, $this->formatYenCell($effectRow['gift_after']), $wakusen, 'R', 0, 0, $xx, $yy);
                    
                    $xx = $colX['total_after'];
                    $pdf->MultiCell($colWidths['total_after'], $rowHeight, $this->formatYenCell($effectRow['total_after']), $wakusen, 'R', 0, 0, $xx, $yy);
                    
                    $xx = $colX['diff'];
                    $pdf->MultiCell($colWidths['diff'], $rowHeight, $this->formatYenCell($effectRow['diff']), $wakusen, 'R', 0, 0, $xx, $yy);
            
                    
                }
             
            }
    
        
        
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
      * Souzoku Kazei Kakaku Rows を取得する
      * 
      * @param int $dataId
      * @return array
      */
     private function getSouzokuKazeikakakuRows(int $dataId, $familyRows, $resultsData, $header): array {
         // resultsDataをもとにテーブル用データを組み立てる
         $r = $resultsData ?? [];

         // after, before などのセクションを取得
         $after         = $r['after']['summary'] ?? [];
         $afterMeta     = $r['after']['meta'] ?? [];
         $projAfter     = $r['projections']['after'] ?? [];

         $beforeSummary = $r['before']['summary'] ?? [];
         $projBefore    = $r['projections']['before'] ?? [];

         // 被相続人の年齢を取得
         $ageCandidates = [
             $familyRows[1]->age      ?? null,
             $header['age'] ?? null,  // header から年齢を取得
         ];
         $baseAge = null;
         foreach ($ageCandidates as $cand) {
             if ($cand === null) continue;
             $n = (int)preg_replace('/[^\d\-]/', '', (string)$cand);
             if ($n >= 0 && $n <= 130) { $baseAge = $n; break; }
         }

         // 行ビルダー
         $buildRow = function (int $t) use (
             $after,
             $afterMeta,
             $projAfter,
             $beforeSummary,
             $baseAge
         ): array {
             // サマリ部分
             $summary = ($t === 0)
                 ? $after
                 : (($projAfter[$t]['summary'] ?? []) ?: []);

             // t=0 で estate_base_yen が無ければ before.summary から補完
             if ($t === 0 && empty($summary['estate_base_yen']) && !empty($beforeSummary['estate_base_yen'])) {
                 $summary['estate_base_yen'] = $beforeSummary['estate_base_yen'] ?? 0; // 安全なデフォルト値を設定
             }

             // t=0 のときだけ cum_row0 から incl_* を補完
             if ($t === 0) {
                 $hasCal = array_key_exists('incl_calendar_yen', $summary) && $summary['incl_calendar_yen'] !== null;
                 $hasSet = array_key_exists('incl_settlement_yen', $summary) && $summary['incl_settlement_yen'] !== null;
                 $cumRow0 = $afterMeta['cum_row0'] ?? null;
                 if ($cumRow0 && (!$hasCal || !$hasSet)) {
                     $calK = (int)($cumRow0['cal_k'] ?? 0);
                     $setK = (int)($cumRow0['set_k'] ?? 0);
                     if (!$hasCal) {
                         $summary['incl_calendar_yen'] = $calK * 1000;
                     }
                     if (!$hasSet) {
                         $summary['incl_settlement_yen'] = $setK * 1000;
                     }
                     if (!array_key_exists('past_gift_included_total_yen', $summary)) {
                         $summary['past_gift_included_total_yen']
                             = (int)($summary['incl_calendar_yen'] ?? 0)
                             + (int)($summary['incl_settlement_yen'] ?? 0);
                     }
                 }
             }

             // 計算部分
             $base        = (int)($summary['estate_base_yen']        ?? 0);
             $decrCal     = (int)($summary['gift_decr_calendar_yen'] ?? 0);
             $decrSet     = (int)($summary['gift_decr_payment_yen']  ?? 0);
             $estateAfter = (int)($summary['estate_after_yen'] ?? ($base + $decrCal + $decrSet));

             /*
             \Log::info("Calculated estate values for t=$t: base=$base, decrCal=$decrCal, decrSet=$decrSet, estateAfter=$estateAfter");

             \Log::info('Before Summary: ' . json_encode($beforeSummary));
             \Log::info('After Summary: ' . json_encode($after));
             \Log::info('Before補完処理: ' . json_encode($beforeSummary['estate_base_yen'] ?? 'N/A'));
             */

             return [
                 'nenji'        => ($t === 0) ? '現時点' : ($t . '年後'),
                 'age'          => is_int($baseAge) ? ($baseAge + $t) . '歳' : '—',
                 'prop_amount'  => $base >= 0 ? number_format($base / 1000) : '0', // baseを千円単位で表示、0の場合は0にする
                 'gift_decr_cal'=> $decrCal / 1000,
                 'gift_decr_set'=> $decrSet / 1000,
                 'estate_after' => $estateAfter / 1000,
                 'incl_cal'     => (int)($summary['incl_calendar_yen']   ?? 0) / 1000,
                 'incl_set'     => (int)($summary['incl_settlement_yen'] ?? 0) / 1000,
                 'taxable'      => (int)($summary['kazei_price_yen'] ?? 0) / 1000, // 課税価格を千円単位に変更
             ];
         };

         // 行を生成
         $rows = [];
         $rows[] = $buildRow(0);
         for ($t = 1; $t <= 20; $t++) {
             $rows[] = $buildRow($t);
         }

         return $rows;
     }

    
}
