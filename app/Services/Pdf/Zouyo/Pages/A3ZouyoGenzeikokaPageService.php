<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Models\ProposalFamilyMember;
use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use TCPDF;

class A3ZouyoGenzeikokaPageService implements ZouyoPdfPageInterface
{
    public function render(TCPDF $pdf, array $payload): void
    {
        $dataId = (int)($payload['data_id'] ?? 0);

        if ($dataId <= 0) {
            \Log::warning('[A3ZouyoGenzeikokaPageService] data_id missing in payload');
            return;
        }

        $templatePath = resource_path('/views/pdf/A3_07_zouyogenzeikouka.pdf');
        if (!is_file($templatePath)) {
            \Log::warning('[A3ZouyoGenzeikokaPageService] template pdf not found', [
                'path' => $templatePath,
            ]);
            return;
        }

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

    // ページ番号
        $pdf->SetFont('mspgothic03', '', 10);
        $pdf->MultiCell(30, 6, '７ページ', 0, 'R', 0, 0, 375.0, 277.0);

        $header = is_array($payload['header'] ?? null) ? $payload['header'] : [];

        $familyRows = ProposalFamilyMember::query()
            ->where('data_id', $dataId)
            ->orderBy('row_no')
            ->get()
            ->keyBy('row_no');

        $resultsData = $this->resolveResultsData($payload);
        $effectRows  = $this->getZouyoGenzeiKokaTrendRows($dataId, $familyRows, $resultsData, $header);

        if (empty($effectRows)) {
            \Log::warning('[A3ZouyoGenzeikokaPageService] no effect rows found', [
                'data_id' => $dataId,
            ]);
            return;
        }


        // 上半分グラフ（5年後・10年後・15年後・20年後の固定比較）
        $this->renderStackedComparisonChart($pdf, $effectRows);



        /**
         * 下表（贈与による減税効果）
         * - テンプレート実測に合わせた座標
         * - 現時点〜20年後を左から右へ描画
         * - 「対策前」の現時点にある「贈与税累計額」「合計」の「－」は
         *   テンプレート側に印字済みなので、コード側では上書きしない
         */
        $startX    = 67.2;   // 現時点列の左端
        $colWidth  = 15.9;   // 1年分の列幅
        $rowHeight = 5.2;

        $rowY = [
            'age'           => 206.6,
            'sozoku_before' => 213.2,
            'gift_before'   => 219.7,
            'total_before'  => 226.2,
            'sozoku_after'  => 232.7,
            'gift_after'    => 239.3,
            'total_after'   => 245.8,
            'diff'          => 252.4,
        ];

        $pdf->SetFont('mspgothic03', '', 8.2);

        foreach ($effectRows as $i => $row) {
            if ($i > 20) {
                \Log::info('[A3ZouyoGenzeikokaPageService] table columns truncated', [
                    'data_id'      => $dataId,
                    'column_index' => $i,
                ]);
                break;
            }

            $xx = $startX + ($colWidth * $i);

            // 年齢
            $this->drawTableCell(
                $pdf,
                (string)($row['age'] ?? ''),
                $xx + 1.8,
                $rowY['age'],
                $colWidth,
                $rowHeight,
                'C'
            );

            // 対策前 - 相続税
            $this->drawTableCell(
                $pdf,
                $this->formatAmountCell($row['sozoku_before'] ?? null),
                $xx,
                $rowY['sozoku_before'],
                $colWidth,
                $rowHeight,
                'R'
            );

            // 対策前 - 贈与税累計額
            // 現時点の「－」はテンプレートに印字済みなので空欄のままにする
            $this->drawTableCell(
                $pdf,
                ($i === 0) ? '' : $this->formatAmountCell($row['gift_before'] ?? null),
                $xx,
                $rowY['gift_before'],
                $colWidth,
                $rowHeight,
                'R'
            );

            // 対策前 - 合計
            // 現時点の「－」はテンプレートに印字済みなので空欄のままにする
            $this->drawTableCell(
                $pdf,
                ($i === 0) ? '' : $this->formatAmountCell($row['total_before'] ?? null),
                $xx,
                $rowY['total_before'],
                $colWidth,
                $rowHeight,
                'R'
            );

            // 対策後 - 相続税
            $this->drawTableCell(
                $pdf,
                $this->formatAmountCell($row['sozoku_after'] ?? null),
                $xx,
                $rowY['sozoku_after'],
                $colWidth,
                $rowHeight,
                'R'
            );

            // 対策後 - 贈与税累計額
            $this->drawTableCell(
                $pdf,
                $this->formatAmountCell($row['gift_after'] ?? null),
                $xx,
                $rowY['gift_after'],
                $colWidth,
                $rowHeight,
                'R'
            );

            // 対策後 - 合計
            $this->drawTableCell(
                $pdf,
                $this->formatAmountCell($row['total_after'] ?? null),
                $xx,
                $rowY['total_after'],
                $colWidth,
                $rowHeight,
                'R'
            );

            // 減税額
            $this->drawTableCell(
                $pdf,
                $this->formatAmountCell($row['diff'] ?? null),
                $xx,
                $rowY['diff'],
                $colWidth,
                $rowHeight,
                'R'
            );
        }
    }
    
    
 
    /**
     * 上半分グラフ
     * - 横軸は 5年後 / 10年後 / 15年後 / 20年後 固定
     * - 各年ごとに「対策前」「対策後」を比較
     * - 棒は積み上げ（下: 相続税額 / 上: 贈与税額）
     * - グラフの元データは下表と同じ effectRows をそのまま使用
     */
    private function renderStackedComparisonChart(TCPDF $pdf, array $effectRows): void
    {
        $graphRows = $this->buildGraphRows($effectRows);


        $totalValues = [];
        $baseValues  = [];
        foreach ($graphRows as $row) {
            $totalValues[] = (int)$row['sozoku_before'] + (int)$row['gift_before'];
            $totalValues[] = (int)$row['sozoku_after']  + (int)$row['gift_after'];
            $baseValues[]  = (int)$row['sozoku_before'];
            $baseValues[]  = (int)$row['sozoku_after'];
        }

        $minBaseValue  = empty($baseValues)  ? 0 : min($baseValues);
        $maxTotalValue = empty($totalValues) ? 0 : max($totalValues);

        [$axisMin, $axisMax, $tickStep] = $this->calculateChartAxis(
            $minBaseValue,
            $maxTotalValue
        );        
 
        // 上半分のグラフ領域
        $chartX = 33.0;
        $chartY = 42.0;
        $chartW = 344.0;
        $chartH = 132.0;

        // 目盛り・ラベル分を差し引いたプロット領域
        $plotLeft   = 26.0;
        $plotRight  = 6.0;
        $plotTop    = 10.0;
        $plotBottom =  5.0;


        $plotX = $chartX + $plotLeft;
        $plotY = $chartY + $plotTop;
        $plotW = $chartW - $plotLeft - $plotRight;
        $plotH = $chartH - $plotTop - $plotBottom;


        // カラー設定
        // - プロットエリア: 極薄いクリーム
        // - 相続税額: 淡い緑
        // - 贈与税額: 淡い赤
        // 明度差を少しつけて、モノクロ印刷時でも判別しやすくする

        //プロットエリア
        //極薄いクリーム色
        $plotFillColor   = [255, 252, 242];

        //相続税額
        //淡い緑色
        $sozokuFillColor = [218, 236, 214];
        //斜線色は緑の濃い色
        //相続税額: RGB(92, 122, 86) の濃い緑
        $sozokuHatchColor = [92, 122, 86];        
        //右斜め線
        $sozokuHatch     = 'right';        


        //贈与税額
        //淡い赤色
        $giftFillColor   = [236, 198, 198];
        //斜線色は赤の濃い色
        //贈与税額: RGB(146, 92, 92) の濃い赤
        $giftHatchColor  = [146, 92, 92];        
        //左斜め線
        $giftHatch       = 'left';


        // 棒の上に置く「対策前」「対策後」がプロット上端を超えそうなら、
        // 上限額を1目盛ずつ上げて上方向の余白を確保する
        $labelOffsetY    = 8.0;
        $labelTopReserve = 2.0;
        $adjustCount     = 0;
        $maxAdjustCount  = 10;

        $highestLabelTextY = $this->valueToChartY($maxTotalValue, $axisMin, $axisMax, $plotY, $plotH) - $labelOffsetY;
        while ($highestLabelTextY < ($plotY + $labelTopReserve) && $adjustCount < $maxAdjustCount) {
            $axisMax += $tickStep;
            $adjustCount++;
            $highestLabelTextY = $this->valueToChartY($maxTotalValue, $axisMin, $axisMax, $plotY, $plotH) - $labelOffsetY;
        }


        // 枠・目盛り
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor($plotFillColor[0], $plotFillColor[1], $plotFillColor[2]);
        $pdf->Rect($plotX, $plotY, $plotW, $plotH, 'DF');

        $pdf->SetFont('mspgothic03', '', 12.0);        
        $pdf->SetTextColor(0, 0, 0);

        for ($value = $axisMin; $value <= $axisMax; $value += $tickStep) {
            $yy = $this->valueToChartY($value, $axisMin, $axisMax, $plotY, $plotH);

            // プロットエリアの上線・下線は黒、それ以外の補助線はグレー
            if ($value === $axisMin || $value === $axisMax) {
                $pdf->SetDrawColor(0, 0, 0);
            } else {
                $pdf->SetDrawColor(210, 210, 210);
            }
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
            $plotX - 10.0,
            $chartY + 1.0,
            true,
            0,
            false,
            true,
            6,
            'M'
        );

        // 凡例
        $legendTotalW = 82.0;
        $legendX = $chartX + (($chartW - $legendTotalW) / 2.0) + 10.0;
        $legendY = $chartY + 1.0;

        $this->drawLegendSwatch($pdf, $legendX, $legendY, 5.0, 3.5, $sozokuFillColor, $sozokuHatch, $sozokuHatchColor);
        $pdf->MultiCell(28, 6, '相続税額', 0, 'L', 0, 0, $legendX + 7.0, $legendY - 1.2, true, 0, false, true, 6, 'M');
 

        $this->drawLegendSwatch($pdf, $legendX + 42.0, $legendY, 5.0, 3.5, $giftFillColor, $giftHatch, $giftHatchColor);
        $pdf->MultiCell(28, 6, '贈与税額', 0, 'L', 0, 0, $legendX + 49.0, $legendY - 1.2, true, 0, false, true, 6, 'M');


        // 棒グラフ描画
        $groupCount = max(1, count($graphRows));
        $groupWidth = $plotW / $groupCount;
        $barGap     = 6.0;
        $baseBarWidth = min(14.0, max(10.0, ($groupWidth - 18.0) / 2.0));
        $barWidth     = min(
            $baseBarWidth * 2.0,
            ($groupWidth - $barGap - 4.0) / 2.0
        );


        foreach ($graphRows as $i => $row) {
            $groupStartX = $plotX + ($groupWidth * $i);
            $centerX     = $groupStartX + ($groupWidth / 2.0);

            $beforeX = $centerX - ($barGap / 2.0) - $barWidth;
            $afterX  = $centerX + ($barGap / 2.0);
            $baseY   = $plotY + $plotH;

            $this->drawStackedBar(
                $pdf,
                $beforeX,
                $barWidth,
                $plotY,
                $plotH,
                $axisMin,
                $axisMax,
                [
                    ['value' => (int)$row['sozoku_before'], 'fill' => $sozokuFillColor, 'hatch' => $sozokuHatch, 'hatch_color' => $sozokuHatchColor],
                    ['value' => (int)$row['gift_before'],   'fill' => $giftFillColor,   'hatch' => $giftHatch,   'hatch_color' => $giftHatchColor],
                ]
            );

            $this->drawStackedBar(
                $pdf,
                $afterX,
                $barWidth,
                $plotY,
                $plotH,
                $axisMin,
                $axisMax,
                [
                    ['value' => (int)$row['sozoku_after'], 'fill' => $sozokuFillColor, 'hatch' => $sozokuHatch, 'hatch_color' => $sozokuHatchColor],
                    ['value' => (int)$row['gift_after'],   'fill' => $giftFillColor,   'hatch' => $giftHatch,   'hatch_color' => $giftHatchColor],
                ]
            );


            $beforeTotal = (int)$row['sozoku_before'] + (int)$row['gift_before'];
            $afterTotal  = (int)$row['sozoku_after']  + (int)$row['gift_after'];

            $beforeTopY = $baseY;
            if ($beforeTotal > 0) {
                $beforeTopY = $this->valueToChartY($beforeTotal, $axisMin, $axisMax, $plotY, $plotH);
            }

            $afterTopY = $baseY;
            if ($afterTotal > 0) {
                $afterTopY = $this->valueToChartY($afterTotal, $axisMin, $axisMax, $plotY, $plotH);
            }

            //差額を点線で
            $this->drawDifferenceGuide(
                $pdf,
                $beforeX,
                $afterX,
                $barWidth,
                $beforeTotal,
                $afterTotal,
                $axisMin,
                $axisMax,
                $plotY,
                $plotH
            );

 
            $labelTopY = min($beforeTopY, $afterTopY);



            $pdf->SetFont('mspgothic03', '', 12.0);

            $pdf->MultiCell(
                $barWidth + 16.0,
                6,
                '対策前',
                0,
                'C',
                0,
                0,
                $beforeX - 8.0,
                $labelTopY - $labelOffsetY,
                true,
                0,
                false,
                true,
                6,
                'M'
            );

            $pdf->MultiCell(
                $barWidth + 16.0,
                6,
                '対策後',
                0,
                'C',
                0,
                0,
                $afterX - 8.0,
                $labelTopY - $labelOffsetY,                
                true,
                0,
                false,
                true,
                6,
                'M'
            );

            //横軸　項目名　5年後 / 10年後 / 15年後 / 20年後
            $pdf->MultiCell($groupWidth, 6, $row['label'], 0, 'C', 0, 0, $groupStartX, $baseY + 2.0, true, 0, false, true, 6, 'M');
        
        
        }
    }

    private function buildGraphRows(array $effectRows): array
    {
        $rows = [];

        foreach ([5, 10, 15, 20] as $year) {
            $src = $effectRows[$year] ?? [];

            $rows[] = [
                'label'         => $year . '年後',
                'sozoku_before' => (int)($src['sozoku_before'] ?? 0),
                'gift_before'   => (int)($src['gift_before'] ?? 0),
                'sozoku_after'  => (int)($src['sozoku_after'] ?? 0),
                'gift_after'    => (int)($src['gift_after'] ?? 0),
            ];
        }

        return $rows;
    }

    private function calculateChartAxis(int $minBaseValue, int $maxTotalValue): array
    {

        if ($maxTotalValue <= 0) {
            return [0, 1000, 200];
        }
 
        $range = max(1, $maxTotalValue - $minBaseValue);
        $rawStep = max(1, (int)ceil($range / 4));
        $magnitude = (int)pow(10, floor(log10((float)$rawStep)));
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

         // 上限は合計額が窮屈にならないよう少しだけ余白を持たせる
         $axisMax = (int)(ceil(($maxTotalValue + ($tickStep * 0.2)) / $tickStep) * $tickStep);

        // 基本は「上限額の約3分の2」を下限候補にする
        $twoThirdsAxisMin = max(0, (int)(floor((($axisMax * 2) / 3) / $tickStep) * $tickStep));

        // ただし、それだと上段の贈与税額が切れる場合があるため、
        // 贈与税額がフルで見える位置（= 下段の相続税額の最小値）まで下げられるようにする
        $giftVisibleAxisMin = max(0, (int)(floor(max(0, $minBaseValue) / $tickStep) * $tickStep));

        $axisMin = min($twoThirdsAxisMin, $giftVisibleAxisMin);        
 


         if ($axisMax <= $axisMin) {
            $axisMax = (int)(ceil(($axisMin + ($tickStep * 4)) / $tickStep) * $tickStep);
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



     private function drawStackedBar(
         TCPDF $pdf,
         float $x,
         float $width,
        float $plotY,
         float $plotHeight,
        int $axisMin,
         int $axisMax,
         array $segments
     ): void {
        $segmentBottom = 0;
 
         foreach ($segments as $segment) {
             $value = max(0, (int)($segment['value'] ?? 0));
            if ($value <= 0 || $axisMax <= $axisMin) {
                $segmentBottom += $value;
                 continue;
             }
 
            $segmentTop    = $segmentBottom + $value;
            $visibleBottom = max($segmentBottom, $axisMin);
            $visibleTop    = min($segmentTop, $axisMax);

            if ($visibleTop <= $visibleBottom) {
                $segmentBottom = $segmentTop;
                 continue;
             }
 
            $yTop    = $this->valueToChartY($visibleTop, $axisMin, $axisMax, $plotY, $plotHeight);
            $yBottom = $this->valueToChartY($visibleBottom, $axisMin, $axisMax, $plotY, $plotHeight);
            $height  = $yBottom - $yTop;

            if ($height <= 0) {
                $segmentBottom = $segmentTop;
                continue;
            }

            $fillColor = $segment['fill'] ?? [255, 255, 255];
            $hatch     = $segment['hatch'] ?? '';
            $hatchColor = $segment['hatch_color'] ?? [90, 90, 90];            
            [$r, $g, $b] = $fillColor;
  
            $pdf->SetFillColor($r, $g, $b);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x, $yTop, $width, $height, 'DF');

            if (is_string($hatch) && $hatch !== '') {
                $this->drawHatchPattern($pdf, $x, $yTop, $width, $height, $hatch, $hatchColor);
            }

            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x, $yTop, $width, $height, 'D');
  
            $segmentBottom = $segmentTop;
        }
    }



    

    private function drawTableCell(
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

    private function formatAmountCell($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return (string)$value;
        }

        return number_format((int)round((float)$value));
    }

    private function yenToKyen($value): int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return 0;
        }

        return (int)round(((int)$value) / 1000);
    }

    private function pickFirstNumericValue(array $source, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = data_get($source, $key);
            if ($value === null || $value === '') {
                continue;
            }
            if (is_numeric($value)) {
                return (int)$value;
            }
        }

        return null;
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

        \Log::warning('[A3ZouyoGenzeikokaPageService] resultsData could not be resolved', [
            'payload_keys' => array_keys($payload),
        ]);

        return [];
    }

    private function normalizeResultsData(array $data): array
    {
        $paths = [
            $data,
            $data['resultsData']    ?? null,
            $data['results']        ?? null,
            $data['zouyo_results']  ?? null,
            $data['calc_results']   ?? null,
            $data['data']           ?? null,
            $data['zouyo']          ?? null,
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

    private function hasUsableResultsData(array $data): bool
    {
        return
            !empty($data['after']['summary'] ?? []) ||
            !empty($data['before']['summary'] ?? []) ||
            !empty($data['projections']['after'] ?? []) ||
            !empty($data['after']['projections']['after'] ?? []) ||
            !empty($data['after']['projections'] ?? []) ||
            !empty($data['projections']['before'] ?? []) ||
            !empty($data['before']['projections']['before'] ?? []) ||
            !empty($data['before']['projections'] ?? []);
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
            $n = (int)preg_replace('/[^\d\-]/', '', (string)$cand);
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
                (int)($beforeRootSummary['total_gift_tax_credits'] ?? 0)
                + (int)($beforeRootSummary['total_settlement_gift_taxes'] ?? 0);
        }

        $rows = [];

        for ($t = 0; $t <= 20; $t++) {
            $beforeBundle  = $this->resolveBeforeTrendBundle($r, $t);
            $afterBundle   = $this->resolveAfterTrendBundle($r, $t);
            $beforeSummary = is_array($beforeBundle['summary'] ?? null) ? $beforeBundle['summary'] : [];
            $afterSummary  = is_array($afterBundle['summary'] ?? null) ? $afterBundle['summary'] : [];

            if ($t === 0 && (empty($beforeSummary) || empty($afterSummary))) {
                \Log::warning('[A3ZouyoGenzeikokaPageService] effect summary is empty', [
                    'data_id'            => $dataId,
                    'has_before_summary' => !empty($beforeSummary),
                    'has_after_summary'  => !empty($afterSummary),
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
                    (int)($afterSummary['total_gift_tax_credits'] ?? 0)
                    + (int)($afterSummary['total_settlement_gift_taxes'] ?? 0);
            }

            $giftBeforeYen  = (int)$baseGiftBeforeYen;
            $totalBeforeYen = $sozokuBeforeYen + $giftBeforeYen;
            $totalAfterYen  = $sozokuAfterYen + (int)$giftAfterYen;
            $diffYen        = $totalAfterYen - $totalBeforeYen;

            $rows[] = [
                'nenji'         => ($t === 0) ? '現時点' : ($t . '年後'),
                'age'           => is_int($baseAge) ? ($baseAge + $t) . '歳' : '',
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

    
    // 差額部分を点線で囲む
    private function drawDifferenceGuide(
        TCPDF $pdf,
        float $beforeX,
        float $afterX,
        float $barWidth,
        int $beforeTotal,
        int $afterTotal,
        int $axisMin,
        int $axisMax,
        float $plotY,
        float $plotH
    ): void {


        if ($beforeTotal <= 0 || $afterTotal <= 0 || $axisMax <= $axisMin) {
            return;
        }

        // 「対策後」の方が税額が多い場合は、
        // 差額の点線表示・棒間の直線表示は行わない
        if ($afterTotal > $beforeTotal) {
            return;
        }

        $beforeY = $this->valueToChartY($beforeTotal, $axisMin, $axisMax, $plotY, $plotH);
        $afterY  = $this->valueToChartY($afterTotal, $axisMin, $axisMax, $plotY, $plotH);
        $topY    = min($beforeY, $afterY);
        $bottomY = max($beforeY, $afterY);

        // 直線は「対策前」棒の右端 → 「対策後」棒の左端
        $lineStartX = $beforeX + $barWidth;
        $lineEndX   = $afterX;


        // 点線枠は「対策後」棒の左端〜右端に描く
        $leftX  = $afterX;
        $rightX = $afterX + $barWidth;

        if ($rightX <= $leftX || $bottomY <= $topY || $lineEndX <= $lineStartX) {
            return;
        }

        // 差額部分を点線で囲む
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineStyle([
            'width' => 0.25,
            'cap'   => 'butt',
            'join'  => 'miter',
            'dash'  => '2,2',
            'color' => [0, 0, 0],
        ]);
        $pdf->Rect($leftX, $topY, $rightX - $leftX, $bottomY - $topY, 'D');

        // 対策前・対策後の差額位置を棒の間で点線表示
        $pdf->SetLineStyle([
            'width' => 0.25,
            'cap'   => 'butt',
            'join'  => 'miter',
            'dash'  => '2,2',       //0は実線
            'color' => [0, 0, 0],
        ]);
        $pdf->Line($lineStartX, $beforeY, $lineEndX, $beforeY);
        $pdf->Line($lineStartX, $afterY,  $lineEndX, $afterY);        

        // 以降の描画へ影響しないよう実線に戻す
        $pdf->SetLineStyle([
            'width' => 0.2,
            'cap'   => 'butt',
            'join'  => 'miter',
            'dash'  => 0,
            'color' => [0, 0, 0],
        ]);
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

    //グラフに斜め線模様を付ける
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

}