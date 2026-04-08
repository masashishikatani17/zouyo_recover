<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Models\FutureGiftPlanEntry;
use App\Models\FutureGiftRecipient;
use App\Models\PastGiftCalendarEntry;
use App\Models\PastGiftSettlementEntry;
use App\Models\ProposalFamilyMember;
use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;

/**
 * 4: 家族構成、所有財産など
 */
class A3FamilyGiftPlanPageService implements ZouyoPdfPageInterface
{
    public function render(TCPDF $pdf, array $payload): void
    {
        // テンプレートPDFのパス
        $templatePath = resource_path('/views/pdf/A3_03_pr_kazokukosei.pdf');

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
        $startX    = 45;
        $startY    = 54.5;
        $rowHeight = 5.78;

        $colX = [
            'no'           => $startX,
            'name'         => $startX + 8,
            'gender'       => $startX + 38,
            'rel'          => $startX + 49,
            'yousi'        => $startX + 70,
            'souzoku'      => $startX + 95,
            'civil_share'  => $startX + 112,
            'houtei_share' => $startX + 128,
            'birth_year'   => $startX + 144,
            'birth_month'  => $startX + 151,
            'birth_day'    => $startX + 158.5,
            'age'          => $startX + 183.5,
            'cash'         => $startX + 191,
            'prop'         => $startX + 227,
            'ksum'         => $startX + 263,
        ];

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
            '(3ページ)',
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
            $pdf->MultiCell(28, 10, $name, $wakusen, 'L', 0, 0, $x, $y);            
            

            if ($hasName) {
                $x = $colX['gender'];
                $pdf->MultiCell(10, 10, (string)($row['gender'] ?? ''), $wakusen, 'C', 0, 0, $x, $y);
            }


            if ($hasName) {
                $x = $colX['rel'];
                $relCode = $row['relationship_code'] ?? null;
                $relLabel = $relCode !== null && array_key_exists($relCode, $relationships)
                    ? $relationships[$relCode]
                    : '';
                $relFontSize = $this->resolveRelationshipFontSize($relLabel);
                $pdf->SetFont('mspgothic03', '', $relFontSize);
                $pdf->MultiCell(22, 10, $relLabel, $wakusen, 'L', 0, 0, $x, $y);
                $pdf->SetFont('mspgothic03', '', 9);
             }
             

            if ($hasName) {
                $x = $colX['yousi'];
                $pdf->MultiCell(20, 10, (string)($row['yousi'] ?? ''), $wakusen, 'L', 0, 0, $x, $y);
            }


            if ($hasName) {
                $x = $colX['souzoku'];
                $pdf->MultiCell(30, 10, (string)($row['souzokunin'] ?? ''), $wakusen, 'L', 0, 0, $x, $y);
            }



            if ($hasName && (($row['bunsi'] ?? null) !== null)) {                
                
                $x = $colX['houtei_share'];
                $pdf->MultiCell(
                    20,
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
                $pdf->MultiCell(20, 10, (string)($row['birth_year'] ?? '') . '年', $wakusen, 'R', 0, 0, $x, $y);
            }


            if ($hasName && (($row['birth_month'] ?? null) !== null)) {
                $x = $colX['birth_month'];
                $pdf->MultiCell(20, 10, (string)($row['birth_month'] ?? '') . '月', $wakusen, 'R', 0, 0, $x, $y);
            }


            if ($hasName && (($row['birth_day'] ?? null) !== null)) {
                $x = $colX['birth_day'];
                $pdf->MultiCell(20, 10, (string)($row['birth_day'] ?? '') . '日', $wakusen, 'R', 0, 0, $x, $y);
            }

            $age = $row['age'] ?? null;
            if ($hasName && $age !== null) {
                $x = $colX['age'];
                $pdf->MultiCell(10, 10, (string)$age . '歳', $wakusen, 'L', 0, 0, $x, $y);
            }

            $cash = $row['cash'] ?? null;
            if ($hasName && $cash !== null) {
                $x = $colX['cash'];
                $pdf->MultiCell(30, 10, number_format((int)$cash), $wakusen, 'R', 0, 0, $x, $y);
            }

            $prop = ($row['property'] ?? 0) - ($row['cash'] ?? 0);
            if ($hasName && $prop !== null) {
                $x = $colX['prop'];
                $pdf->MultiCell(30, 10, number_format((int)$prop), $wakusen, 'R', 0, 0, $x, $y);
            }

            $ksum = ($row['property'] ?? 0);
            if ($hasName && $prop !== null) {                
                $x = $colX['ksum'];
                $pdf->MultiCell(30, 10, number_format((int)$ksum), $wakusen, 'R', 0, 0, $x, $y);
            }
        }

        $pdf->SetFont('mspgothic03', '', 9);

        // 合計行
        $totalCash = $header['cash_110'] ?? null;
        $totalProp = ($header['property_110'] ?? 0) - ($header['cash_110'] ?? 0);
        $totaksum  = $header['property_110'] ?? null;

        if ($totalProp !== null || $totalCash !== null) {
            $y = $startY + 10 * $rowHeight;

            if ($totalCash !== null) {
                $x = $colX['cash'];
                $pdf->MultiCell(30, 10, number_format((int)$totalCash), $wakusen, 'R', 0, 0, $x, $y);
            }

            if ($totalProp !== null) {
                $x = $colX['prop'];
                $pdf->MultiCell(30, 10, number_format((int)$totalProp), $wakusen, 'R', 0, 0, $x, $y);
            }

            if ($totaksum !== null) {
                $x = $colX['ksum'];
                $pdf->MultiCell(30, 10, number_format((int)$totaksum), $wakusen, 'R', 0, 0, $x, $y);
            }
        }

        // ▼ 家族表の下に「各人別贈与プラン」を描画
        $this->renderEachRecipientGiftPlanTable($pdf, $payload, $family);
    }

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

        $recipientNos = array_keys($targets);
        $yearKeys     = $this->giftPlanYearKeys();
        $matrix       = $this->initGiftPlanMatrix($recipientNos, $yearKeys);

        // ▼ 過年度：暦年贈与
        $pastCalendarRows = PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->whereIn('recipient_no', $recipientNos)
            ->whereNotNull('gift_year')
            ->get(['recipient_no', 'gift_year', 'amount_thousand']);

        foreach ($pastCalendarRows as $row) {
            $recipientNo = (int)($row->recipient_no ?? 0);
            $bucket = $this->resolvePastGiftBucket($this->toIntValue($row->gift_year));

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
            $bucket = $this->resolvePastGiftBucket($this->toIntValue($row->gift_year));
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
            $label = $yearKey === 'before_2022' ? '～2021' : (string)$yearKey;
            
            if ($label === '～2021'){
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
     *  - before_2022
     *  - 2022 / 2023 / 2024
     *  - 2025-2044
     *
     * @return array<int,string>
     */
    private function giftPlanYearKeys(): array
    {
        $keys = ['before_2022', '2022', '2023', '2024'];

        foreach (range(2025, 2044) as $year) {
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
     *  - 2021以前 → before_2022
     *  - 2022-2024 → 年そのもの
     *  - それ以外 → null
     */
    private function resolvePastGiftBucket(?int $giftYear): ?string
    {
        if ($giftYear === null || $giftYear <= 0) {
            return null;
        }

        if ($giftYear < 2022) {
            return 'before_2022';
        }

        if ($giftYear <= 2024) {
            return (string)$giftYear;
        }

        return null;
    }

    /**
     * 未来分の年を決定する
     *  - gift_year があればそれを使用
     *  - 無ければ row_no から 2025-2044 に補完
     */
    private function resolveFutureGiftYear(?int $giftYear, ?int $rowNo): ?int
    {
        $resolvedYear = $giftYear;

        if ($resolvedYear === null || $resolvedYear <= 0) {
            if ($rowNo === null || $rowNo <= 0) {
                return null;
            }

            // KakujinZouyoPageService と同じ補完
            // row_no=1 → 2025, row_no=20 → 2044
            $resolvedYear = 2024 + $rowNo;
        }

        if ($resolvedYear < 2025 || $resolvedYear > 2044) {
            return null;
        }

        return $resolvedYear;
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

}

