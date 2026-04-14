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
}