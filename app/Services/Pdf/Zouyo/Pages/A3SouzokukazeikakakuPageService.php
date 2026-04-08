<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use App\Models\FutureGiftRecipient;
use App\Models\ProposalFamilyMember;
use TCPDF;


use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class A3SouzokukazeikakakuPageService implements ZouyoPdfPageInterface
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
        $templatePath = resource_path('/views/pdf/A3_06_pr_zoyogo.pdf');
        
        $pdf->setSourceFile($templatePath);
        $tpl          = $pdf->importPage(1);
        $templateSize = $pdf->getTemplateSize($tpl);
        $orientation  = ($templateSize['width'] >= $templateSize['height']) ? 'L' : 'P';
 
        $pdf->AddPage($orientation, [$templateSize['width'], $templateSize['height']]);
        $pdf->useTemplate(
            $tpl,
            0,
            0,
            $templateSize['width'],
            $templateSize['height'],
            true
        );

        // A3版は1ページ構成
        $pdf->SetFont('mspgothic03', '', 10);
        $wakusen = 0;
        $x = 375;
        $y = 277;
        $pdf->MultiCell(30, 6, '(6ページ)', $wakusen, 'R', 0, 0, $x, $y);


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
        $pdf->SetFont('mspgothic03', '', 11);
        $xx = 33.0;
        $yy = 22.3;
        $pdf->MultiCell(46, 8, $donorName, $wakusen, 'L', 0, 0, $xx, $yy);


        // 計算結果の取得
        
        $resultsData = $this->resolveResultsData($payload);

//\Log::info('Results Data: ' . json_encode($resultsData));
        
    
        $rows = $this->getSouzokuKazeikakakuRows($dataId, $familyRows, $resultsData, $header);
        

    
        if (empty($rows)) {
            \Log::warning('[SouzokukazeikakakuPageService] No rows found to display', ['data_id' => $dataId]);
            return;
        }
        

        // A3上表は「縦に項目」「横に年次」
        // テンプレートPDFの罫線に合わせて、現時点〜20年後を左から右へ描画する
        $startX    = 75.6;   // 現時点列の左端
        $colWidth  = 15.9;   // 1年分の列幅
        $rowHeight = 5.6;

        $rowY = [
            'age'           => 36.8,
            'prop_amount'   => 42.4,
            'gift_decr_cal' => 48.0,
            'gift_decr_set' => 53.5,
            'estate_after'  => 59.0,
            'incl_cal'      => 64.6,
            'incl_set'      => 70.2,
            'taxable'       => 75.8,
        ];

        $pdf->SetFont('mspgothic03', '', 8.5);        

        foreach ($rows as $i => $row) {
            if ($i > 20) {
    
                \Log::info('[SouzokukazeikakakuPageService] table rows truncated for page height', [
                    'data_id' => $dataId,
                    'column_index' => $i,
                ]);

                break;

            }

            $xx = $startX + ($colWidth * $i);

            // 年齢
            $this->drawA3TableCell(
                $pdf,
                (string)($row['age'] ?? ''),
                $xx + 2.0,
                $rowY['age'],
                $colWidth,
                $rowHeight,
                'C'
             );
             
            // 所有財産の額(贈与前)
            $this->drawA3TableCell(
                $pdf,
                $this->formatYenCell($row['prop_amount'] ?? ''),
                $xx,
                $rowY['prop_amount'],
                $colWidth,
                $rowHeight,
                'R'
            );

            // 贈与による財産の減少（現時点は空欄）
            $this->drawA3TableCell(
                $pdf,
                ($i === 0) ? '  －' : $this->formatYenCell($row['gift_decr_cal'] ?? ''),
                $xx,
                $rowY['gift_decr_cal'],
                $colWidth,
                $rowHeight,
                ($i === 0) ? 'C' : 'R'
            );
            $this->drawA3TableCell(
                $pdf,
                ($i === 0) ? '  －' : $this->formatYenCell($row['gift_decr_set'] ?? ''),
                $xx,
                $rowY['gift_decr_set'],
                $colWidth,
                $rowHeight,
                ($i === 0) ? 'C' : 'R'
            );

            // 相続財産の額(贈与後)
            $this->drawA3TableCell(
                $pdf,
                $this->formatYenCell($row['estate_after'] ?? ''),
                $xx,
                $rowY['estate_after'],
                $colWidth,
                $rowHeight,
                'R'
            );

            // 贈与加算累計額
            $this->drawA3TableCell(
                $pdf,
                $this->formatYenCell($row['incl_cal'] ?? ''),
                $xx,
                $rowY['incl_cal'],
                $colWidth,
                $rowHeight,
                'R'
            );
            $this->drawA3TableCell(
                $pdf,
                $this->formatYenCell($row['incl_set'] ?? ''),
                $xx,
                $rowY['incl_set'],
                $colWidth,
                $rowHeight,
                'R'
            );

            // 課税価格（現時点だけ A4 版と同様に estate_after + incl_cal + incl_set を表示）
            $taxableValue = ($i === 0)
                ? ((int)($row['estate_after'] ?? 0) + (int)($row['incl_cal'] ?? 0) + (int)($row['incl_set'] ?? 0))
                : (int)($row['taxable'] ?? 0);

            $this->drawA3TableCell(
                $pdf,
                $this->formatYenCell($taxableValue),
                $xx,
                $rowY['taxable'],
                $colWidth,
                $rowHeight,
                'R'
            );


        }
    




        /**
         * 真ん中の表：相続税額の推移
         * - A3KakujinSouzokuPageService の「合計欄」に出している値だけを
         *   現時点〜20年後まで横並びで表示する
         * - この帳票は「贈与後」ページなので after / projections.after を使用する
         */
        $middleRows = $this->getSouzokuZeigakuTrendRows($dataId, $familyRows, $resultsData, $header);

        if (empty($middleRows)) {
            \Log::warning('[SouzokukazeikakakuPageService] No middle trend rows found', [
                'data_id' => $dataId,
            ]);
            return;
        }

        $middleStartX    = 75.6;   // 現時点列の左端
        $middleColWidth  = 15.9;   // 1年分の列幅
        $middleRowHeight = 5.4;

        $middleRowY = [
            'age'                    => 105.3,
            'taxable_price'          => 110.9,
            'basic_deduction'        => 116.5,
            'taxable_estate'         => 122.1,
            'legal_share'            => 127.8,
            'sozoku_tax_total'       => 133.4,
            'anbun_ratio'            => 139.0,
            'sanzutsu_tax_total'     => 144.7,
            'two_wari'               => 150.3,
            'gift_tax_credit'        => 155.9,
            'spouse_relief'          => 161.3,
            'other_credit'           => 166.7,
            'credits_total'          => 172.3,
            'sashihiki_tax'          => 178.0,
            'settlement_gift_tax'    => 183.6,
            'final_after_settlement' => 189.2,
            'payable_tax'            => 194.9,
            'refund_tax'             => 200.5,
        ];

        $pdf->SetFont('mspgothic03', '', 8.2);

        foreach ($middleRows as $i => $row) {
            if ($i > 20) {
                \Log::info('[SouzokukazeikakakuPageService] middle table columns truncated', [
                    'data_id' => $dataId,
                    'column_index' => $i,
                ]);
                break;
            }

            $xx = $middleStartX + ($middleColWidth * $i);

            // 年齢
            $this->drawA3TableCell(
                $pdf,
                (string)($row['age'] ?? ''),
                $xx + 2.0,
                $middleRowY['age'],
                $middleColWidth,
                $middleRowHeight,
                'C'
            );

            // 課税価格
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['taxable_price'] ?? null), $xx, $middleRowY['taxable_price'], $middleColWidth, $middleRowHeight, 'R');

            // 基礎控除額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['basic_deduction'] ?? null), $xx, $middleRowY['basic_deduction'], $middleColWidth, $middleRowHeight, 'R');

            // 課税遺産総額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['taxable_estate'] ?? null), $xx, $middleRowY['taxable_estate'], $middleColWidth, $middleRowHeight, 'R');

            // 法定相続分（合計欄は空欄）
            $this->drawA3TableCell(
                $pdf,
                (string)($row['legal_share'] ?? ''),
                $xx,
                $middleRowY['legal_share'],
                $middleColWidth,
                $middleRowHeight,
                'C'
            );

            // 相続税の総額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['sozoku_tax_total'] ?? null), $xx, $middleRowY['sozoku_tax_total'], $middleColWidth, $middleRowHeight, 'R');

            // あん分割合（合計欄は常に 1.0000）
            $this->drawA3TableCell(
                $pdf,
                (string)($row['anbun_ratio'] ?? ''),
                $xx,
                $middleRowY['anbun_ratio'],
                $middleColWidth,
                $middleRowHeight,
                'R'
            );

            // 算出税額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['sanzutsu_tax_total'] ?? null), $xx, $middleRowY['sanzutsu_tax_total'], $middleColWidth, $middleRowHeight, 'R');

            // 2割加算
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['two_wari'] ?? null), $xx, $middleRowY['two_wari'], $middleColWidth, $middleRowHeight, 'R');

            // 暦年課税分の贈与税額控除額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['gift_tax_credit'] ?? null), $xx, $middleRowY['gift_tax_credit'], $middleColWidth, $middleRowHeight, 'R');

            // 配偶者の税額軽減額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['spouse_relief'] ?? null), $xx, $middleRowY['spouse_relief'], $middleColWidth, $middleRowHeight, 'R');

            // その他の税額控除額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['other_credit'] ?? null), $xx, $middleRowY['other_credit'], $middleColWidth, $middleRowHeight, 'R');

            // 控除税額合計
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['credits_total'] ?? null), $xx, $middleRowY['credits_total'], $middleColWidth, $middleRowHeight, 'R');

            // 差引税額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['sashihiki_tax'] ?? null), $xx, $middleRowY['sashihiki_tax'], $middleColWidth, $middleRowHeight, 'R');

            // 相続時精算課税分の贈与税額控除額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['settlement_gift_tax'] ?? null), $xx, $middleRowY['settlement_gift_tax'], $middleColWidth, $middleRowHeight, 'R');

            // 小計
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['final_after_settlement'] ?? null), $xx, $middleRowY['final_after_settlement'], $middleColWidth, $middleRowHeight, 'R');

            // 納付税額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['payable_tax'] ?? null), $xx, $middleRowY['payable_tax'], $middleColWidth, $middleRowHeight, 'R');

            // 還付税額
            $this->drawA3TableCell($pdf, $this->formatTrendAmountCell($row['refund_tax'] ?? null), $xx, $middleRowY['refund_tax'], $middleColWidth, $middleRowHeight, 'R');
        }

    

        // =========================
        // 下表：贈与による減税効果
        // =========================
        $effectRows = $this->getZouyoGenzeiKokaTrendRows($dataId, $familyRows, $resultsData, $header);

        if (empty($effectRows)) {
            \Log::warning('[SouzokukazeikakakuPageService] No bottom effect rows found', [
                'data_id' => $dataId,
            ]);
        } else {
            $bottomStartX    = 75.6;
            $bottomColWidth  = 15.9;
            $bottomRowHeight = 5.4;

            $bottomRowY = [
                'age'           => 229.6,
                'sozoku_before' => 235.2,
                'gift_before'   => 240.7,
                'total_before'  => 246.5,
                'sozoku_after'  => 252.0,
                'gift_after'    => 257.5,
                'total_after'   => 263.0,
                'diff'          => 268.5,
            ];

            $pdf->SetFont('mspgothic03', '', 8.2);

            foreach ($effectRows as $i => $row) {
                if ($i > 20) {
                    \Log::info('[SouzokukazeikakakuPageService] bottom table columns truncated', [
                        'data_id' => $dataId,
                        'column_index' => $i,
                    ]);
                    break;
                }

                $xx = $bottomStartX + ($bottomColWidth * $i);

                //年齢
                $this->drawA3TableCell($pdf, (string)($row['age'] ?? ''), $xx + 2.0, $bottomRowY['age'], $bottomColWidth, $bottomRowHeight, 'C');
                
                //対策前
                //相続税額
                $this->drawA3TableCell($pdf, $this->formatEffectTrendAmountCell($row['sozoku_before'] ?? null), $xx, $bottomRowY['sozoku_before'], $bottomColWidth, $bottomRowHeight, 'R');
                
                //贈与税額
                $this->drawA3TableCell($pdf, $this->formatEffectTrendAmountCell($row['gift_before'] ?? null), $xx, $bottomRowY['gift_before'], $bottomColWidth, $bottomRowHeight, 'R');
                
                //税額合計
                $this->drawA3TableCell($pdf, $this->formatEffectTrendAmountCell($row['total_before'] ?? null), $xx, $bottomRowY['total_before'], $bottomColWidth, $bottomRowHeight, 'R');
                
                //対策後
                //相続税額
                $this->drawA3TableCell($pdf, $this->formatEffectTrendAmountCell($row['sozoku_after'] ?? null), $xx, $bottomRowY['sozoku_after'], $bottomColWidth, $bottomRowHeight, 'R');
                
                //贈与税額
                $this->drawA3TableCell($pdf, $this->formatEffectTrendAmountCell($row['gift_after'] ?? null), $xx, $bottomRowY['gift_after'], $bottomColWidth, $bottomRowHeight, 'R');
                
                //税額合計
                $this->drawA3TableCell($pdf, $this->formatEffectTrendAmountCell($row['total_after'] ?? null), $xx, $bottomRowY['total_after'], $bottomColWidth, $bottomRowHeight, 'R');
                
                
                //減税効果　対策後-対策前
                $this->drawA3TableCell($pdf, $this->formatEffectTrendAmountCell($row['diff'] ?? null), $xx, $bottomRowY['diff'], $bottomColWidth, $bottomRowHeight, 'R');
            
                
                
            }
        }
    }


    private function drawA3TableCell(
        TCPDF $pdf,
        string $text,
        float $x,
        float $y,
        float $w,
        float $h,
        string $align = 'R'
    ): void {
        $pdf->MultiCell($w, $h, $text, 0, $align, 0, 0, $x, $y, true, 0, false, true, $h, 'M');
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



    private function formatTrendAmountCell($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return (string)$value;
        }

        $int = (int) round((float) $value);

        // A3KakujinSouzokuPageService の合計欄と同様、0 は空欄にする
        if ($int === 0) {
            return '';
        }

        return number_format($int);
    }
    
    

    private function formatEffectTrendAmountCell($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return (string)$value;
        }

        return number_format((int) round((float) $value));
    }



    private function yenToKyen($value): int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return 0;
        }

        return (int) round(((int) $value) / 1000);
    }

    private function pickFirstNumericValue(array $source, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = data_get($source, $key);
            if ($value === null || $value === '') {
                continue;
            }
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function sumHeirsYenByKeys(array $heirsByIdx, array $keys): int
    {
        $sum = 0;

        for ($no = 2; $no <= 10; $no++) {
            $row = is_array($heirsByIdx[$no] ?? null) ? $heirsByIdx[$no] : [];
            $value = $this->pickFirstNumericValue($row, $keys);
            if ($value !== null) {
                $sum += $value;
            }
        }

        return $sum;
    }

    private function buildHeirsByIdx(array $bundle): array
    {
        $heirsByIdx = [];
        $heirs = $bundle['heirs'] ?? [];

        if (!is_iterable($heirs)) {
            return $heirsByIdx;
        }

        foreach ($heirs as $heir) {
            if (!is_array($heir)) {
                continue;
            }
            if (!isset($heir['row_index'])) {
                continue;
            }
            $heirsByIdx[(int) $heir['row_index']] = $heir;
        }

        return $heirsByIdx;
    }

    private function resolveAfterTrendBundle(array $resultsData, int $t): array
    {
        if ($t === 0) {
            return is_array($resultsData['after'] ?? null) ? $resultsData['after'] : [];
        }

        $projAfter =
            $resultsData['projections']['after']
            ?? $resultsData['after']['projections']['after']
            ?? $resultsData['after']['projections']
            ?? [];

        return is_array($projAfter[$t] ?? null) ? $projAfter[$t] : [];
    }

    private function getSouzokuZeigakuTrendRows(int $dataId, $familyRows, $resultsData, $header): array
    {
        $r = is_array($resultsData) ? $resultsData : [];

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

        $rows = [];

        for ($t = 0; $t <= 20; $t++) {
            $bundle     = $this->resolveAfterTrendBundle($r, $t);
            $summary    = is_array($bundle['summary'] ?? null) ? $bundle['summary'] : [];
            $heirsByIdx = $this->buildHeirsByIdx($bundle);

            if ($t === 0 && empty($summary)) {
                \Log::warning('[SouzokukazeikakakuPageService] middle summary is empty', [
                    'data_id' => $dataId,
                    'results_top_keys' => array_keys($r),
                ]);
            }

            $taxablePriceYen = $this->pickFirstNumericValue($summary, [
                'kazei_price_yen',
                'taxable_price_yen',
            ]);
            if ($taxablePriceYen === null) {
                $estateAfterYen = $this->pickFirstNumericValue($summary, ['estate_after_yen']) ?? 0;
                $inclCalYen     = $this->pickFirstNumericValue($summary, ['incl_calendar_yen']) ?? 0;
                $inclSetYen     = $this->pickFirstNumericValue($summary, ['incl_settlement_yen']) ?? 0;
                $taxablePriceYen = $estateAfterYen + $inclCalYen + $inclSetYen;
            }

            $sozokuTaxTotalYen = $this->pickFirstNumericValue($summary, [
                'sozoku_tax_total',
                'sozoku_tax_total_yen',
            ]) ?? 0;

            $sanzutsuTaxTotalYen = $this->pickFirstNumericValue($summary, [
                'total_sanzutsu_tax_yen',
                'sanzutsu_tax_total_yen',
            ]);
            if ($sanzutsuTaxTotalYen === null) {
                $sanzutsuTaxTotalYen = $this->sumHeirsYenByKeys($heirsByIdx, ['sanzutsu_tax_yen']);
            }

            $twoWariYen = $this->pickFirstNumericValue($summary, [
                'total_two_wari_kasan_yen',
                'two_wari_kasan_total_yen',
                'total_2wari_kasan_yen',
            ]);
            if ($twoWariYen === null) {
                $twoWariYen = 0;
                for ($no = 2; $no <= 10; $no++) {
                    $finalTaxYen    = (int) ($heirsByIdx[$no]['final_tax_yen'] ?? 0);
                    $sanzutsuTaxYen = (int) ($heirsByIdx[$no]['sanzutsu_tax_yen'] ?? 0);
                    $diff = $finalTaxYen - $sanzutsuTaxYen;
                    if ($diff > 0) {
                        $twoWariYen += $diff;
                    }
                }
            }

            $giftTaxCreditYen = $this->pickFirstNumericValue($summary, [
                'total_gift_tax_credits',
                'total_gift_tax_credits_yen',
            ]);
            if ($giftTaxCreditYen === null) {
                $giftTaxCreditYen = $this->sumHeirsYenByKeys($heirsByIdx, ['gift_tax_credit_calendar_yen']);
            }

            $spouseReliefYen = $this->pickFirstNumericValue($summary, [
                'total_spouse_relief',
                'total_spouse_relief_yen',
            ]);
            if ($spouseReliefYen === null) {
                $spouseReliefYen = $this->sumHeirsYenByKeys($heirsByIdx, ['spouse_relief_yen']);
            }

            $otherCreditYen = $this->pickFirstNumericValue($summary, [
                'total_other_credits',
                'total_other_credits_yen',
            ]);
            if ($otherCreditYen === null) {
                $otherCreditYen = $this->sumHeirsYenByKeys($heirsByIdx, ['other_credit_yen']);
            }

            $sashihikiTaxYen = $this->pickFirstNumericValue($summary, [
                'total_sashihiki_tax',
                'total_sashihiki_tax_yen',
            ]);
            if ($sashihikiTaxYen === null) {
                $sashihikiTaxYen = $this->sumHeirsYenByKeys($heirsByIdx, ['sashihiki_tax_yen']);
            }


            /**
             * ▼ 相続時精算課税分の贈与税額控除額（No16）
             *  - 各人欄と同じく heirs[*].settlement_gift_tax_yen の合計を表示する
             *  - summary.total_settlement_gift_taxes は appliedSetCredit 合計で意味が異なるため使わない
             */
            $settlementGiftTaxYen = $this->sumHeirsYenByKeys($heirsByIdx, ['settlement_gift_tax_yen']);



            $finalAfterSettlementYen = $this->pickFirstNumericValue($summary, [
                'total_final_after_settlement',
                'total_final_after_settlement_yen',
            ]);
            if ($finalAfterSettlementYen === null) {
                $finalAfterSettlementYen = $this->sumHeirsYenByKeys($heirsByIdx, ['final_after_settlement_yen']);
            }

            $payableTaxYen = $this->pickFirstNumericValue($summary, [
                'total_payable_tax_yen',
                'payable_tax_total_yen',
            ]);
            if ($payableTaxYen === null) {
                $payableTaxYen = $this->sumHeirsYenByKeys($heirsByIdx, ['payable_tax_yen']);
            }

            $refundTaxYen = $this->pickFirstNumericValue($summary, [
                'total_refund_tax_yen',
                'refund_tax_total_yen',
            ]);
            if ($refundTaxYen === null) {
                $refundTaxYen = $this->sumHeirsYenByKeys($heirsByIdx, ['refund_tax_yen']);
            }

            $giftTaxCreditKyen = $this->yenToKyen($giftTaxCreditYen);
            $spouseReliefKyen  = $this->yenToKyen($spouseReliefYen);
            $otherCreditKyen   = $this->yenToKyen($otherCreditYen);

            $rows[] = [
                'nenji'                  => ($t === 0) ? '現時点' : ($t . '年後'),
                'age'                    => is_int($baseAge) ? ($baseAge + $t) . '歳' : '—',
                'taxable_price'          => $this->yenToKyen($taxablePriceYen),
                'basic_deduction'        => $this->yenToKyen($this->pickFirstNumericValue($summary, ['basic_deduction', 'basic_deduction_yen']) ?? 0),
                'taxable_estate'         => $this->yenToKyen($this->pickFirstNumericValue($summary, ['taxable_estate', 'taxable_estate_yen']) ?? 0),
                'legal_share'            => '',
                'sozoku_tax_total'       => $this->yenToKyen($sozokuTaxTotalYen),
                'anbun_ratio'            => '1.0000',
                'sanzutsu_tax_total'     => $this->yenToKyen($sanzutsuTaxTotalYen),
                'two_wari'               => $this->yenToKyen($twoWariYen),
                'gift_tax_credit'        => $giftTaxCreditKyen,
                'spouse_relief'          => $spouseReliefKyen,
                'other_credit'           => $otherCreditKyen,
                'credits_total'          => $giftTaxCreditKyen + $spouseReliefKyen + $otherCreditKyen,
                'sashihiki_tax'          => $this->yenToKyen($sashihikiTaxYen),
                'settlement_gift_tax'    => $this->yenToKyen($settlementGiftTaxYen),
                'final_after_settlement' => $this->yenToKyen($finalAfterSettlementYen),
                'payable_tax'            => $this->yenToKyen($payableTaxYen),
                'refund_tax'             => $this->yenToKyen($refundTaxYen),
            ];
        }

        return $rows;
    }



    private function resolveResultsData(array $payload): array
    {
        $candidates = [];

        if (isset($payload['resultsData']) && is_array($payload['resultsData'])) {
            $candidates[] = $payload['resultsData'];
        }
        if (isset($payload['results']) && is_array($payload['results'])) {
            $candidates[] = $payload['results'];
        }
        if (isset($payload['zouyo_results']) && is_array($payload['zouyo_results'])) {
            $candidates[] = $payload['zouyo_results'];
        }
        if (isset($payload['calc_results']) && is_array($payload['calc_results'])) {
            $candidates[] = $payload['calc_results'];
        }

        $sessionResults = Session::get('zouyo.results', []);
        if (is_array($sessionResults) && !empty($sessionResults)) {
            $candidates[] = $sessionResults;
        }

        $key = Session::get('zouyo.results_key');
        if ($key) {
            $cacheResults = Cache::get($key, []);
            if (is_array($cacheResults) && !empty($cacheResults)) {
                $candidates[] = $cacheResults;
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeResultsData($candidate);
            if ($this->hasUsableResultsData($normalized)) {
                return $normalized;
            }
        }

        \Log::warning('[SouzokukazeikakakuPageService] resultsData could not be resolved', [
            'payload_keys' => array_keys($payload),
        ]);

        return [];
    }

    private function normalizeResultsData(array $data): array
    {
        $paths = [
            $data,
            $data['resultsData'] ?? null,
            $data['results'] ?? null,
            $data['zouyo_results'] ?? null,
            $data['calc_results'] ?? null,
            $data['data'] ?? null,
            $data['zouyo'] ?? null,
        ];

        foreach ($paths as $candidate) {
            if (!is_array($candidate) || empty($candidate)) {
                continue;
            }

            if (
                isset($candidate['after']) ||
                isset($candidate['before']) ||
                isset($candidate['projections'])
            ) {
                return $candidate;
            }
        }

        return $data;
    }

     /**
      * Souzoku Kazei Kakaku Rows を取得する
      * 
      * @param int $dataId
      * @return array
      */
     private function getSouzokuKazeikakakuRows(int $dataId, $familyRows, $resultsData, $header): array
     {

         $r = is_array($resultsData) ? $this->normalizeResultsData($resultsData) : [];

         $this->logResultsShape($dataId, $r);

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
             $baseAge,
             $dataId,
             $r
        ): array {
             $projection = ($t === 0) ? [] : ($projAfter[$t] ?? []);
             $summary    = ($t === 0) ? $after : (($projection['summary'] ?? []) ?: []);
             $meta       = ($t === 0) ? $afterMeta : (($projection['meta'] ?? []) ?: []);


             if ($t === 0 && empty($summary)) {
                 \Log::warning('[SouzokukazeikakakuPageService] t=0 summary is empty', [
                     'data_id' => $dataId,
                     'results_top_keys' => array_keys($r),
                 ]);
             }

             // t=0 で estate_base_yen が無ければ before.summary から補完
             if ($t === 0 && empty($summary['estate_base_yen']) && !empty($beforeSummary['estate_base_yen'])) {
                 $summary['estate_base_yen'] = (int) $beforeSummary['estate_base_yen'];
             }

             // この帳票は Calculator / projection が返した SoT をそのまま使う。
             // PDF側で累計補正や年次加算の再構成はしない。
             $inclCal   = (int) ($summary['incl_calendar_yen'] ?? 0);
             $inclSet   = (int) ($summary['incl_settlement_yen'] ?? 0);
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
                 'prop_amount'   => $base >= 0 ? round($base / 1000) : 0,
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
     
     

    private function hasUsableResultsData(array $data): bool
    {
        return
            !empty($data['after']['summary'] ?? []) ||
            !empty($data['before']['summary'] ?? []) ||
            !empty($data['projections']['after'] ?? []) ||
            !empty($data['after']['projections']['after'] ?? []) ||
            !empty($data['after']['projections'] ?? []);
    }

    
    private function logResultsShape(int $dataId, array $r): void
    {
        
        /*
        \Log::info('[SouzokukazeikakakuPageService] results shape', [
            'data_id' => $dataId,
            'top_keys' => array_keys($r),
            'after_keys' => array_keys($r['after'] ?? []),
            'before_keys' => array_keys($r['before'] ?? []),
            'has_after_summary' => !empty($r['after']['summary'] ?? []),
            'has_before_summary' => !empty($r['before']['summary'] ?? []),
            'has_root_proj_after' => !empty($r['projections']['after'] ?? []),
            'has_after_proj_after' => !empty($r['after']['projections']['after'] ?? []),
            'has_after_projections' => !empty($r['after']['projections'] ?? []),
        ]);
        */
    }
    
    

    private function resolveBeforeTrendBundle(array $resultsData, int $t): array
    {
        if ($t === 0) {
            return is_array($resultsData['before'] ?? null) ? $resultsData['before'] : [];
        }

        $projBefore =
            $resultsData['projections']['before']
            ?? $resultsData['before']['projections']['before']
            ?? $resultsData['before']['projections']
            ?? [];

        return is_array($projBefore[$t] ?? null) ? $projBefore[$t] : [];
    }

  

    private function getZouyoGenzeiKokaTrendRows(int $dataId, $familyRows, $resultsData, $header): array
    {
        $r = is_array($resultsData) ? $this->normalizeResultsData($resultsData) : [];

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

        $beforeRootSummary = is_array($r['before']['summary'] ?? null) ? $r['before']['summary'] : [];

        $baseGiftBeforeYen = $this->pickFirstNumericValue($beforeRootSummary, [
            'gift_tax_cum_yen',
            'gift_tax_total_yen',
            'gift_tax_total',
        ]);
        if ($baseGiftBeforeYen === null) {
            $baseGiftBeforeYen =
                (int) ($beforeRootSummary['total_gift_tax_credits'] ?? 0)
                + (int) ($beforeRootSummary['total_settlement_gift_taxes'] ?? 0);
        }

        $rows = [];

        for ($t = 0; $t <= 20; $t++) {
            $beforeBundle  = $this->resolveBeforeTrendBundle($r, $t);
            $afterBundle   = $this->resolveAfterTrendBundle($r, $t);
            $beforeSummary = is_array($beforeBundle['summary'] ?? null) ? $beforeBundle['summary'] : [];
            $afterSummary  = is_array($afterBundle['summary'] ?? null) ? $afterBundle['summary'] : [];

            if ($t === 0 && (empty($beforeSummary) || empty($afterSummary))) {
                \Log::warning('[SouzokukazeikakakuPageService] bottom summary is empty', [
                    'data_id' => $dataId,
                    'has_before_summary' => !empty($beforeSummary),
                    'has_after_summary' => !empty($afterSummary),
                ]);
            }

            $sozokuBeforeYen = $this->pickFirstNumericValue($beforeSummary, [
                'final_after_settlement_yen',
                'total_final_after_settlement',
                'total_final_after_settlement_yen',
                'sozoku_tax_total',
                'sozoku_tax_total_yen',
            ]) ?? 0;

            $sozokuAfterYen = $this->pickFirstNumericValue($afterSummary, [
                'final_after_settlement_yen',
                'total_final_after_settlement',
                'total_final_after_settlement_yen',
                'sozoku_tax_total',
                'sozoku_tax_total_yen',
            ]) ?? 0;

            $giftAfterYen = $this->pickFirstNumericValue($afterSummary, [
                'calendar_gift_tax_cum_yen',
                'gift_tax_cum_yen',
                'gift_tax_total_yen',
                'gift_tax_total',
            ]);
            if ($giftAfterYen === null) {
                $giftAfterYen =
                    (int) ($afterSummary['total_gift_tax_credits'] ?? 0)
                    + (int) ($afterSummary['total_settlement_gift_taxes'] ?? 0);
            }

            $giftBeforeYen  = (int) $baseGiftBeforeYen;
            $totalBeforeYen = $sozokuBeforeYen + $giftBeforeYen;
            $totalAfterYen  = $sozokuAfterYen + (int) $giftAfterYen;
            $diffYen        = $totalAfterYen - $totalBeforeYen;

            $rows[] = [
                'nenji'         => ($t === 0) ? '現時点' : ($t . '年後'),
                'age'           => is_int($baseAge) ? ($baseAge + $t) . '歳' : '—',
                'sozoku_before' => $this->yenToKyen($sozokuBeforeYen),
                'gift_before'   => $this->yenToKyen($giftBeforeYen),
                'total_before'  => $this->yenToKyen($totalBeforeYen),
                'sozoku_after'  => $this->yenToKyen($sozokuAfterYen),
                'gift_after'    => $this->yenToKyen($giftAfterYen),
                'total_after'   => $this->yenToKyen($totalAfterYen),
                'diff'          => $this->yenToKyen($diffYen),
            ];
        }

        return $rows;
    }


}
