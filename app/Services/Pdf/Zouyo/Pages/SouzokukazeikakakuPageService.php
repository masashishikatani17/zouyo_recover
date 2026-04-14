<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use App\Models\FutureGiftRecipient;
use App\Models\ProposalFamilyMember;
use TCPDF;


use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class SouzokukazeikakakuPageService implements ZouyoPdfPageInterface
{
    public function render(TCPDF $pdf, array $payload): void
    {
        $dataId = (int)($payload['data_id'] ?? 0);
        
        
        /*
        \Log::info('Payload for results data: ' . json_encode($payload)); // payloadの内容を確認
        */
        
        
        if ($dataId <= 0) {
            \Log::warning('[SouzokukazeikakakuPageService] data_id missing in payload');
            return;
        }
    
        $wakusen = 0;
        $templatePath = resource_path('/views/pdf/06_pr_zoyogo.pdf');
        
        $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);

        $pdf->AddPage();
        $pdf->useTemplate($tpl);


        $pdf->SetFont('mspgothic03', '',10);
        $wakusen = 0;
        $x = 255;
        $y = 190;
        $pdf->MultiCell(30, 6, '６ページ', $wakusen, 'R', 0, 0, $x, $y);


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

        if ($recipients->isEmpty()) {
            \Log::info('[SouzokukazeikakakuPageService] no FutureGiftRecipient found', ['data_id' => $dataId]);
            return;
        }

        // 被相続人氏名をPDFに描画
        $pdf->SetFont('mspgothic03', '', 12);
        $xx = 62;
        $yy = 25;
        $pdf->MultiCell(40, 10, $donorName , $wakusen, 'L', 0, 0, $xx, $yy);

        // 計算結果の取得
        
      // 計算結果のフォールバックをセッションからも拾う
      // 計算結果のフォールバックを payload → session → cache の順で拾う
      $resultsData = $payload['resultsData'] ?? [];

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
        /*
        $resultsData = $payload['resultsData'] ?? [
            'after' => ['summary' => ['estate_base_yen' => 0], 'meta' => [], 'projections' => ['after' => []]], // ここを修正
            'before' => ['summary' => [], 'projections' => ['before' => []]],
        ];
        */

//\Log::info('Results Data: ' . json_encode($resultsData));
        
    
        $rows = $this->getSouzokuKazeikakakuRows($dataId, $familyRows, $resultsData, $header);
        

    
        if (empty($rows)) {
            \Log::warning('[SouzokukazeikakakuPageService] No rows found to display', ['data_id' => $dataId]);
            return;
        }
        

        // テーブル描画位置の設定
        $startX     =  0.0;
        $startY     = 53.0;
        $rowHeight  =  6.1;

        $colX = [
            'index'         => 0.0, 
            'year'          => 5.0, 
            'age'           => 30.0, 
            'prop_amount'   => 50.0, 
            'gift_decr_cal' => 80.0, 
            'gift_decr_set' => 111.0, 
            'estate_after'  => 146.0, 
            'incl_cal'      => 178.0, 
            'incl_set'      => 209.0, 
            'taxable'       => 241.0, 
        ];

        $colWidths = [
            'index'         => 10.0, 
            'year'          => 10.0, 
            'age'           => 20.0, 
            'prop_amount'   => 30.0, 
            'gift_decr_cal' => 30.0, 
            'gift_decr_set' => 30.0, 
            'estate_after'  => 30.0, 
            'incl_cal'      => 30.0, 
            'incl_set'      => 30.0, 
            'taxable'       => 30.0, 
        ];

        $pdf->SetFont('mspgothic03', '', 9);

        foreach ($rows as $i => $row) {
            $yy = $startY + $rowHeight * $i;
            if ($yy > 260) {
                \Log::info('[SouzokukazeikakakuPageService] table rows truncated for page height', [
                    'data_id' => $dataId,
                    'row_index' => $i,
                ]);
                break;
            }

            $xx = $colX['year'];
            /*
            $pdf->MultiCell(
                $colWidths['year'],
                $rowHeight,
                (string)($row['nenji'] ?? ''),
                $wakusen,
                'C',
                0,
                0,
                $xx,
                $yy
            );
            */

            // 年齢
            $xx = $colX['age'];
            $pdf->MultiCell($colWidths['age'], $rowHeight, (string)($row['age'] ?? ''), $wakusen, 'R', 0, 0, $xx, $yy);

            // その他のカラム
            $xx = $colX['prop_amount'];
            $pdf->MultiCell($colWidths['prop_amount'], $rowHeight, $this->formatYenCell($row['prop_amount']), $wakusen, 'R', 0, 0, $xx, $yy);

            if ( $i === 0 ){
            } else {
                $xx = $colX['gift_decr_cal'];
                $pdf->MultiCell($colWidths['gift_decr_cal'], $rowHeight, $this->formatYenCell($row['gift_decr_cal']), $wakusen, 'R', 0, 0, $xx, $yy);
    
                $xx = $colX['gift_decr_set'];
                $pdf->MultiCell($colWidths['gift_decr_set'], $rowHeight, $this->formatYenCell($row['gift_decr_set']), $wakusen, 'R', 0, 0, $xx, $yy);
            }
            
            $xx = $colX['estate_after'];
            $pdf->MultiCell($colWidths['estate_after'], $rowHeight, $this->formatYenCell($row['estate_after']), $wakusen, 'R', 0, 0, $xx, $yy);

            $xx = $colX['incl_cal'];
            $pdf->MultiCell($colWidths['incl_cal'], $rowHeight, $this->formatYenCell($row['incl_cal']), $wakusen, 'R', 0, 0, $xx, $yy);

            $xx = $colX['incl_set'];
            $pdf->MultiCell($colWidths['incl_set'], $rowHeight, $this->formatYenCell($row['incl_set']), $wakusen, 'R', 0, 0, $xx, $yy);

            $xx = $colX['taxable'];
            if ( $i === 0 ){
                $pdf->MultiCell($colWidths['taxable'], $rowHeight, $this->formatYenCell($row['estate_after'] + $row['incl_cal'] + $row['incl_set']), $wakusen, 'R', 0, 0, $xx, $yy);
            } else {
                $pdf->MultiCell($colWidths['taxable'], $rowHeight, $this->formatYenCell($row['taxable']), $wakusen, 'R', 0, 0, $xx, $yy);
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
     private function getSouzokuKazeikakakuRows(int $dataId, $familyRows, $resultsData, $header): array
     {
         $r = $resultsData ?? [];

         $after         = $r['after']['summary'] ?? [];
         $afterMeta     = $r['after']['meta'] ?? [];
         $beforeSummary = $r['before']['summary'] ?? [];

         // resultsData の構造差異に備えて複数パスをフォールバック
         $projAfter =
             $r['projections']['after']
             ?? $r['after']['projections']['after']
             ?? $r['after']['projections']
             ?? [];






        /*
         \Log::info('[SouzokukazeikakakuPageService] projections diagnose', [
             'resultsData_keys' => array_keys($r),
             'after_keys'       => array_keys($r['after'] ?? []),
             'has_projections'  => array_key_exists('projections', $r),
             'projAfter_type'   => gettype($projAfter),
             'projAfter_keys'   => is_array($projAfter) ? array_slice(array_keys($projAfter), 0, 10) : [],
             'projAfter_t1'     => $projAfter[1] ?? null,
         ]);
        */ 
        

         $ageCandidates = [
             $familyRows[1]->age ?? null,
             $header['age'] ?? null,
         ];

         $baseAge = null;
         foreach ($ageCandidates as $cand) {
             if ($cand === null) {
                 continue;
             }
             $n = (int) preg_replace('/[^\d\-]/', '', (string) $cand);
             if ($n >= 0 && $n <= 130) {
                 $baseAge = $n;
                 break;
             }
         }



         $buildRow = function (int $t) use (
             $after,
             $afterMeta,
             $projAfter,
             $beforeSummary,
             $baseAge
        ): array {
             $projection = ($t === 0) ? [] : ((array) ($projAfter[$t] ?? $projAfter[(string)$t] ?? []));
             $summary    = ($t === 0) ? $after : (($projection['summary'] ?? []) ?: []);
             $meta       = ($t === 0) ? $afterMeta : (($projection['meta'] ?? []) ?: []);

             // t=0 で estate_base_yen が無ければ before.summary から補完
             if ($t === 0 && empty($summary['estate_base_yen']) && !empty($beforeSummary['estate_base_yen'])) {
                 $summary['estate_base_yen'] = (int) $beforeSummary['estate_base_yen'];
             }

             // この帳票は Calculator / projection が返した SoT をそのまま使う。
             // PDF側で累計補正や年次加算の再構成はしない。
             // ★ PDF仕様：
             //   贈与加算累計額は「1年後」から表示する。
             //   そのため「現時点(t=0)」では incl_cal / incl_set は常に 0 とする。
             $inclCal   = ($t === 0) ? 0 : (int) ($summary['incl_calendar_yen'] ?? 0);
             $inclSet   = ($t === 0) ? 0 : (int) ($summary['incl_settlement_yen'] ?? 0);
             
             
             $inclTotal = (int) ($summary['past_gift_included_total_yen'] ?? ($inclCal + $inclSet));


             $base        = (int) ($summary['estate_base_yen'] ?? 0);
             $decrCal     = (int) ($summary['gift_decr_calendar_yen'] ?? 0);
             $decrSet     = (int) ($summary['gift_decr_payment_yen'] ?? 0);
             $estateAfter = (int) ($summary['estate_after_yen'] ?? ($base + $decrCal + $decrSet));         

             /*
             \Log::info('[SouzokukazeikakakuPageService] row diagnose', [
                 't'              => $t,
                 'inclCal_yen'    => $inclCal,
                 'inclSet_yen'    => $inclSet,
                 'inclTotal_yen'  => $inclTotal,
             ]);
             */

             return [
                 'nenji'         => ($t === 0) ? '現時点' : ($t . '年後'),
                 'age'           => is_int($baseAge) ? ($baseAge + $t) . '歳' : '—',
                 'prop_amount'   => $base >= 0 ? number_format($base / 1000) : '0',
                 'gift_decr_cal' => $decrCal / 1000,
                 'gift_decr_set' => $decrSet / 1000,
                 'estate_after'  => $estateAfter / 1000,
                 'incl_cal'      => $inclCal / 1000,
                 'incl_set'      => $inclSet / 1000,
                 'taxable'       => (int) ($summary['kazei_price_yen'] ?? 0) / 1000,
             ];
         };

         $rows = [];
         $rows[] = $buildRow(0);
         for ($t = 1; $t <= 20; $t++) {
             $rows[] = $buildRow($t);
         }

         return $rows;
     }





}
