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
 * 8: 相続後の各人別財産の推移(贈与税・相続税支払後)
 *
 * 仕様：
 *  - A3テンプレート 1ページに相続人全員を表示
 *  - 横方向に 現時点〜20年後、縦方向に 相続人一覧 を表示
 *  - 3ブロック構成
 *      1. 対策前  = before.persons.{recipient_no}.timeline.{n}.asset_total_yen
 *      2. 増加額  = 対策後 - 対策前
 *      3. 対策後  = after.persons.{recipient_no}.timeline.{n}.asset_after_yen
 *  - 各ブロック最下段に合計を表示
*/
class A3KakujinzaisansuiiPageService implements ZouyoPdfPageInterface
{
    public function render(TCPDF $pdf, array $payload): void
    {

        $dataId = (int)($payload['data_id'] ?? 0);
        if ($dataId <= 0) {
            \Log::warning('[A3KakujinzaisansuiiPageService] data_id missing in payload');
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
               'recipient_no'   => $no,
               'recipient_name' => $name,
            ];
        }
 
        if (empty($targets)) {
            return;
        }
 
        $templatePath = resource_path('/views/pdf/A3_08_pr_gosuii.pdf');
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("A3 gosuii template not found: {$templatePath}");
        }
 
        $pdf->setSourceFile($templatePath);
        $tplId = $pdf->importPage(1);
 
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
 
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        $pdf->AddPage();
        $pdf->useTemplate($tplId);

        $pdf->SetFont('mspgothic03', '', 10);
        $pdf->SetTextColor(0, 0, 0);

        $wakusen = 0;
        $x = 375;
        $y = 277;
        $pdf->MultiCell(30, 6, '９ページ', $wakusen, 'R', 0, 0, $x, $y);

        $layout = $this->buildLayout();
        $series = $this->buildSeries($targets, $resultsData);

        $this->drawBlock($pdf, $layout['blocks']['before'],   $layout, $targets, $series['before'],   $series['totals']['before']);
        $this->drawBlock($pdf, $layout['blocks']['increase'], $layout, $targets, $series['increase'], $series['totals']['increase']);
        $this->drawBlock($pdf, $layout['blocks']['after'],    $layout, $targets, $series['after'],    $series['totals']['after']);


        $this->renderTotalAssetComparisonChart($pdf, $series['totals']);        

    }
 
    private function buildLayout(): array
    {
        return [
            'name_x'          => 31.6,
            'name_w'          => 23.5,
            'year_start_x'    => 55.0,
            'year_step'       => 16.245,
            'year_col_width'  => 17.0,
            'blocks' => [
                'before' => [
                    // テンプレート変更後、1行目がヘッダ罫線に近すぎるため少し下げる
                    'body_top_y'    => 36.0,
                    'body_bottom_y' => 78.8,
                    'total_y'       => 80.0,
                ],
                'increase' => [
                    // 最下段が次ブロック境界線に掛かるため、本文を少し上で締め、
                    // 合計行は枠内中央へ戻す
                    'body_top_y'    => 85.0,
                    'body_bottom_y' => 127.4,
                    'total_y'       => 128.6,
                ],
                'after' => [
                    // 最終ブロックは特に下へ落ちているため、本文・合計とも上へ詰める
                    'body_top_y'    => 134.0,
                    'body_bottom_y' => 176.1,
                    'total_y'       => 177.5,
                ],
            ],
        ];
    }
 
    private function buildSeries(array $targets, array $resultsData): array
    {
        $result = [
            'before' => [],
            'increase' => [],
            'after' => [],
            'totals' => [
                'before' => array_fill(0, 21, 0),
                'increase' => array_fill(0, 21, 0),
                'after' => array_fill(0, 21, 0),
            ],
        ];

        foreach ($targets as $info) {
            $recipientNo = (int)($info['recipient_no'] ?? 0);

            $beforeTimeline = Arr::get($resultsData, 'before.persons.' . $recipientNo . '.timeline', []);
            $afterTimeline  = Arr::get($resultsData, 'after.persons.' . $recipientNo . '.timeline', []);

            if (!is_array($beforeTimeline)) {
                $beforeTimeline = [];
            }
            if (!is_array($afterTimeline)) {
                $afterTimeline = [];
            }

            $result['before'][$recipientNo] = [];
            $result['increase'][$recipientNo] = [];
            $result['after'][$recipientNo] = [];

            for ($i = 0; $i <= 20; $i++) {
                $beforeRow = $beforeTimeline[$i] ?? [];
                $afterRow  = $afterTimeline[$i] ?? [];

                if (!is_array($beforeRow)) {
                    $beforeRow = [];
                }
                if (!is_array($afterRow)) {
                    $afterRow = [];
                }

                $beforeYen = $this->firstInt([
                    $beforeRow['asset_total_yen'] ?? null,
                    $afterRow['asset_total_yen'] ?? null,
                ]);

                $afterYen = $this->firstInt([
                    $afterRow['asset_after_yen'] ?? null,
                    $afterRow['asset_total_yen'] ?? null,
                ]);

                $beforeK = $this->toK($beforeYen);
                $afterK  = $this->toK($afterYen);
                $diffK   = $afterK - $beforeK;

                $result['before'][$recipientNo][$i] = $beforeK;
                $result['increase'][$recipientNo][$i] = $diffK;
                $result['after'][$recipientNo][$i] = $afterK;

                $result['totals']['before'][$i] += $beforeK;
                $result['totals']['increase'][$i] += $diffK;
                $result['totals']['after'][$i] += $afterK;
            }
        }

        return $result;
    }
    
    

    /**
     * 9ページ下半分グラフ
     *
     * - 横軸は「現時点 / 5年後 / 10年後 / 15年後 / 20年後」の5点固定
     * - 棒グラフの元データは各相続人の合計額
     *   - 対策前 = totals.before
     *   - 対策後 = totals.after
     * - 積み上げはしない
     */
    private function renderTotalAssetComparisonChart(TCPDF $pdf, array $totals): void
    {
        $graphRows = $this->buildTotalAssetGraphRows($totals);
        if (empty($graphRows)) {
            return;
        }

        $values = [];
        foreach ($graphRows as $row) {
            $values[] = (int)($row['before'] ?? 0);
            $values[] = (int)($row['after'] ?? 0);
        }

        [$axisMin, $axisMax, $tickStep] = $this->calculateAssetChartAxis($values);

        // 9ページ下半分
        $chartX = 33.0;
        $chartY = 190.0;
        $chartW = 344.0;
        $chartH = 80.0;

        // プロットエリアを少し下げて、上部に凡例・タイトルの余白を確保        
        $plotLeft   = 26.0;
        $plotRight  = 6.0;
        $plotTop    = 13.0;
        $plotBottom =  8.0;

        $plotX = $chartX + $plotLeft;
        $plotY = $chartY + $plotTop;
        $plotW = $chartW - $plotLeft - $plotRight;
        $plotH = $chartH - $plotTop - $plotBottom;
        


        $plotFillColor   = [255, 252, 242]; // 極薄いクリーム
        $beforeFillColor = [218, 236, 214]; // 淡い緑
        $afterFillColor  = [236, 198, 198]; // 淡い赤
        $beforeHatchColor = [92, 122, 86];  // 濃い緑
        $afterHatchColor  = [146, 92, 92];  // 濃い赤        
        $beforeHatch     = 'right';         // 右斜め線
        $afterHatch      = 'left';          // 左斜め線

        // 枠・背景
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor($plotFillColor[0], $plotFillColor[1], $plotFillColor[2]);
        $pdf->Rect($plotX, $plotY, $plotW, $plotH, 'DF');

        $pdf->SetFont('mspgothic03', '', 10.5);
        $pdf->SetTextColor(0, 0, 0);


        // 目盛り
        $pdf->SetFont('mspgothic03', '', 10.5);
        for ($value = $axisMin; $value <= $axisMax; $value += $tickStep) {
            $yy = $this->valueToChartY($value, $axisMin, $axisMax, $plotY, $plotH);            

            $pdf->SetDrawColor(210, 210, 210);
            $pdf->Line($plotX, $yy, $plotX + $plotW, $yy);

            $pdf->SetDrawColor(0, 0, 0);
            $pdf->MultiCell(
                24,
                6,
                number_format($value),
                0,
                'R',
                0,
                0,
                $plotX - 26.0,
                $yy - 3.0,
                true,
                0,
                false,
                true,
                6,
                'M'
            );
        }

        $pdf->MultiCell(
            24,
            6,
            '（千円）',
            0,
            'L',
            0,
            0,
            $plotX - 11.0,
            $chartY + 5.0,
            true,
            0,
            false,
            true,
            6,
            'M'
        );

        // タイトル（プロットエリアの外側）        
        $pdf->SetFont('mspgothic03', '', 12.0);
        $pdf->MultiCell(
            $plotW + 20.0,
            10,
            '＜各人の合計額で比較＞',
            0,
            'C',
            0,
            0,
            $plotX - 16.0,
            $plotY - 18.0,
            true,
            0,
            false,
            true,
            6,
            'M'
        );

        // 凡例
        $pdf->SetFont('mspgothic03', '', 9.5);
        $legendTotalW = 114.0;
        $legendX = $chartX + (($chartW - $legendTotalW) / 2.0) + 22.0;
        $legendY = $chartY + 6.0;

        $this->drawLegendSwatch($pdf, $legendX, $legendY, 5.0, 3.5, $beforeFillColor, $beforeHatch, $beforeHatchColor);
        $pdf->MultiCell(
            45,
            6,
            '対策前の財産の額',
            0,
            'L',
            0,
            0,
            $legendX + 7.0,
            $legendY - 1.4,
            true,
            0,
            false,
            true,
            6,
            'M'
        );


        $this->drawLegendSwatch($pdf, $legendX + 55.0, $legendY, 5.0, 3.5, $afterFillColor, $afterHatch, $afterHatchColor);
        $pdf->MultiCell(
            45,
            6,
            '対策後の財産の額',
            0,
            'L',
            0,
            0,
            $legendX + 62.0,
            $legendY - 1.4,
            true,
            0,
            false,
            true,
            6,
            'M'
        );
        


        // 棒グラフ描画
        $groupCount = max(1, count($graphRows));
        $groupWidth = $plotW / $groupCount;
        $barGap     = 0.0;
        $barWidth   = min(19.0, max(14.0, ($groupWidth - $barGap - 6.0) / 2.0));
        $baseY      = $plotY + $plotH;


        foreach ($graphRows as $i => $row) {
            $groupStartX = $plotX + ($groupWidth * $i);
            $centerX     = $groupStartX + ($groupWidth / 2.0);

            $beforeX = $centerX - ($barGap / 2.0) - $barWidth;
            $afterX  = $centerX + ($barGap / 2.0);

            $beforeValue = (int)($row['before'] ?? 0);
            $afterValue  = (int)($row['after'] ?? 0);

            $this->drawSingleBar(
                $pdf,
                $beforeX,
                $barWidth,
                $plotY,
                $plotH,
                $axisMin,
                $axisMax,
                $beforeValue,
                $beforeFillColor,
                $beforeHatch,
                $beforeHatchColor
            );

            $this->drawSingleBar(
                $pdf,
                $afterX,
                $barWidth,
                $plotY,
                $plotH,
                $axisMin,
                $axisMax,
                $afterValue,
                $afterFillColor,
                $afterHatch,
                $afterHatchColor
            );

            $pdf->SetFont('mspgothic03', '', 10.0);
            $pdf->MultiCell(
                $groupWidth,
                6,
                (string)($row['label'] ?? ''),
                0,
                'C',
                0,
                0,
                $groupStartX,
                $baseY + 1.8,
                true,
                0,
                false,
                true,
                6,
                'M'
            );
        }
    }
    
    
    
 
    private function drawBlock(
         TCPDF $pdf,
        array $block,
        array $layout,
        array $targets,
        array $personSeries,
        array $totals
    ): void {
        $count = count($targets);
        if ($count <= 0) {
            return;
        }

        $bodyTop    = (float)$block['body_top_y'];
        $bodyBottom = (float)$block['body_bottom_y'];
        $rowPitch   = ($bodyBottom - $bodyTop) / $count;
        $cellH      = max(3.0, min(4.2, $rowPitch - 0.5));

        $nameFont  = $count >= 8 ? 8.5 : 9.0;
        $valueFont = $count >= 8 ? 8.5 : 9.0;

        foreach ($targets as $index => $info) {
            $recipientNo = (int)($info['recipient_no'] ?? 0);
            $name = (string)($info['recipient_name'] ?? '');

            $rowY = $bodyTop + ($rowPitch * $index) + (($rowPitch - $cellH) / 2);

            $pdf->SetFont('mspgothic03', '', $nameFont);
            $pdf->MultiCell(
                (float)$layout['name_w'],
                $cellH,
                $name,
                0,
                'L',
                0,
                0,
                (float)$layout['name_x'],
                $rowY
            );

            for ($year = 0; $year <= 20; $year++) {
                $x = (float)$layout['year_start_x'] + ((float)$layout['year_step'] * $year);
                $value = (int)($personSeries[$recipientNo][$year] ?? 0);

                $this->drawValueCell(
                    $pdf,
                    $x,
                    $rowY,
                    (float)$layout['year_col_width'],
                    $this->fmt($value),
                    'R',
                    $valueFont,
                    $cellH
                );
            }
        }

        for ($year = 0; $year <= 20; $year++) {
            $x = (float)$layout['year_start_x'] + ((float)$layout['year_step'] * $year);
            $total = (int)($totals[$year] ?? 0);

            $this->drawValueCell(
                $pdf,
                $x,
                (float)$block['total_y'],
                (float)$layout['year_col_width'],
                $this->fmt($total),
                'R',
                9.0,
                3.8
            );
        }
    }
 
    private function drawValueCell(
        TCPDF $pdf,
        float $x,
        float $y,
        float $w,
        string $text,
        string $align = 'R',
        float $fontSize = 9.0,
        float $cellH = 4.0
    ): void {
        $pdf->SetFont('mspgothic03', '', $fontSize);
        $pdf->MultiCell($w, $cellH, $text, 0, $align, 0, 0, $x, $y);
    }
    

    private function buildTotalAssetGraphRows(array $totals): array
    {
        $rows = [];

        // 「現時点」が重複しないよう、グラフはこの5点固定
        $years = [
            0  => '現時点',
            5  => '5年後',
            10 => '10年後',
            15 => '15年後',
            20 => '20年後',
        ];

        foreach ($years as $year => $label) {
            $rows[] = [
                'label'  => $label,
                'before' => (int)($totals['before'][$year] ?? 0),
                'after'  => (int)($totals['after'][$year] ?? 0),
            ];
        }

        return $rows;
    }

    private function calculateAssetChartAxis(array $values): array
    {
        $values = array_map('intval', $values);

        if (empty($values)) {
            return [0, 1000, 200];
        }

        $minValue = min($values);
        $maxValue = max($values);

        if ($maxValue <= 0) {
            return [0, 1000, 200];
        }

        $range      = max(1, $maxValue - $minValue);
        $rawStep    = max(1, (int)ceil($range / 4));
        $magnitude  = (int)pow(10, floor(log10((float)$rawStep)));
        $normalized = $rawStep / max(1, $magnitude);

        if ($normalized <= 1) {
            $nice = 1;
        } elseif ($normalized <= 2) {
            $nice = 2;
        } elseif ($normalized <= 5) {
            $nice = 5;
        } else {
            $nice = 10;
        }

        $tickStep = (int)($nice * $magnitude);

        // 最小値・最大値に少し余白を持たせる
        $axisMin = (int)(floor(($minValue - ($tickStep * 0.8)) / $tickStep) * $tickStep);
        $axisMax = (int)(ceil(($maxValue + ($tickStep * 0.8)) / $tickStep) * $tickStep);

        if ($axisMin < 0) {
            $axisMin = 0;
        }

        if ($axisMax <= $axisMin) {
            $axisMax = $axisMin + ($tickStep * 4);
        }

        return [$axisMin, $axisMax, $tickStep];
    }

    private function valueToChartY(
        int $value,
        int $axisMin,
        int $axisMax,
        float $plotY,
        float $plotH
    ): float {
        if ($axisMax <= $axisMin) {
            return $plotY + $plotH;
        }

        $clamped = min(max($value, $axisMin), $axisMax);
        $ratio   = ($clamped - $axisMin) / ($axisMax - $axisMin);

        return $plotY + $plotH - ($plotH * $ratio);
    }

    private function drawSingleBar(
        TCPDF $pdf,
        float $x,
        float $width,
        float $plotY,
        float $plotH,
        int $axisMin,
        int $axisMax,
        int $value,
        array $fillColor,
        string $hatch,
        array $hatchColor
    ): void {
        if ($axisMax <= $axisMin) {
            return;
        }

        $visibleTop = min(max($value, $axisMin), $axisMax);
        $yTop       = $this->valueToChartY($visibleTop, $axisMin, $axisMax, $plotY, $plotH);
        $yBottom    = $this->valueToChartY($axisMin, $axisMin, $axisMax, $plotY, $plotH);
        $height     = $yBottom - $yTop;

        if ($height <= 0) {
            return;
        }

        [$r, $g, $b] = $fillColor;

        $pdf->SetFillColor($r, $g, $b);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x, $yTop, $width, $height, 'DF');

        if ($hatch !== '') {
            $this->drawHatchPattern($pdf, $x, $yTop, $width, $height, $hatch, $hatchColor);
        }

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x, $yTop, $width, $height, 'D');
    }

    private function drawLegendSwatch(
        TCPDF $pdf,
        float $x,
        float $y,
        float $w,
        float $h,
        array $fillColor,
        string $hatch,
        array $hatchColor
    ): void {
        $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x, $y, $w, $h, 'DF');

        $this->drawHatchPattern($pdf, $x, $y, $w, $h, $hatch, $hatchColor, 1.4, 0.12);        

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x, $y, $w, $h, 'D');
    }

    private function drawHatchPattern(
        TCPDF $pdf,
        float $x,
        float $y,
        float $w,
        float $h,
        string $direction,
        array $lineColor,        
        float $spacing = 1.8,
        float $lineWidth = 0.08
    ): void {
        if ($w <= 0 || $h <= 0) {
            return;
        }

        $pdf->SetDrawColor($lineColor[0], $lineColor[1], $lineColor[2]);        
        $pdf->SetLineWidth($lineWidth);

        $limit = $w + $h;

        for ($k = 0.0; $k <= $limit; $k += $spacing) {
            if ($direction === 'right') {
                // 右斜め線（／）
                $startX = ($k <= $h) ? $x : ($x + $k - $h);
                $startY = ($k <= $h) ? ($y + $h - $k) : $y;
                $endX   = ($k <= $w) ? ($x + $k) : ($x + $w);
                $endY   = ($k <= $w) ? ($y + $h) : ($y + $h - ($k - $w));
            } else {
                // 左斜め線（＼）
                $startX = ($k <= $h) ? $x : ($x + $k - $h);
                $startY = ($k <= $h) ? ($y + $k) : ($y + $h);
                $endX   = ($k <= $w) ? ($x + $k) : ($x + $w);
                $endY   = ($k <= $w) ? $y : ($y + $k - $w);
            }

            if ($endX > $startX || abs($endY - $startY) > 0.01) {
            $pdf->Line($startX, $startY, $endX, $endY);
            }
        }

        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(0, 0, 0);
    }

    
    

    private function firstInt(array $candidates): int
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            return (int)$candidate;
        }

        return 0;
    }




    private function toK(int $yen): int
    {
        return (int)round($yen / 1000, 0);
    }
 
    private function fmt(?int $value): string
    {
        return $value === null ? '' : number_format((int)$value);
    }
}