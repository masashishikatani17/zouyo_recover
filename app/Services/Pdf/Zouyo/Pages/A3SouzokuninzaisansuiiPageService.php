<?php
 
namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;
use App\Models\FutureGiftRecipient;
use App\Models\ProposalFamilyMember;
use Illuminate\Support\Arr;
 
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
 
/**
 * 5: 各人別贈与額および贈与税 ページ
 * A3: 相続後の相続人別財産の推移
 *
 * 仕様：
 *  - A3テンプレート 1ページに相続人 3人分を表示
 *  - 横方向に 現時点〜20年後、縦方向に各項目を表示
 *  - 計算値 SoT は after.persons.{recipient_no}.timeline
 */
class A3SouzokuninzaisansuiiPageService implements ZouyoPdfPageInterface
{
    public function render(TCPDF $pdf, array $payload): void
    {

        $dataId = (int)($payload['data_id'] ?? 0);
        if ($dataId <= 0) {
            \Log::warning('[A3SouzokuninzaisansuiiPageService] data_id missing in payload');
            return;
        }

        $familyRows = ProposalFamilyMember::query()
            ->where('data_id', $dataId)
            ->orderBy('row_no')
            ->get()
            ->keyBy('row_no');

        $recipientsByNo = FutureGiftRecipient::query()
            ->where('data_id', $dataId)
            ->whereBetween('recipient_no', [2, 10])
            ->get()
            ->keyBy('recipient_no');

        $targets = [];
        for ($no = 2; $no <= 10; $no++) {
            $nameFromFamily = is_object($familyRows->get($no))
                ? trim((string)($familyRows[$no]->name ?? ''))
                : '';

            $recipientRow = $recipientsByNo->get($no);
            $nameFromRecipient = $recipientRow
                ? trim((string)($recipientRow->recipient_name ?? ''))
                : '';

            $name = $nameFromFamily !== '' ? $nameFromFamily : $nameFromRecipient;
            if ($name === '') {
                continue;
            }

            $targets[] = [
                'recipient_no'      => $no,
                'recipient_name'    => $name,
                'relationship_code' => (int)($familyRows[$no]->relationship_code ?? 0),
            ];
        }

        if (empty($targets)) {
            return;
        }

        $relationships = config('relationships');

        $templatePath = resource_path('/views/pdf/A3_07_pr_kakusisan.pdf');
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("A3 kakusisan template not found: {$templatePath}");
        }

        $pdf->setSourceFile($templatePath);
        $tplId = $pdf->importPage(1);

        $birthByRow = [];
        foreach ($familyRows as $rowNo => $row) {
            $birthByRow[$rowNo] = [
                'year'  => $row->birth_year  !== null ? (int)$row->birth_year  : null,
                'month' => $row->birth_month !== null ? (int)$row->birth_month : null,
                'day'   => $row->birth_day   !== null ? (int)$row->birth_day   : null,
            ];
        }

        $resultsData = (array)($payload['resultsData'] ?? []);
        if (empty($resultsData)) {
            $resultsData = (array)Session::get('zouyo.results', []);
        }
        if (empty($resultsData)) {
            $cacheKey = Session::get('zouyo.results_key');
            if ($cacheKey) {
                $resultsData = (array)Cache::get($cacheKey, []);
            }
        }
        

        $familyPayload = (array)($payload['family'] ?? []);
        $headerPayload = (array)($payload['header'] ?? []);

        /*
        \Log::debug('2026.03.26 00001 [A3Souzokuninzaisansuii][payload check]', [
            'payload_keys' => array_keys($payload),
            'family_keys' => array_keys($familyPayload),
            'header_keys' => array_keys($headerPayload),
        ]);
        */



        $sectionDefs = [
            [
                'name_x'      => 14.0,
                'name_y'      => 23.9,
                'name_w'      => 35.0,
                'rel_y'       => 28.4,
                'table_top_y' => 29.6,
                'col_start_x' => 76.2,
                'col_width'   => 15.90,
            ],
            [
                'name_x'      => 14.0,
                'name_y'      => 113.5,
                'name_w'      => 35.0,
                'rel_y'       => 127.0,
                'table_top_y' => 119.0,
                'col_start_x' => 76.2,
                'col_width'   => 15.90,
            ],
            [
                'name_x'      => 14.0,
                'name_y'      => 203.0,
                'name_w'      => 35.0,
                'rel_y'       => 225.6,
                'table_top_y' => 208.6,
                'col_start_x' => 76.2,
                'col_width'   => 15.90,
            ],
        ];

        $pageNo = 0;
        foreach (array_chunk($targets, 3) as $chunk) {
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);

            $pdf->AddPage();
            $pdf->useTemplate($tplId);

            $pageNo++;
            $pdf->SetFont('mspgothic03', '', 10);
            $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(28, 5, '(7 - ' . $pageNo . 'ページ)', 0, 'R', 0, 0, 386, 286);

            foreach ($chunk as $index => $info) {
                $this->drawPersonSection(
                    $pdf,
                    $sectionDefs[$index],
                    $info,
                    $relationships,
                    $birthByRow,
                    $resultsData,
                    $familyPayload,
                    $headerPayload
                );
            }
        }
    }

    private function drawPersonSection(
        TCPDF $pdf,
        array $section,
        array $info,
        array $relationships,
        array $birthByRow,
        array $resultsData,
        array $prefillFamily,
        array $prefillHeader        
    ): void {
        $recipientNo   = (int)($info['recipient_no'] ?? 0);
        $recipientName = (string)($info['recipient_name'] ?? '');
        $relCode       = $info['relationship_code'] ?? null;

        $relLabel = $relCode !== null && array_key_exists($relCode, $relationships)
            ? (string)$relationships[$relCode]
            : '';

        $timeline = Arr::get($resultsData, 'after.persons.' . $recipientNo . '.timeline', []);
        if (!is_array($timeline)) {
            $timeline = [];
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('mspgothic03', '', 8.5);
        $pdf->MultiCell((float)$section['name_w'], 4.5, $recipientName, 0, 'L', 0, 0, (float)$section['name_x'], (float)$section['name_y']);
        //$pdf->MultiCell((float)$section['name_w'], 4.5, $relLabel,      0, 'C', 0, 0, (float)$section['name_x'], (float)$section['rel_y']);

        $rowYs = $this->buildRowYs((float)$section['table_top_y']);
        $colStartX = (float)$section['col_start_x'];
        $colWidth  = (float)$section['col_width'];

        $pdf->SetFont('mspgothic03', '', 7.0);

        $birth = $birthByRow[$recipientNo] ?? ['year' => null, 'month' => null, 'day' => null];
        $age0  = $this->calcAgeAtJan1($birth['year'], $birth['month'], $birth['day'], 2025);

        for ($i = 0; $i <= 20; $i++) {
            $x = $colStartX + ($colWidth * $i);
            $pRow = $timeline[$i] ?? [];
            if (!is_array($pRow)) {
                $pRow = [];
            }

            $assetTotal      = (int)round(((int)($pRow['asset_total_yen']              ?? 0)) / 1000, 0);
            $giftCalReceived = (int)round(((int)($pRow['gift_calendar_received_yen']   ?? 0)) / 1000, 0);
            $giftCalTax      = (int)round(((int)($pRow['gift_calendar_tax_yen']        ?? 0)) / 1000, 0);
            $giftSetReceived = (int)round(((int)($pRow['gift_settlement_received_yen'] ?? 0)) / 1000, 0);
            $giftSetTax      = (int)round(((int)($pRow['gift_settlement_tax_yen']      ?? 0)) / 1000, 0);
            $inheritNet      = (int)round(((int)($pRow['inherit_net_yen']              ?? 0)) / 1000, 0);
            $inheritTax      = (int)round(((int)($pRow['inherit_tax_yen']              ?? 0)) / 1000, 0);
            $investGain      = (int)round(((int)($pRow['investment_gain_yen']          ?? 0)) / 1000, 0);
            $assetAfter      = (int)round(((int)($pRow['asset_after_yen']              ?? 0)) / 1000, 0);

            $ageText = '';
            if ($age0 !== null) {
                $ageText = (string)($age0 + $i) . '歳';
            }
 
            $currentNoValue = '  －';
            $currentNoValueAlign = 'C';
            $valueAlign = 'R';
              
 
            $this->drawValueCell($pdf, $x + 2.0, $rowYs['age'],       $colWidth, $ageText,                  'C');
            $this->drawValueCell($pdf, $x, $rowYs['asset_total'],     $colWidth, $this->fmt($assetTotal),   $valueAlign);
 
            $this->drawValueCell($pdf, $x, $rowYs['gift_cal_receive'],$colWidth, $i === 0 ? $currentNoValue : $this->fmt($giftCalReceived), $i === 0 ? $currentNoValueAlign : $valueAlign);
            $this->drawValueCell($pdf, $x, $rowYs['gift_cal_tax'],    $colWidth, $i === 0 ? $currentNoValue : $this->fmt(-$giftCalTax),     $i === 0 ? $currentNoValueAlign : $valueAlign);
            $this->drawValueCell($pdf, $x, $rowYs['gift_set_receive'],$colWidth, $i === 0 ? $currentNoValue : $this->fmt($giftSetReceived), $i === 0 ? $currentNoValueAlign : $valueAlign);
            $this->drawValueCell($pdf, $x, $rowYs['gift_set_tax'],    $colWidth, $i === 0 ? $currentNoValue : $this->fmt(-$giftSetTax),     $i === 0 ? $currentNoValueAlign : $valueAlign);
 
            $this->drawValueCell($pdf, $x, $rowYs['inherit_net'],     $colWidth, $this->fmt($inheritNet),   $valueAlign);
            $this->drawValueCell($pdf, $x, $rowYs['inherit_tax'],     $colWidth, $this->fmt(-$inheritTax),  $valueAlign);
 
            $this->drawValueCell($pdf, $x, $rowYs['invest_gain'],     $colWidth, $i === 0 ? $currentNoValue : $this->fmt($investGain),      $i === 0 ? $currentNoValueAlign : $valueAlign);
 
            $this->drawValueCell($pdf, $x, $rowYs['asset_after'],     $colWidth, $this->fmt($assetAfter),   $valueAlign);


        }
        


        $this->drawCommentBlock(
            $pdf,
            (float)$section['table_top_y'] + 66.0,
            $recipientNo,
            $prefillFamily,
            $prefillHeader
        );        
        

    }
    

    private function drawCommentBlock(
        TCPDF $pdf,
        float $y,
        int $recipientNo,
        array $prefillFamily,
        array $prefillHeader
    ): void {
        $wakusen = 0;


        /*
        \Log::debug('2026.03.26 00002 [A3Souzokuninzaisansuii][comment source]', [
            'recipientNo' => $recipientNo,
            'family_exists' => array_key_exists($recipientNo, $prefillFamily),
            'family_row' => $prefillFamily[$recipientNo] ?? null,
            'header' => $prefillHeader,
        ]);
        */



        $strStar = '★';
        $cashK   = (int)($prefillFamily[$recipientNo]['cash'] ?? 0);
        $per     = (float)($prefillHeader['per'] ?? 0);

        $line1 = '資産運用による増加額とは対策前の所有財産のうち金融資産である'
            . number_format($cashK)
            . '千円と贈与による財産の額から贈与税を控除した額を運用した場合の運用益です';

        $line2 = '(税引後利回り '
            . number_format($per, 1)
            . '％)。';

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('mspmincho02', '', 10);

        $pdf->MultiCell(10, 15, '(' . $strStar . ')', $wakusen, 'L', 0, 0, 20, $y);
        $pdf->MultiCell(550, 5, $line1 . $line2, $wakusen, 'L', 0, 0, 27, $y);

    }




    private function buildRowYs(float $tableTopY): array
    {
        return [
            'age'              => $tableTopY +  6.2,
            'asset_total'      => $tableTopY + 12.4,
            'gift_cal_receive' => $tableTopY + 18.0,
            'gift_cal_tax'     => $tableTopY + 24.0,
            'gift_set_receive' => $tableTopY + 30.0,
            'gift_set_tax'     => $tableTopY + 36.0,
            'inherit_net'      => $tableTopY + 42.0,
            'inherit_tax'      => $tableTopY + 48.0,
            'invest_gain'      => $tableTopY + 53.8,
            'asset_after'      => $tableTopY + 59.8,
        ];
    }

    private function drawValueCell(
        TCPDF $pdf,
        float $x,
        float $y,
        float $w,
        string $text,
        string $align
    ): void {
        $pdf->SetFont('mspgothic03', '', 9.0);
        $pdf->MultiCell($w, 4.5, $text, 0, $align, 0, 0, $x, $y);
    }

    private function fmt(?int $value): string
    {
        return $value === null ? '' : number_format((int)$value);
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