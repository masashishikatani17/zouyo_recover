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
        $pdf->MultiCell(30, 6, '(8ページ)', $wakusen, 'R', 0, 0, $x, $y);

        $layout = $this->buildLayout();
        $series = $this->buildSeries($targets, $resultsData);

        $this->drawBlock($pdf, $layout['blocks']['before'],   $layout, $targets, $series['before'],   $series['totals']['before']);
        $this->drawBlock($pdf, $layout['blocks']['increase'], $layout, $targets, $series['increase'], $series['totals']['increase']);
        $this->drawBlock($pdf, $layout['blocks']['after'],    $layout, $targets, $series['after'],    $series['totals']['after']);
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
                    'body_top_y'    => 34.0,
                    'body_bottom_y' => 79.2,
                    'total_y'       => 81.0,
                ],
                'increase' => [
                    'body_top_y'    => 86.0,
                    'body_bottom_y' => 131.4,
                    'total_y'       => 133.2,
                ],
                'after' => [
                    'body_top_y'    => 138.0,
                    'body_bottom_y' => 183.3,
                    'total_y'       => 185.1,
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