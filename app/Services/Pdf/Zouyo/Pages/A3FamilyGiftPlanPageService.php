<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Models\FutureGiftPlanEntry;
use App\Models\FutureGiftRecipient;
use App\Models\PastGiftCalendarEntry;
use App\Models\PastGiftSettlementEntry;
use App\Models\ProposalHeader;
use App\Models\ProposalFamilyMember;
use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use App\Services\Pdf\Zouyo\Pages\Support\A3InheritanceTaxTableDataBuilder;
use Illuminate\Support\Facades\Schema;
use TCPDF;

/**
 * 4: 家族構成、所有財産など
 */
class A3FamilyGiftPlanPageService implements ZouyoPdfPageInterface
{
    public function render(TCPDF $pdf, array $payload): void
    {

        $dataId = (int)($payload['data_id'] ?? 0);
        $assetInputMode = $this->resolveAssetInputMode($payload, $dataId);

        // テンプレートPDFのパス
        $templateFile = $assetInputMode === 'combined'
            ? 'A3_03_pr_kazokukosei_sonota.pdf'
            : 'A3_03_pr_kazokukosei.pdf';
        $templatePath = resource_path('/views/pdf/' . $templateFile);

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Family template not found: {$templatePath}");
        }

        $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);

        // ページ追加
        $pdf->AddPage();
        $pdf->useTemplate($tpl);
        $size = $pdf->getTemplateSize($tpl);

        $wakusen = 0;

        // payload から取得
        $header = $payload['header'] ?? [];
        $family = $payload['family'] ?? [];

        // ▼ 家族表の描画
        $startY    = 54.35;
        $rowHeight = 5.78;


        // 新しい家族構成表テンプレートの罫線位置に合わせた座標
        $colX = [
            'name'         => 53.6,
            'gender'       => 82.9,
            'rel'          => 94.4,
            'yousi'        => 115.8,
            'souzoku'      => 140.1,
            'civil_share'  => 168.5,
            'houtei_share' => 184.7,            
            'birth_year'   => 201.8,
            'birth_month'  => 211.6,
            'birth_day'    => 219.3,
            'age'          => 229.6,
            'cash'         => 244.6,
            'prop'         => 280.4,
            'ksum'         => 316.6,
        ];

        $colW = [
            'name'         => 28.0,
            'gender'       => 10.2,
            'rel'          => 20.5,
            'yousi'        => 23.0,
            'souzoku'      => 26.7,
            'civil_share'  => 15.5,
            'houtei_share' => 15.5,            
            'birth_year'   => 12.0,
            'birth_month'  => 9.4,
            'birth_day'    => 9.4,
            'age'          => 12.0,
            'cash'         => 33.8,
            'prop'         => 34.0,
            'ksum'         => 34.0,
        ];


        // 「金融資産を分けずに入力する」用テンプレート
        if ($assetInputMode === 'combined') {

                $colX = [
                    'name'         => 50.0,
                    'gender'       => 85.0,
                    'rel'          => 96.0,
                    'yousi'        => 120.5,
                    'souzoku'      => 148.5,
                    'civil_share'  => 181.5,
                    'houtei_share' => 197.7,            
                    'birth_year'   => 217.8,
                    'birth_month'  => 227.6,
                    'birth_day'    => 235.3,
                    'age'          => 249.0,
                    'cash'         => 250.0,
                    'prop'         => 265.0,
                    'ksum'         => 280.0,
                ];


        }

        /**
         * 家族構成表の印字位置微調整
         * - 現テンプレートでは全体に少し左上へ寄っているため、
         *   表全体を右下へ寄せる
         * - 今後さらに微調整する場合はこの2値だけを触ればよい
         */
        $familyTableOffsetX = 15.5;
        $familyTableOffsetY =  4.2;

        // 「金融資産を分けずに入力する」用テンプレートは
        // 通常版より家族表の印字基準が少し右寄り。
        if ($assetInputMode === 'combined') {
            $familyTableOffsetX = 22.5;
            $familyTableOffsetY =  4.2;
        }

        foreach ($colX as $key => $value) {
            $colX[$key] = $value + $familyTableOffsetX;
        }
        $startY += $familyTableOffsetY;


        $relationships = config('relationships');

        $pdf->SetFont('mspgothic03', '', 10);

        // ページ番号
        $pageLabelW   = 30;
        $pageLabelH   = 6;
        $rightMargin  = 20;
        $bottomMargin = 15;

        $x = max(0, $size['width'] - $pageLabelW - $rightMargin);
        $y = max(0, $size['height'] - $pageLabelH - $bottomMargin);

        $pdf->MultiCell(
            $pageLabelW,
            $pageLabelH,
            '３ページ',
            $wakusen,
            'R',
            0,
            0,
            $x,
            $y
        );

        $pdf->SetFont('mspgothic03', '', 9);
        $pdf->SetTextColor(0, 0, 0);

        // 家族構成、所有財産
        for ($i = 1; $i <= 10; $i++) {
            $row = $family[$i] ?? null;
            if (!$row) {
                continue;
            }


            // 仕様：
            // 氏名が空欄の行は、続柄を含めて各項目を空欄にする
            $name = trim((string)($row['name'] ?? ''));
            $hasName = $name !== '';


            $x = $colX['name'];
            $y = $startY + ($i - 1) * $rowHeight;
            $pdf->MultiCell($colW['name'], 10, $name, $wakusen, 'L', 0, 0, $x, $y);


            if ($hasName) {
                $x = $colX['gender'];
                $pdf->MultiCell($colW['gender'], 10, (string)($row['gender'] ?? ''), $wakusen, 'C', 0, 0, $x, $y);
                
            }


            if ($hasName) {
                $x = $colX['rel'];
                $relCode = $row['relationship_code'] ?? null;
                $relLabel = $relCode !== null && array_key_exists($relCode, $relationships)
                    ? $relationships[$relCode]
                    : '';
                $relFontSize = $this->resolveRelationshipFontSize($relLabel);
                $pdf->SetFont('mspgothic03', '', $relFontSize);
                $pdf->MultiCell($colW['rel'], 10, $relLabel, $wakusen, 'L', 0, 0, $x, $y);
                $pdf->SetFont('mspgothic03', '', 9);
             }
             

            if ($hasName) {
                $x = $colX['yousi'];
                $pdf->MultiCell($colW['yousi'], 10, (string)($row['yousi'] ?? ''), $wakusen, 'L', 0, 0, $x, $y);
            }


            if ($hasName) {
                $x = $colX['souzoku'];
                $pdf->MultiCell($colW['souzoku'], 10, (string)($row['souzokunin'] ?? ''), $wakusen, 'L', 0, 0, $x, $y);
            }



            // 民法上の法定相続割合（FamilyPageService と同様）
            if ($hasName && (($row['civil_share_bunsi'] ?? null) !== null)) {
                $x = $colX['civil_share'];
                $pdf->MultiCell(
                    $colW['civil_share'],
                    10,
                    (string)($row['civil_share_bunsi'] ?? '') . '/' . (string)($row['civil_share_bunbo'] ?? ''),
                    $wakusen,
                    'C',
                    0,
                    0,
                    $x,
                    $y
                );
            }

            // 税法上の法定相続割合
            if ($hasName && (($row['bunsi'] ?? null) !== null)) {
                $x = $colX['houtei_share'];
                $pdf->MultiCell(
                    $colW['houtei_share'],
                    10,
                    (string)($row['bunsi'] ?? '') . '/' . (string)($row['bunbo'] ?? ''),
                    $wakusen,
                    'C',
                    0,
                    0,
                    $x,
                    $y
                );
            }


            if ($hasName && (($row['birth_year'] ?? null) !== null)) {
                $x = $colX['birth_year'];
                $pdf->MultiCell($colW['birth_year'], 10, (string)($row['birth_year'] ?? '') . '年', $wakusen, 'R', 0, 0, $x, $y);
            }


            if ($hasName && (($row['birth_month'] ?? null) !== null)) {
                $x = $colX['birth_month'];
                $pdf->MultiCell($colW['birth_month'], 10, (string)($row['birth_month'] ?? '') . '月', $wakusen, 'R', 0, 0, $x, $y);
            }


            if ($hasName && (($row['birth_day'] ?? null) !== null)) {
                $x = $colX['birth_day'];
                $pdf->MultiCell($colW['birth_day'], 10, (string)($row['birth_day'] ?? '') . '日', $wakusen, 'R', 0, 0, $x, $y);
            }

            $age = $row['age'] ?? null;
            if ($hasName && $age !== null) {
                $x = $colX['age'];
                $pdf->MultiCell($colW['age'], 10, (string)$age . '歳', $wakusen, 'R', 0, 0, $x, $y);
            }

            $cash = $row['cash'] ?? null;
            if ($hasName && $assetInputMode !== 'combined' && $cash !== null) {
                $x = $colX['cash'];
                $pdf->MultiCell($colW['cash'], 10, number_format((int)$cash) . ' 千円', $wakusen, 'R', 0, 0, $x, $y);
            }

            $propertyTotal = $row['property'] ?? null;
            $prop = $assetInputMode === 'combined'
                ? $propertyTotal
                : (($row['property'] ?? 0) - ($row['cash'] ?? 0));
            if ($hasName && $prop !== null) {
                $x = $colX['prop'];
                $pdf->MultiCell($colW['prop'], 10, number_format((int)$prop) . ' 千円', $wakusen, 'R', 0, 0, $x, $y);
            }

            $ksum = $propertyTotal;
            if ($hasName && $assetInputMode !== 'combined' && $ksum !== null) {
                $x = $colX['ksum'];
                $pdf->MultiCell($colW['ksum'], 10, number_format((int)$ksum) . ' 千円', $wakusen, 'R', 0, 0, $x, $y);
            }
        }

        $pdf->SetFont('mspgothic03', '', 9);

        // 合計行
        $totalCash = $header['cash_110'] ?? null;
        $totalProperty = $header['property_110'] ?? null;
        $totalProp = $assetInputMode === 'combined'
            ? $totalProperty
            : (($header['property_110'] ?? 0) - ($header['cash_110'] ?? 0));
        $totaksum  = $totalProperty;


        if ($totalProp !== null || $totalCash !== null || $totaksum !== null) {
            $y = $startY + 10 * $rowHeight;

            // sonota テンプレートでは合計行だけ少し上に補正しないと
            // 「合計」ラベルと金額が重なりやすい
            $combinedTotalOffsetY = $assetInputMode === 'combined' ? -0.6 : 0.0;

            if ($assetInputMode !== 'combined' && $totalCash !== null) {
                $x = $colX['cash'];
                $pdf->MultiCell($colW['cash'], 10, number_format((int)$totalCash) . ' 千円', $wakusen, 'R', 0, 0, $x, $y + $combinedTotalOffsetY);
             }
 
             if ($totalProp !== null) {
                 $x = $colX['prop'];
                $pdf->MultiCell($colW['prop'], 10, number_format((int)$totalProp) . ' 千円', $wakusen, 'R', 0, 0, $x, $y + $combinedTotalOffsetY);
             }
 
             if ($assetInputMode !== 'combined' && $totaksum !== null) {
                 $x = $colX['ksum'];
                $pdf->MultiCell($colW['ksum'], 10, number_format((int)$totaksum) . ' 千円', $wakusen, 'R', 0, 0, $x, $y + $combinedTotalOffsetY);
             }
         }



        

        // ▼ STEP3 削除予定
        //    「各人別贈与プラン」は A3KakuzoyoPlanPageService へ移設する。
        //    この呼び出しは、A3_04 ページをページ配列へ組み込んだ後に削除する。
        //    現段階ではまだ動作を維持するため残しておく。
        //$this->renderEachRecipientGiftPlanTable($pdf, $payload, $family);
        
        // 下段の「各人別贈与プラン」は新ページへ移動したため、このページでは描画しない        



        //各相続人の相続税額
        $tableBuilder = new A3InheritanceTaxTableDataBuilder();
        $inheritanceTaxTableData = $tableBuilder->build($payload);
        $this->renderInheritanceTaxTable($pdf, $inheritanceTaxTableData);
 

    }




    //各相続人の相続税額
    private function renderInheritanceTaxTable(TCPDF $pdf, array $tableData): void
    {

        $yMap = $this->inheritanceTaxTableYMap();
        $totalX = 145.0;
        $totalW = 19.0;
        $heirStartX = 169.0;
        $heirW = 18.5;
        $heirStep = 25.1;

        $wakusen = 0;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('mspgothic03', '', 10);
        

        // 相続人ヘッダ（氏名・続柄）
        $this->renderInheritanceTaxHeaderRows(
            $pdf,
            $tableData,
            $heirStartX,
            $heirW,
            $heirStep
        );        

        
        //相続税の基礎控除
        $formulaLabel = trim((string)($tableData['basic_deduction_formula_label'] ?? ''));
        if ($formulaLabel !== '') {
            $pdf->SetFont('mspgothic03', '', 8.5);
            $pdf->MultiCell(
                108.0,
                5.0,
                $formulaLabel,
                $wakusen,
                'R',
                0,
                0,
                22.0,
                ($yMap['basic_deduction']['y'] ?? 181.2) + 0.4
            );
            $pdf->SetFont('mspgothic03', '', 10);
        }

        foreach ($yMap as $rowKey => $meta) {
            $this->renderInheritanceTaxCell(
                $pdf,
                $totalX,
                (float)$meta['y'],
                $totalW,
                $tableData['total'][$rowKey] ?? null,
                (string)($meta['align'] ?? 'R')
            );
        }

        foreach (range(2, 10) as $index => $recipientNo) {
            $heir = $tableData['heirs'][$recipientNo] ?? [];
            if (!($heir['has_name'] ?? false)) {
                continue;
            }

            $x = $heirStartX + ($heirStep * $index);
            foreach ($yMap as $rowKey => $meta) {
                $this->renderInheritanceTaxCell(
                    $pdf,
                    $x,
                    (float)$meta['y'],
                    $heirW,
                    $heir[$rowKey] ?? null,
                    (string)($meta['align'] ?? 'R')
                );
            }
        }
    }

    private function renderInheritanceTaxHeaderRows(
        TCPDF $pdf,
        array $tableData,
        float $heirStartX,
        float $heirW,
        float $heirStep
    ): void {
        // 「相続人」見出しの下にある2段
        $nameY = 140.6;
        $relationshipY = 147.8;
        $wakusen = 0;

        foreach (range(2, 10) as $index => $recipientNo) {
            $heir = $tableData['heirs'][$recipientNo] ?? [];
            if (!($heir['has_name'] ?? false)) {
                continue;
            }

            $x = $heirStartX + ($heirStep * $index);

            $name = trim((string)($heir['name'] ?? ''));
            if ($name !== '') {
                $pdf->SetFont('mspgothic03', '', 8.5);
                $pdf->MultiCell(
                    $heirW,
                    5.2,
                    $name,
                    $wakusen,
                    'C',
                    0,
                    0,
                    $x,
                    $nameY
                );
            }

            $relationship = trim((string)($heir['relationship'] ?? ''));
            if ($relationship !== '') {
                $relFontSize = $this->resolveRelationshipFontSize($relationship);
                $pdf->SetFont('mspgothic03', '', $relFontSize);
                $pdf->setCellHeightRatio(1.0);
                $pdf->MultiCell(
                    $heirW,
                    5.0,
                    $relationship,
                    $wakusen,
                    'C',
                    0,
                    0,
                    $x,
                    $relationshipY
                );
                $pdf->setCellHeightRatio(1.25);
            }
        }

        $pdf->SetFont('mspgothic03', '', 10);
    }


    private function inheritanceTaxTableYMap(): array
    {
        return [
            // 下段表は「相続人」見出しの下に
            // 1行目: 氏名
            // 2行目: 続柄
            // が入るため、数値本体は従来より約2段下へ寄せる
            'property_total'         => ['y' => 153.5, 'align' => 'R'], // ①
            'lifetime_gift'          => ['y' => 160.2, 'align' => 'R'], // ②
            'taxable_total'          => ['y' => 166.0, 'align' => 'R'], // ③
            'basic_deduction'        => ['y' => 172.2, 'align' => 'R'], // ④
            'taxable_estate'         => ['y' => 179.0, 'align' => 'R'], // ⑤
            'law_share'              => ['y' => 185.0, 'align' => 'C'], // ⑥
            'legal_tax'              => ['y' => 191.0, 'align' => 'R'], // ⑦
            'anbun_ratio'            => ['y' => 197.0, 'align' => 'R'], // ⑧
            'sanzutsu_tax'           => ['y' => 204.0, 'align' => 'R'], // ⑨
            'twowari'                => ['y' => 210.0, 'align' => 'R'], // ⑩
            'calendar_gift_credit'   => ['y' => 216.0, 'align' => 'R'], // ⑪
            'spouse_relief'          => ['y' => 222.0, 'align' => 'R'], // ⑫
            'other_credit'           => ['y' => 228.0, 'align' => 'R'], // ⑬
            'credits_total'          => ['y' => 234.0, 'align' => 'R'], // ⑭
            'sashihiki_tax'          => ['y' => 241.0, 'align' => 'R'], // ⑮
            'settlement_gift_credit' => ['y' => 247.0, 'align' => 'R'], // ⑯
            'payable_tax'            => ['y' => 253.0, 'align' => 'R'], // ⑰
            'refund_tax'             => ['y' => 260.0, 'align' => 'R'], // ⑱
            'ratio'                  => ['y' => 266.0, 'align' => 'R'],
        ];
    }

    private function renderInheritanceTaxCell(
        TCPDF $pdf,
        float $x,
        float $y,
        float $w,
        $value,
        string $align = 'R',
        float $h = 5.5,
        ?float $fontSize = null
    ): void {
        $text = $this->formatInheritanceTaxCellValue($value);
        if ($text === '') {
            return;
        }

        if ($fontSize !== null) {
            $pdf->SetFont('mspgothic03', '', $fontSize);
        }

        $pdf->MultiCell($w, $h, $text, 0, $align, 0, 0, $x, $y);

        if ($fontSize !== null) {
            $pdf->SetFont('mspgothic03', '', 10);
        }
    }

    private function formatInheritanceTaxCellValue($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value)) {
            return $value === 0 ? '' : number_format($value);
        }

        if (is_float($value)) {
            if (abs($value) < 0.000001) {
                return '';
            }

            if (floor($value) === $value) {
                return number_format((int)round($value));
            }

            return (string)$value;
        }

        if (is_numeric($value)) {
            $numeric = (float)$value;
            if (abs($numeric) < 0.000001) {
                return '';
            }

            if (floor($numeric) === $numeric) {
                return number_format((int)round($numeric));
            }

            return (string)$value;
        }

        return trim((string)$value);
    }

    // ============================================================
    // STEP3 削除対象ここから
    // 「各人別贈与プラン」専用ロジック
    // - A3KakuzoyoPlanPageService へ移設済み
    // - A3FamilyGiftPlanPageService では、下段に「各人別相続税額」を
    //   描画する段階でこのブロックを削除する
    // ============================================================
    /**
     * 家族表の下に「各人別贈与プラン」を描画する。
     *  - 縦軸：受贈者 9 人（recipient_no 2-10）
     *  - 横軸：～2021 / 2022 / 2023 / 2024 / 2025-2044
     *  - 各セル：上段=暦年贈与額、下段=精算課税による贈与額（千円）
     */
    private function renderEachRecipientGiftPlanTable(TCPDF $pdf, array $payload, array $family): void
    {
        $dataId = (int)($payload['data_id'] ?? 0);
        if ($dataId <= 0) {
            return;
        }

        $targets = $this->buildGiftPlanTargets($dataId, $family);
        if ($targets === []) {
            return;
        }


        // ★ 相続開始年を基準に、過年度3年＋1年目〜20年目の年軸を動的に作る
        $deathYear = $this->resolveDisplayDeathYear($payload, $dataId);


        $recipientNos = array_keys($targets);
        $yearKeys     = $this->giftPlanYearKeys($deathYear);
        $matrix       = $this->initGiftPlanMatrix($recipientNos, $yearKeys);

        // ▼ 過年度：暦年贈与
        $pastCalendarRows = PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->whereIn('recipient_no', $recipientNos)
            ->whereNotNull('gift_year')
            ->get(['recipient_no', 'gift_year', 'amount_thousand']);

        foreach ($pastCalendarRows as $row) {
            $recipientNo = (int)($row->recipient_no ?? 0);
            $bucket = $this->resolvePastGiftBucket($deathYear, $this->toIntValue($row->gift_year));

            if ($recipientNo <= 0 || $bucket === null || !isset($matrix[$recipientNo][$bucket])) {
                continue;
            }

            $matrix[$recipientNo][$bucket]['calendar'] += $this->toIntValue($row->amount_thousand);
        }

        // ▼ 過年度：精算課税
        $pastSettlementRows = PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->whereIn('recipient_no', $recipientNos)
            ->whereNotNull('gift_year')
            ->get(['recipient_no', 'gift_year', 'amount_thousand']);

        foreach ($pastSettlementRows as $row) {
            $recipientNo = (int)($row->recipient_no ?? 0);
            $bucket = $this->resolvePastGiftBucket($deathYear, $this->toIntValue($row->gift_year));
            if ($recipientNo <= 0 || $bucket === null || !isset($matrix[$recipientNo][$bucket])) {
                continue;
            }

            $matrix[$recipientNo][$bucket]['settlement'] += $this->toIntValue($row->amount_thousand);
        }

        // ▼ 将来分：2025-2044
        $futurePlanRows = FutureGiftPlanEntry::query()
            ->where('data_id', $dataId)
            ->whereIn('recipient_no', $recipientNos)
            ->orderBy('recipient_no')
            ->orderBy('row_no')
            ->get([
                'recipient_no',
                'row_no',
                'gift_year',
                'calendar_amount_thousand',
                'settlement_amount_thousand',
            ]);

        foreach ($futurePlanRows as $row) {
            $recipientNo = (int)($row->recipient_no ?? 0);

            $giftYear = $this->resolveFutureGiftYear(
                $deathYear,                
                $row->gift_year !== null ? $this->toIntValue($row->gift_year) : null,
                $row->row_no !== null ? $this->toIntValue($row->row_no) : null
            );

            if ($recipientNo <= 0 || $giftYear === null) {
                continue;
            }

            $bucket = (string)$giftYear;
            if (!isset($matrix[$recipientNo][$bucket])) {
                continue;
            }

            $matrix[$recipientNo][$bucket]['calendar'] += $this->toIntValue($row->calendar_amount_thousand);
            $matrix[$recipientNo][$bucket]['settlement'] += $this->toIntValue($row->settlement_amount_thousand);
        }



        // ▼ 横合計・縦合計を事前計算
        $rowTotals = [];
        foreach ($targets as $recipientNo => $recipientName) {
            $rowTotals[$recipientNo] = [
                'calendar'   => 0,
                'settlement' => 0,
            ];

            foreach ($yearKeys as $yearKey) {
                $rowTotals[$recipientNo]['calendar']   += (int)($matrix[$recipientNo][$yearKey]['calendar'] ?? 0);
                $rowTotals[$recipientNo]['settlement'] += (int)($matrix[$recipientNo][$yearKey]['settlement'] ?? 0);
            }
        }

        $colTotals = [];
        foreach ($yearKeys as $yearKey) {
            $colTotals[$yearKey] = [
                'calendar'   => 0,
                'settlement' => 0,
            ];

            foreach ($targets as $recipientNo => $recipientName) {
                $colTotals[$yearKey]['calendar']   += (int)($matrix[$recipientNo][$yearKey]['calendar'] ?? 0);
                $colTotals[$yearKey]['settlement'] += (int)($matrix[$recipientNo][$yearKey]['settlement'] ?? 0);
            }
        }

        $grandTotals = [
            'calendar'   => 0,
            'settlement' => 0,
        ];
        foreach ($rowTotals as $recipientNo => $totals) {
            $grandTotals['calendar']   += (int)($totals['calendar'] ?? 0);
            $grandTotals['settlement'] += (int)($totals['settlement'] ?? 0);
        }



        // ▼ レイアウト
        $leftX   =  13.5;
        $titleY  = 130.0;
        $tableY  = 148.5;
        $nameW   =  18.0;
        $typeW   =  18.5;
        $yearW   =  14.3;
        $totalW  =  14.3;        
        $headerH =  14.2;
        $rowH    =   5.55;
        
        $wakusen = 0;

        $pdf->SetTextColor(0, 0, 0);

        // ヘッダ

        //贈与者名
        $pdf->SetFont('mspgothic03', '', 11);
        $giftName = $this->resolveDonorName($dataId, $family);
        $pdf->MultiCell($nameW * 2, $rowH, $giftName, $wakusen, 'L', 0, 0, $leftX + 51.0, $titleY + 4.35);

        
        //贈与者年齢
        $nenrei = $this->resolveDonorAge($dataId, $family);

        
        $x = $leftX + $nameW + $typeW;
        foreach ($yearKeys as $yearKey) {
            $label = $this->formatGiftPlanYearLabel($deathYear, $yearKey);            
            
            if (str_starts_with($yearKey, 'before_')){                
            } else {

                //年次
                $pdf->SetFont('mspgothic03', '', 11);
                $pdf->MultiCell($yearW, $headerH, $label, $wakusen, 'C', 0, 0, $x, $tableY);

                //年齢
                $pdf->SetFont('mspgothic03', '', 10);
                if ($nenrei !== null) {
                    $nenrei++;
                    $pdf->MultiCell($yearW, $headerH, (string)$nenrei . '歳', $wakusen, 'C', 0, 0, $x, $tableY + 5.7);
                }

            }

            $x += $yearW;
        }
        
        

        // 右端の合計見出し
        $pdf->SetFont('mspgothic03', '', 11);
        //$pdf->MultiCell($totalW, $headerH, '合計', $wakusen, 'C', 0, 0, $x, $tableY);


        // 本体
        // 仕様：
        // - 受贈者 2〜10 の 9枠を固定で使う
        // - 氏名が空欄の受贈者は空行のまま残す
        // - 合計は必ず最下段（9人分の下の2段）に表示する
        $bodyStartY = $tableY + $headerH - 2.80;
        $slotRecipientNos = range(2, 10);

        foreach ($slotRecipientNos as $slotIndex => $recipientNo) {
            $y = $bodyStartY + ($rowH * 2 * $slotIndex);
            $displayName = trim((string)($targets[$recipientNo] ?? ''));

            if ($displayName === '') {
                continue;
            }

            $pdf->SetFont('mspgothic03', '', 9);
            $pdf->MultiCell($nameW, $rowH * 2, $displayName, $wakusen, 'L', 0, 0, $leftX, $y);

            $pdf->SetFont('mspgothic03', '', 9);

            $x = $leftX + $nameW + $typeW;

            foreach ($yearKeys as $yearKey) {

                $calendarValue = (int)($matrix[$recipientNo][$yearKey]['calendar'] ?? 0);
                $settlementValue = (int)($matrix[$recipientNo][$yearKey]['settlement'] ?? 0);

                // 暦年贈与の毎年の贈与額
                $pdf->MultiCell(
                    $yearW,
                    $rowH,
                    $this->formatCalendarPlanAmountByYearKey($yearKey, $calendarValue),
                    $wakusen,
                    'R',
                    0,
                    0,
                    $x,
                    $y
                );

                // 精算課税の毎年の贈与額
                $pdf->MultiCell(
                    $yearW,
                    $rowH,
                    $this->formatPlanAmount($settlementValue),
                    $wakusen,
                    'R',
                    0,
                    0,
                    $x,
                    $y + $rowH
                );

                $x += $yearW;
            }

            // 右端：各受贈者の横合計
            // 上段：暦年贈与
            $pdf->MultiCell(
                $totalW,
                $rowH,
                $this->formatPlanAmount((int)($rowTotals[$recipientNo]['calendar'] ?? 0)),
                $wakusen,
                'R',
                0,
                0,
                $x,
                $y
            );

            // 下段：精算課税
            $pdf->MultiCell(
                $totalW,
                $rowH,
                $this->formatPlanAmount((int)($rowTotals[$recipientNo]['settlement'] ?? 0)),
                $wakusen,
                'R',
                0,
                0,
                $x,
                $y + $rowH
            );
        }

        // 最下段：各年次の縦合計（固定位置）
        $totalY = $bodyStartY + ($rowH * 2 * count($slotRecipientNos));
        $x = $leftX + $nameW + $typeW;


        foreach ($yearKeys as $yearKey) {

            $calendarTotal = (int)($colTotals[$yearKey]['calendar'] ?? 0);
            $settlementTotal = (int)($colTotals[$yearKey]['settlement'] ?? 0);


            // 暦年課税
            $pdf->MultiCell(
                $yearW,
                $rowH,
                $this->formatCalendarPlanAmountByYearKey($yearKey, $calendarTotal),                
                $wakusen,
                'R',
                0,
                0,
                $x,
                $totalY
            );


            //相続時精算課税
            $pdf->MultiCell(
                $yearW,
                $rowH,
                $this->formatPlanAmount($settlementTotal),                
                $wakusen,
                'R',
                0,
                0,
                $x,
                $totalY + $rowH
            );

            $x += $yearW;
        }

        // 右下：総合計
        //暦年課税
        $pdf->MultiCell(
            $totalW,
            $rowH,
            $this->formatPlanAmount((int)$grandTotals['calendar']),
            $wakusen,
            'R',
            0,
            0,
            $x,
            $totalY
        );

        $pdf->MultiCell(
            $totalW,
            $rowH,
            $this->formatPlanAmount((int)$grandTotals['settlement']),
            $wakusen,
            'R',
            0,
            0,
            $x,
            $totalY + $rowH
        );        
        
        
        
    }

    /**
     * recipient_no 2-10 の受贈者名を組み立てる。
     * 優先順位：
     *   1. payload family
     *   2. proposal_family_members
     *   3. future_gift_recipients
     */
    private function buildGiftPlanTargets(int $dataId, array $family): array
    {
        $familyRows = ProposalFamilyMember::query()
            ->where('data_id', $dataId)
            ->orderBy('row_no')
            ->get()
            ->keyBy('row_no');

        $recipientRows = FutureGiftRecipient::query()
            ->where('data_id', $dataId)
            ->whereBetween('recipient_no', [2, 10])
            ->get()
            ->keyBy('recipient_no');

        $targets = [];

        foreach (range(2, 10) as $recipientNo) {
            $familyRow    = $familyRows->get($recipientNo);
            $recipientRow = $recipientRows->get($recipientNo);

            $name = trim((string)(
                ($family[$recipientNo]['name'] ?? null)
                ?: ($familyRow->name ?? null)
                ?: ($recipientRow->recipient_name ?? null)
                ?: ''
            ));


            // 仕様：
            // 受贈者の氏名が空欄のときは、その受贈者行をA3表に出さない
            if ($name === '') {
                continue;
            }

            $targets[$recipientNo] = $name;



        }

        return $targets;
    }




    /**
     * 贈与者名（row_no=1）を取得する。
     * 優先順位：
     *   1. payload family[1]['name']
     *   2. proposal_family_members.row_no=1
     *   3. 空文字
     */
    private function resolveDonorName(int $dataId, array $family): string
    {
        $nameFromPayload = trim((string)($family[1]['name'] ?? ''));
        if ($nameFromPayload !== '') {
            return $nameFromPayload;
        }

        $familyRow = ProposalFamilyMember::query()
            ->where('data_id', $dataId)
            ->where('row_no', 1)
            ->first(['name']);

        $nameFromDb = trim((string)($familyRow->name ?? ''));

        return $nameFromDb;
    }




    /**
     * 贈与者年齢（row_no=1）を取得する。
     * 優先順位：
     *   1. payload family[1]['age']
     *   2. proposal_family_members.row_no=1 の age
     *   3. 生年月日から当年1月1日時点の満年齢を算出
     *   4. 取得不可なら null
     */
    private function resolveDonorAge(int $dataId, array $family): ?int
    {
        if (isset($family[1]['age']) && $family[1]['age'] !== null && $family[1]['age'] !== '') {
            return $this->toIntValue($family[1]['age']) - 4;
        }

        $familyRow = ProposalFamilyMember::query()
            ->where('data_id', $dataId)
            ->where('row_no', 1)
            ->first(['age', 'birth_year', 'birth_month', 'birth_day']);

        if (!$familyRow) {
            return null;
        }

        if ($familyRow->age !== null && $familyRow->age !== '') {
            return $this->toIntValue($familyRow->age) - 4;
        }

        $birthYear  = $familyRow->birth_year  !== null ? (int)$familyRow->birth_year  : null;
        $birthMonth = $familyRow->birth_month !== null ? (int)$familyRow->birth_month : null;
        $birthDay   = $familyRow->birth_day   !== null ? (int)$familyRow->birth_day   : null;

        if (!$birthYear || !$birthMonth || !$birthDay) {
            return null;
        }

        try {
            $currentYear = (int)date('Y');
            $base = new \DateTimeImmutable(sprintf('%04d-01-01', $currentYear));
            $birth = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay));

            $age = $currentYear - $birthYear;
            $birthdayThisYear = $birth->setDate($currentYear, (int)$birth->format('m'), (int)$birth->format('d'));
            if ($birthdayThisYear > $base) {
                $age--;
            }
            
            $age = $age - 4;
            
            return ($age >= 0 && $age <= 130) ? $age : null;
        } catch (\Throwable $e) {
            return null;
        }
    }


    /**
     * 横軸の年キー
     *  - before_(相続開始年-3)
     *  - (相続開始年-3) / (相続開始年-2) / (相続開始年-1)
     *  - 相続開始年〜(相続開始年+19)
     *
     * 例: 相続開始年 2026
     *  - before_2023
     *  - 2023 / 2024 / 2025
     *  - 2026〜2045
     *
     * @return array<int,string>
     */
    private function giftPlanYearKeys(int $deathYear): array
    {

        $keys = [
            'before_' . ($deathYear - 3),
            (string)($deathYear - 3),
            (string)($deathYear - 2),
            (string)($deathYear - 1),
        ];        

        foreach (range($deathYear, $deathYear + 19) as $year) {            
            $keys[] = (string)$year;
        }

        return $keys;
    }

    /**
     * 受贈者 × 年次 の表示マトリクス初期化
     *
     * @param array<int,int>    $recipientNos
     * @param array<int,string> $yearKeys
     * @return array<int,array<string,array<string,int>>>
     */
    private function initGiftPlanMatrix(array $recipientNos, array $yearKeys): array
    {
        $matrix = [];

        foreach ($recipientNos as $recipientNo) {
            foreach ($yearKeys as $yearKey) {
                $matrix[$recipientNo][$yearKey] = [
                    'calendar'   => 0,
                    'settlement' => 0,
                ];
            }
        }

        return $matrix;
    }



    /**
     * 過年度分の年バケット
     *  - (相続開始年-3) より前 → before_(相続開始年-3)
     *  - (相続開始年-3)〜(相続開始年-1) → 年そのもの
     *  - それ以外 → null
     */
    private function resolvePastGiftBucket(int $deathYear, ?int $giftYear): ?string
    {
        if ($giftYear === null || $giftYear <= 0) {
            return null;
        }

        $firstPastYear = $deathYear - 3;
        $lastPastYear  = $deathYear - 1;

        if ($giftYear < $firstPastYear) {
            return 'before_' . $firstPastYear;
        }

        if ($giftYear >= $firstPastYear && $giftYear <= $lastPastYear) {
            return (string)$giftYear;
        }

        return null;
    }

    /**
     * 未来分の年を決定する
     *  - gift_year があればそれを使用
     *  - 無ければ row_no から「1年目 = 相続開始年」で補完
     */
    private function resolveFutureGiftYear(int $deathYear, ?int $giftYear, ?int $rowNo): ?int
    {
        $resolvedYear = $giftYear;

        if ($resolvedYear === null || $resolvedYear <= 0) {
            if ($rowNo === null || $rowNo <= 0) {
                return null;
            }

            // row_no=1 → 相続開始年, row_no=20 → 相続開始年+19
            $resolvedYear = $deathYear + $rowNo - 1;

        }

        if ($resolvedYear < $deathYear || $resolvedYear > ($deathYear + 19)) {        
            return null;
        }

        return $resolvedYear;
    }


    /**
     * 年見出しの表示ラベル
     *  - before_(相続開始年-3) → ～(相続開始年-4)
     *  - それ以外はそのまま
     */
    private function formatGiftPlanYearLabel(int $deathYear, string $yearKey): string
    {
        $beforeKey = 'before_' . ($deathYear - 3);
        if ($yearKey === $beforeKey) {
            return '～' . ($deathYear - 4);
        }

        return $yearKey;
    }

    /**
     * 表示用の相続開始年を解決する。
     *
     * 優先順位:
     *  1) payload.header_year
     *  2) payload.header['year']
     *  3) proposal_headers.proposal_year
     *  4) proposal_headers.proposal_date の年
     *  5) 当年
     */
    private function resolveDisplayDeathYear(array $payload, int $dataId): int
    {
        $candidates = [
            $payload['header_year'] ?? null,
            $payload['header']['year'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $year = $this->toIntValue($candidate);
            if ($year >= 1900) {
                return $year;
            }
        }

        $header = ProposalHeader::query()
            ->where('data_id', $dataId)
            ->first();

        if ($header) {
            $proposalYear = $this->toIntValue($header->proposal_year ?? null);
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
     * 文字列混在でも int 化
     */
    private function toIntValue($value): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int)$value;
        }

        $string = mb_convert_kana((string)$value, 'n', 'UTF-8');
        $string = str_replace([',', ' ', '　'], '', $string);
        $string = preg_replace('/[^\d\-]/', '', $string) ?? '';

        if ($string === '' || $string === '-') {
            return 0;
        }

        return (int)$string;
    }

    /**
     * 表示用フォーマット（0 も必ず表示）
     */
    private function formatPlanAmount(int $value): string
    {
        return number_format($value);
    }
    


    /**
     * 暦年贈与額の表示用フォーマット
     * - before_2022（～2021）列のみ、0 は空白
     * - それ以外の年は 0 も表示
     */
    private function formatCalendarPlanAmountByYearKey(string $yearKey, int $value): string
    {
        if ($yearKey === 'before_2022' && $value === 0) {
            return '';
        }

        return number_format($value);
    }
    // ============================================================
    // STEP3 削除対象ここまで
    // ============================================================


    private function resolveRelationshipFontSize(string $label): float
    {
        $label = trim($label);
        if ($label === '') {
            return 9.0;
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($label, 'UTF-8')
            : strlen($label);

        return match (true) {
            $length <= 3 => 9.0,
            $length === 4 => 8.5,
            $length === 5 => 8.0,
            $length === 6 => 7.0,
            $length === 7 => 6.5,
            default      => 6.0,
        };
    }



    private function resolveAssetInputMode(array $payload, int $dataId): string
    {
        $payloadMode = (string)($payload['header']['asset_input_mode'] ?? ($payload['asset_input_mode'] ?? ''));
        if (in_array($payloadMode, ['split', 'combined'], true)) {
            return $payloadMode;
        }

        $table = (new ProposalHeader())->getTable();
        if ($dataId > 0 && Schema::hasColumn($table, 'asset_input_mode')) {
            $dbMode = (string)(ProposalHeader::query()
                ->where('data_id', $dataId)
                ->value('asset_input_mode') ?? '');

            if (in_array($dbMode, ['split', 'combined'], true)) {
                return $dbMode;
            }
        }

        return 'split';
    }    
    
}

