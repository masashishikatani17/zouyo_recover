<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Models\FutureGiftHeader;
use App\Models\FutureGiftPlanEntry;
use App\Models\FutureGiftRecipient;
use App\Models\PastGiftCalendarEntry;
use App\Models\PastGiftSettlementEntry;
use App\Models\ProposalHeader;
use App\Models\ProposalFamilyMember;
use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;

/**
 * 4: 各人別贈与プラン
 */
class A3KakuzoyoPlanPageService implements ZouyoPdfPageInterface
{
    public function render(TCPDF $pdf, array $payload): void
    {
        $templatePath = resource_path('/views/pdf/A3_04_pr_kakuzoyoplan.pdf');

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Gift plan template not found: {$templatePath}");
        }

        $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);

        $pdf->AddPage();
        $pdf->useTemplate($tpl);
        $size = $pdf->getTemplateSize($tpl);

        $wakusen = 0;
        $dataId = (int)($payload['data_id'] ?? 0);
        $family = $payload['family'] ?? [];

        // ページ番号
        $pageLabelW   = 30;
        $pageLabelH   = 6;
        $rightMargin  = 20;
        $bottomMargin = 15;

        $x = max(0, $size['width'] - $pageLabelW - $rightMargin);
        $y = max(0, $size['height'] - $pageLabelH - $bottomMargin);

        $pdf->SetFont('mspgothic03', '', 10);
        $pdf->MultiCell($pageLabelW, $pageLabelH, '４ページ', $wakusen, 'R', 0, 0, $x, $y);

        if ($dataId <= 0) {
            return;
        }

        $targets = $this->buildGiftPlanTargets($dataId, $family);
        $slotRecipientNos = range(2, 10);
        $deathYear = $this->resolveDisplayDeathYear($payload, $dataId);
        $yearKeys = $this->giftPlanYearKeys($deathYear);

        $matrix = $this->initGiftPlanMatrix($slotRecipientNos, $yearKeys);

        $recipientNos = array_keys($targets);
        if ($recipientNos !== []) {
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
        }

        $rowTotals = [];
        foreach ($slotRecipientNos as $recipientNo) {
            $rowTotals[$recipientNo] = [
                'calendar'   => 0,
                'settlement' => 0,
                'total'      => 0,
            ];

            foreach ($yearKeys as $yearKey) {
                $calendarValue = (int)($matrix[$recipientNo][$yearKey]['calendar'] ?? 0);
                $settlementValue = (int)($matrix[$recipientNo][$yearKey]['settlement'] ?? 0);

                $rowTotals[$recipientNo]['calendar'] += $calendarValue;
                $rowTotals[$recipientNo]['settlement'] += $settlementValue;
                $rowTotals[$recipientNo]['total'] += ($calendarValue + $settlementValue);
            }
        }

        $colTotals = [];
        foreach ($yearKeys as $yearKey) {
            $colTotals[$yearKey] = [
                'calendar'   => 0,
                'settlement' => 0,
                'total'      => 0,
            ];

            foreach ($slotRecipientNos as $recipientNo) {
                $calendarValue = (int)($matrix[$recipientNo][$yearKey]['calendar'] ?? 0);
                $settlementValue = (int)($matrix[$recipientNo][$yearKey]['settlement'] ?? 0);

                $colTotals[$yearKey]['calendar'] += $calendarValue;
                $colTotals[$yearKey]['settlement'] += $settlementValue;
                $colTotals[$yearKey]['total'] += ($calendarValue + $settlementValue);
            }
        }

        $grandTotals = [
            'calendar'   => 0,
            'settlement' => 0,
            'total'      => 0,
        ];
        foreach ($rowTotals as $totals) {
            $grandTotals['calendar'] += (int)($totals['calendar'] ?? 0);
            $grandTotals['settlement'] += (int)($totals['settlement'] ?? 0);
            $grandTotals['total'] += (int)($totals['total'] ?? 0);
        }

        // レイアウト（A3_04_pr_kakuzoyoplan.pdf の罫線に合わせる）
        $leftX        = 12.8;
        $nameW        = 21.0;
        $dataStartX   = 49.4;
        $colW         = 14.30;
        $totalW       = 15.2;
        $giftYearY    = 47.0;
        $ageY         = 54.0;
        $bodyStartY   = 60.5;
        $rowH         = 6.95;
        $blockH       = $rowH * 3;
        $donorNameX   = 64.4;
        $donorNameY   = 30.3;
        $donorNameW   = 27.5;

        $pdf->SetTextColor(0, 0, 0);

        // 贈与者名
        $giftName = $this->resolveDonorName($dataId, $family);
        if ($giftName !== '') {
            $pdf->SetFont('mspgothic03', '', 10);
            $pdf->MultiCell($donorNameW, 6, $giftName, $wakusen, 'C', 0, 0, $donorNameX, $donorNameY);
        }

        //コメント
        [$futureGiftMonth, $futureGiftDay] = $this->resolveFirstGiftMonthDay($payload, $dataId);
         
        
        
        $strCom = '※';
        $strCom .= sprintf(
            '贈与は毎年%d月%d日に実施するものとします。なお、相続発生の現時点とは提案書の作成日付です。',
            $futureGiftMonth,
            $futureGiftDay
        );

        if ($strCom !== '') {
            $pdf->SetFont('mspgothic03', '', 12);
            $pdf->MultiCell(250, 6, $strCom, $wakusen, 'L', 0, 0, 110.0, 31.5);
        }



        // 贈与年 / 年齢
        $pdf->SetFont('mspgothic03', '', 8.5);
        $x = $dataStartX;
        $ageBase = $this->resolveDonorAge($dataId, $family);

        foreach ($yearKeys as $yearKey) {
            if (!str_starts_with($yearKey, 'before_')) {
                $pdf->MultiCell($colW, 5, (string)$yearKey, $wakusen, 'C', 0, 0, $x, $giftYearY);

                if ($ageBase !== null) {
                    $ageBase++;
                    $pdf->MultiCell($colW, 5, (string)$ageBase . '歳', $wakusen, 'C', 0, 0, $x, $ageY);
                }
            }

            $x += $colW;
        }

        // 受贈者別ブロック
        $pdf->SetFont('mspgothic03', '', 8.5);
        foreach ($slotRecipientNos as $slotIndex => $recipientNo) {
            $displayName = trim((string)($targets[$recipientNo] ?? ''));
            if ($displayName === '') {
                continue;
            }

            $blockY = $bodyStartY + ($blockH * $slotIndex);

            $pdf->SetFont('mspgothic03', '', 9);
            $pdf->MultiCell($nameW, 6, $displayName, $wakusen, 'L', 0, 0, $leftX, $blockY + $rowH);

            $pdf->SetFont('mspgothic03', '', 8.5);
            $x = $dataStartX;
            foreach ($yearKeys as $yearKey) {
                $calendarValue   = (int)($matrix[$recipientNo][$yearKey]['calendar'] ?? 0);
                $settlementValue = (int)($matrix[$recipientNo][$yearKey]['settlement'] ?? 0);
                $totalValue      = $calendarValue + $settlementValue;

                $pdf->MultiCell(
                    $colW,
                    5.5,
                    $this->formatCalendarPlanAmountByYearKey($yearKey, $calendarValue),
                    $wakusen,
                    'R',
                    0,
                    0,
                    $x,
                    $blockY + 0.8
                );

                $pdf->MultiCell(
                    $colW,
                    5.5,
                    $this->formatPlanAmount($settlementValue),
                    $wakusen,
                    'R',
                    0,
                    0,
                    $x,
                    $blockY + $rowH + 0.8
                );

                $pdf->MultiCell(
                    $colW,
                    5.5,
                    $this->formatPlanAmount($totalValue),
                    $wakusen,
                    'R',
                    0,
                    0,
                    $x,
                    $blockY + ($rowH * 2) + 0.8
                );

                $x += $colW;
            }

        $pdf->MultiCell(
                $totalW,
                5.5,
                $this->formatPlanAmount((int)($rowTotals[$recipientNo]['calendar'] ?? 0)),
                $wakusen,
                'R',
                0,
                0,
                $x,
                $blockY + 0.8
            );

            $pdf->MultiCell(
            $totalW,
                5.5,
                $this->formatPlanAmount((int)($rowTotals[$recipientNo]['settlement'] ?? 0)),
                $wakusen,
                'R',
                0,
                0,
                $x,
                $blockY + $rowH + 0.8
            );

            $pdf->MultiCell(
                $totalW,
                5.5,
                $this->formatPlanAmount((int)($rowTotals[$recipientNo]['total'] ?? 0)),
                $wakusen,
                'R',
                0,
                0,
                $x,
                $blockY + ($rowH * 2) + 0.8
            );
        }

        // 最下段：合計
        $totalY = $bodyStartY + ($blockH * count($slotRecipientNos));
        $x = $dataStartX;

        foreach ($yearKeys as $yearKey) {
            $calendarTotal   = (int)($colTotals[$yearKey]['calendar'] ?? 0);
            $settlementTotal = (int)($colTotals[$yearKey]['settlement'] ?? 0);
            $sumTotal        = (int)($colTotals[$yearKey]['total'] ?? 0);

        $pdf->MultiCell(
                $colW,
                5.5,
                $this->formatCalendarPlanAmountByYearKey($yearKey, $calendarTotal),
                $wakusen,
                'R',
                0,
                0,
                $x,
                $totalY + 0.8
            );

            $pdf->MultiCell(
                $colW,
                5.5,
                $this->formatPlanAmount($settlementTotal),
                $wakusen,
                'R',
                0,
                0,
                $x,
                $totalY + $rowH + 0.8
            );

            $pdf->MultiCell(
                $colW,
                5.5,
                $this->formatPlanAmount($sumTotal),
                $wakusen,
                'R',
                0,
                0,
                $x,
                $totalY + ($rowH * 2) + 0.8
            );

            $x += $colW;
        }

        $pdf->MultiCell(
            $totalW,
            5.5,
            $this->formatPlanAmount((int)$grandTotals['calendar']),
            $wakusen,
            'R',
            0,
            0,
            $x,
            $totalY + 0.8
        );

        $pdf->MultiCell(
            $totalW,
            5.5,
            $this->formatPlanAmount((int)$grandTotals['settlement']),
            $wakusen,
            'R',
            0,
            0,
            $x,
            $totalY + $rowH + 0.8
        );

        $pdf->MultiCell(
            $totalW,
            5.5,
            $this->formatPlanAmount((int)$grandTotals['total']),
            $wakusen,
            'R',
            0,
            0,
            $x,
            $totalY + ($rowH * 2) + 0.8
        );
    }

    /**
     * recipient_no 2-10 の受贈者名を組み立てる。
     * 優先順位：
     *   1. payload family
     *   2. proposal_family_members
     *   3. future_gift_recipients
     *
     * @return array<int,string>
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

            if ($name === '') {
                continue;
            }

            $targets[$recipientNo] = $name;
        }

        return $targets;
    }

    /**
     * 贈与者名（row_no=1）を取得する。
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

        return trim((string)($familyRow->name ?? ''));
    }

    /**
     * 贈与者年齢（row_no=1）を取得する。
     * 相続開始年-4 から開始し、相続開始年-3 以降を順に表示する。
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

            $age -= 4;

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
     * @param array<int,int> $recipientNos
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
     * 未来分の年を決定する。
     */
    private function resolveFutureGiftYear(int $deathYear, ?int $giftYear, ?int $rowNo): ?int
    {
        $resolvedYear = $giftYear;

        if ($resolvedYear === null || $resolvedYear <= 0) {
            if ($rowNo === null || $rowNo <= 0) {
                return null;
            }

            $resolvedYear = $deathYear + $rowNo - 1;
        }

        if ($resolvedYear < $deathYear || $resolvedYear > ($deathYear + 19)) {
            return null;
        }

        return $resolvedYear;
    }

    /**
     * 表示用の相続開始年を解決する。
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
     * save 済みの future_gift_headers を最優先で参照する。
     * payload は補助的なフォールバックにとどめる。
     * 値が取得できない場合のみ従来どおり 1/1 を既定値とする。     * 
     *
     * @return array{0:int,1:int}
     */
    private function resolveFirstGiftMonthDay(array $payload, int $dataId): array
     {

        if ($dataId > 0) {
            $header = FutureGiftHeader::query()
                ->where('data_id', $dataId)
                ->first(['base_month', 'base_day']);

            if ($header) {
                $month = $this->toIntValue($header->base_month ?? null);
                $day   = $this->toIntValue($header->base_day ?? null);

                if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                    return [$month, $day];
                }
            }
        }
        
        $month = $this->toIntValue(
            $payload['header']['month']
                ?? $payload['future_base_month']
                ?? $payload['header_month']
                ?? null
        );

        $day = $this->toIntValue(
            $payload['header']['day']
                ?? $payload['future_base_day']
                ?? $payload['header_day']
                ?? null
        );

        if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
            return [$month, $day];
        }

        return [1, 1];         //1月1日

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
     * - before_XXXX 列のみ、0 は空白
     * - それ以外の年は 0 も表示
     */
    private function formatCalendarPlanAmountByYearKey(string $yearKey, int $value): string
    {
        if (str_starts_with($yearKey, 'before_') && $value === 0) {
            return '';
        }

        return number_format($value);
    }
}