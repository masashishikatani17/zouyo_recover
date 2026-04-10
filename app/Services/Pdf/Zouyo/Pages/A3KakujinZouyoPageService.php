<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;
use App\Models\FutureGiftRecipient;
use App\Models\FutureGiftPlanEntry;
use App\Models\ProposalFamilyMember;
use App\Models\PastGiftCalendarEntry;
use App\Models\PastGiftSettlementEntry;
use App\Models\ZouyoGeneralRate;
use App\Models\ZouyoTokureiRate;

use Illuminate\Support\Facades\Log;

/**
 * 5: 各人別贈与額および贈与税 ページ
 *
 * 仕様：
 *  - DB上で「これからの贈与」の入力がある受贈者ごとに 1 ページずつ出力
 *  - ページレイアウトは 05_pr_kakujin_zouyo.pdf を背景テンプレとして利用
 *  - 今の段階ではテンプレのみ（数値の印字は後続ステップで追加）
 */
class A3KakujinZouyoPageService implements ZouyoPdfPageInterface
{


    /** @var array<int,array<int,array{lower:int,upper:?int,rate:float,ded:int}>> */
    private array $giftRateCache = [
        0 => [], // general  year => rows
        1 => [], // tokurei  year => rows
    ];



    public function render(TCPDF $pdf, array $payload): void
    {

        $dataId = (int)($payload['data_id'] ?? 0);
        if ($dataId <= 0) {
            return;
        }

        $fmtStr = static function ($v): string {
            if ($v === null) {
                return '';
            }
            return number_format((int)$v);
        };

        $toInt = static function ($v): int {
            if ($v === null) return 0;
            if (is_int($v)) return $v;
            if (is_float($v)) return (int)$v;
            $s = mb_convert_kana((string)$v, 'n', 'UTF-8');
            $s = str_replace([',', ' ', '　'], '', $s);
            $s = preg_replace('/[^\d\-]/', '', $s) ?? '';
            if ($s === '' || $s === '-') {
                return 0;
            }
            return (int)$s;
        };

        $prefillFuture = (array)($payload['prefillFuture'] ?? []);

        $rateYearGeneral = (int)(ZouyoGeneralRate::query()->whereNull('company_id')->max('kihu_year') ?: 2026);
        $rateYearTokurei = (int)(ZouyoTokureiRate::query()->whereNull('company_id')->max('kihu_year') ?: 2026);

        $familyRows = ProposalFamilyMember::query()
            ->where('data_id', $dataId)
            ->orderBy('row_no')
            ->get()
            ->keyBy('row_no');

        $header = $payload['header'] ?? [];
        $donorName = (string)(
            ($familyRows[1]->name ?? null)
            ?? ($header['customer_name'] ?? '')
        );

        $recipients = FutureGiftRecipient::query()
            ->where('data_id', $dataId)
            ->orderBy('recipient_no')
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }


        $birthByRow = [];
        $inputAgeByRow = [];
        foreach ($familyRows as $rowNo => $row) {
            $birthByRow[(int)$rowNo] = [
                'year'  => $row->birth_year  !== null ? (int)$row->birth_year  : null,
                'month' => $row->birth_month !== null ? (int)$row->birth_month : null,
                'day'   => $row->birth_day   !== null ? (int)$row->birth_day   : null,
            ];
            
            $rawAge = $row->age ?? null;
            if ($rawAge === null || $rawAge === '') {
                $inputAgeByRow[(int)$rowNo] = null;
            } else {
                $age = (int)preg_replace('/[^\d\-]/', '', (string)$rawAge);
                $inputAgeByRow[(int)$rowNo] = ($age >= 0 && $age <= 130) ? $age : null;
            }            

        }

        $targets = [];
        foreach ($recipients as $r) {

            $recipientNo = (int)$r->recipient_no;        
            if ($recipientNo < 2 || $recipientNo > 10) {
                continue;
            }

            // 仕様：
            // 受贈者の氏名が空欄のときは表示しない（ページ追加対象に含めない）
            // 氏名は画面入力（ProposalFamilyMember）を唯一の基準にする
            $nameFromFamily = trim((string)($familyRows[$recipientNo]->name ?? ''));
            if ($nameFromFamily === '') {
                continue;
            }

            $targets[] = [
                'recipient_no'   => $recipientNo,
                'recipient_name' => $nameFromFamily,
                'tokurei_flag'   => (int)($familyRows[$recipientNo]->tokurei_zouyo ?? 0),
            ];
        }

        if (empty($targets)) {
            return;
        }

        // A3 テンプレート
        $templatePath = resource_path('/views/pdf/A3_04_pr_kakuzoyo.pdf');
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("A3 KakujinZouyo template not found: {$templatePath}");
        }

        $pdf->setSourceFile($templatePath);
        $tplId = $pdf->importPage(1);

        // 1ページに2人分（上段 / 下段）
        $pageGroups = array_chunk($targets, 2);
        $pageNo = 0;

        foreach ($pageGroups as $group) {
            $pdf->AddPage('L', 'A3');
            $pdf->useTemplate($tplId);
            $pageNo++;

            $pdf->SetFont('mspgothic03', '', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell(38, 5, '(4 - ' . $pageNo . 'ページ)', 0, 'R', 0, 0, 375, 277);

            // 上段
            if (isset($group[0])) {
                $dataset = $this->buildA3RecipientDataset(
                    $dataId,
                    $group[0],
                    $birthByRow,
                    $inputAgeByRow,                    
                    $prefillFuture,
                    $rateYearGeneral,
                    $rateYearTokurei,
                    $toInt
                );
                $this->renderA3RecipientPanel($pdf, $dataset, $donorName, 'top', $fmtStr);
            }

            // 下段
            if (isset($group[1])) {
                $dataset = $this->buildA3RecipientDataset(
                    $dataId,
                    $group[1],
                    $birthByRow,
                    $inputAgeByRow,                    
                    $prefillFuture,
                    $rateYearGeneral,
                    $rateYearTokurei,
                    $toInt
                );
                $this->renderA3RecipientPanel($pdf, $dataset, $donorName, 'bottom', $fmtStr);
            }
        }
    }

    /**
     * A3版 1受贈者分の描画データを組み立てる
     *
     * @param callable $toInt
     * @return array<string,mixed>
     */
    private function buildA3RecipientDataset(
        int $dataId,
        array $info,
        array $birthByRow,
        array $inputAgeByRow,        
        array $prefillFuture,
        int $rateYearGeneral,
        int $rateYearTokurei,
        callable $toInt
    ): array {
        $recipientNo   = (int)($info['recipient_no'] ?? 0);
        $recipientName = (string)($info['recipient_name'] ?? '');
        $tokureiFlag   = (int)($info['tokurei_flag'] ?? 0);


        // 念のための最終ガード：
        // 氏名が空欄なら、この受贈者パネル用データは作らない
        if (trim($recipientName) === '') {
            return [];
        }


        $pastRows = PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->whereNotNull('gift_year')
            ->orderBy('gift_year')
            ->get(['gift_year', 'amount_thousand', 'tax_thousand']);

        $zoyoByYear = [];
        $kojoByYear = [];
        $taxByYear  = [];
        foreach ($pastRows as $row) {
            $y = $toInt($row->gift_year);
            $z = $toInt($row->amount_thousand);
            $k = $toInt($row->tax_thousand);
            if ($y <= 0 || ($z === 0 && $k === 0)) {
                continue;
            }
            $zoyoByYear[$y] = ($zoyoByYear[$y] ?? 0) + $z;
            $kojoByYear[$y] = ($kojoByYear[$y] ?? 0) + min($z, 1100);
            $taxByYear[$y]  = ($taxByYear[$y]  ?? 0) + $k;
        }

        $seisanRows = PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->whereNotNull('gift_year')
            ->orderBy('gift_year')
            ->get(['gift_year', 'amount_thousand', 'tax_thousand']);

        $seisanZoyoByYear = [];
        $seisanTaxByYear  = [];
        foreach ($seisanRows as $row) {
            $y = $toInt($row->gift_year);
            $z = $toInt($row->amount_thousand);
            $k = $toInt($row->tax_thousand);
            if ($y <= 0 || ($z === 0 && $k === 0)) {
                continue;
            }
            $seisanZoyoByYear[$y] = ($seisanZoyoByYear[$y] ?? 0) + $z;
            $seisanTaxByYear[$y]  = ($seisanTaxByYear[$y]  ?? 0) + $k;
        }

        $before2022 = [
            'cal_amount' => 0,
            'cal_basic'  => 0,
            'cal_after'  => 0,
            'cal_tax'    => 0,
            'cal_cum'    => 0,
            'set_amount' => 0,
            'set_basic'  => 0,
            'set_after'  => 0,
            'set_after25'=> 0,
            'set_tax'    => 0,
            'set_cum'    => 0,
        ];

        foreach ($zoyoByYear as $year => $amount) {
            // A3版は過年度を1列で表現するため、2024年以前をすべて集約する
            if ($year <= 2024) {                $basic = (int)($kojoByYear[$year] ?? 0);
                $before2022['cal_amount'] += $amount;
                $before2022['cal_basic']  -= $basic;
                $before2022['cal_after']  += max(0, $amount - $basic);
                $before2022['cal_tax']    += (int)($taxByYear[$year] ?? 0);
            }
        }

        foreach ($seisanZoyoByYear as $year => $amount) {
            // A3版は過年度を1列で表現するため、2024年以前をすべて集約する
            if ($year <= 2024) {
                $before2022['set_amount'] += $amount;
                $before2022['set_after']  += $amount;
                $before2022['set_after25']+= max(0, $amount - 25000);
                $before2022['set_tax']    += (int)($seisanTaxByYear[$year] ?? 0);
            }
        }

        $plans = FutureGiftPlanEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->orderBy('row_no')
            ->get()
            ->keyBy('row_no');

        $birth = $birthByRow[$recipientNo] ?? ['year' => null, 'month' => null, 'day' => null];
        $donorBirth = $birthByRow[1] ?? ['year' => null, 'month' => null, 'day' => null];
        $recipientInputAge = $inputAgeByRow[$recipientNo] ?? null;
        $donorInputAge     = $inputAgeByRow[1] ?? null;        
        $isTokurei = ($tokureiFlag === 1);
        $rateYear  = $isTokurei ? $rateYearTokurei : $rateYearGeneral;

        $runningSetCum = (int)PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->sum('amount_thousand');

        $detailCols = [];
        $firstGiftYear = null;        
        $sum = [
            'cal_amount' => (int)($before2022['cal_amount'] ?? 0),
            'cal_basic'  => (int)($before2022['cal_basic']  ?? 0),
            'cal_after'  => (int)($before2022['cal_after']  ?? 0),
            'cal_tax'    => (int)($before2022['cal_tax']    ?? 0),
            'cal_cum'    => (int)($before2022['cal_cum']    ?? 0),
            'set_amount' => (int)($before2022['set_amount'] ?? 0),
            'set_basic'  => (int)($before2022['set_basic']  ?? 0),
            'set_after'  => (int)($before2022['set_after']  ?? 0),
            'set_after25'=> (int)($before2022['set_after25']?? 0),
            'set_tax'    => (int)($before2022['set_tax']    ?? 0),
            'set_cum'    => (int)($before2022['set_cum']    ?? 0),
        ];


        for ($i = 1; $i <= 20; $i++) {
            $plan = $plans[$i] ?? null;
            $giftYear = $plan && $plan->gift_year ? (int)$plan->gift_year : (2024 + $i);
            if ($firstGiftYear === null) {
                $firstGiftYear = $giftYear;
            }            

            $calAmountK  = $toInt($plan->calendar_amount_thousand ?? 0);
            $calBasicInK = $toInt($plan->calendar_basic_deduction_thousand ?? 0);
            $calBasicK   = (int)min($calAmountK, $calBasicInK);
            $calAfterK   = max(0, $calAmountK - $calBasicK);
            $calTaxK     = $this->calcGiftTaxKyen($calAfterK, $isTokurei, $rateYear);

            $calCumRaw = $plan->calendar_add_cum_thousand ?? null;
            if ($calCumRaw === null || $calCumRaw === '') {
                $calCumRaw = $this->getPrefillFutureValue(
                    $prefillFuture,
                    $recipientNo,
                    $i,
                    ['calendar_add_cum_thousand', 'calendar_add_cum', 'add_cum_thousand']
                );
            }
            $calCumK = $toInt($calCumRaw);

            $setAmountK  = $toInt($plan->settlement_amount_thousand ?? 0);
            $setBasicInK = $toInt($plan->settlement_110k_basic_thousand ?? 0);
            $setBasicK   = (int)min($setAmountK, $setBasicInK);
            $setAfterK   = $toInt($plan->settlement_after_basic_thousand ?? 0);
            $setAfter25K = $toInt($plan->settlement_after_25m_thousand ?? 0);
            $setTax20K   = $toInt($plan->settlement_tax20_thousand ?? 0);

            $setCumFromDb = $plan->settlement_add_cum_thousand ?? null;
            if ($setCumFromDb === null || $setCumFromDb === '') {
                $runningSetCum += $setAmountK;
                $setCumK = $runningSetCum;
            } else {
                $setCumK = $toInt($setCumFromDb);
                $runningSetCum = $setCumK;
            }

            $yearOffset = $firstGiftYear !== null ? ($giftYear - $firstGiftYear) : 0;

            $age = null;
            if ($recipientInputAge !== null) {
                $age = $recipientInputAge + $yearOffset;
            } else {
                $age = $this->calcAgeAtJan1(
                    $birth['year'],
                    $birth['month'],
                    $birth['day'],
                    $giftYear
                );
            }

            $donorAge = null;
            if ($donorInputAge !== null) {
                $donorAge = $donorInputAge + $yearOffset;
            } else {
                $donorAge = $this->calcAgeAtJan1(
                    $donorBirth['year'],
                    $donorBirth['month'],
                    $donorBirth['day'],
                    $giftYear
                );
            }

            $detailCols[$i] = [
                'index'      => $i,
                'gift_year'  => $giftYear,
                'donor_age'  => $donorAge,                
                'age'        => $age,
                'cal_amount' => $calAmountK,
                'cal_basic'  => -$calBasicK,
                'cal_after'  => $calAfterK,
                'cal_tax'    => $calTaxK,
                'cal_cum'    => $calCumK,
                'set_amount' => $setAmountK,
                'set_basic'  => -$setBasicK,
                'set_after'  => $setAfterK,
                'set_after25'=> $setAfter25K,
                'set_tax'    => $setTax20K,
                'set_cum'    => $setCumK,
            ];

            $sum['cal_amount'] += $calAmountK;
            $sum['cal_basic']  += -$calBasicK;
            $sum['cal_after']  += $calAfterK;
            $sum['cal_tax']    += $calTaxK;
            $sum['cal_cum']    += $calCumK;
            $sum['set_amount'] += $setAmountK;
            $sum['set_basic']  += -$setBasicK;
            $sum['set_after']  += $setAfterK;
            $sum['set_after25']+= $setAfter25K;
            $sum['set_tax']    += $setTax20K;
            $sum['set_cum']    += $setCumK;
        }
         
        return [
            'recipient_no'   => $recipientNo,
            'recipient_name' => $recipientName !== '' ? $recipientName : ('受贈者 ' . $recipientNo),
            'tokurei_label'  => $tokureiFlag === 1 ? '(特例税率)' : '(一般税率)',
            'donor_birth'    => $donorBirth,            
            'before2022'     => $before2022,
            'detail_cols'    => $detailCols,
            'sum' => $sum,

        ];
    }

    /**
     * A3版パネル描画
     *
     * @param callable $fmtStr
     */
    private function renderA3RecipientPanel(
        TCPDF $pdf,
        array $dataset,
        string $donorName,
        string $panel,
        callable $fmtStr
    ): void {
        
        if (trim((string)($dataset['recipient_name'] ?? '')) === '') {
            return;
        }
        
        $originY = $panel === 'top' ? 0.0 : 139.2;
        $originX = 0.0;
        $wakusen = 0;

        // ヘッダ
        $pdf->SetFont('mspgothic03', '', 10);
        //$this->drawA3Text($pdf, $donorName,                    28.0 + $originX,  29.5 + $originY, 20, 'C');
        $this->drawA3Text($pdf, (string)$dataset['recipient_name'], 39.0 + $originX,  24.3 + $originY, 20, 'L');
        
        $pdf->SetFont('mspmincho02', '', 9);
        //$this->drawA3Text($pdf, (string)$dataset['tokurei_label'],  37.7 + $originX,  72.1 + $originY, 20, 'L');
        $pdf->MultiCell(30, 4.0, (string)$dataset['tokurei_label'], 0,       'L', 0, 0, 33.7 + $originX, 78.4 + $originY);

        
        $pdf->SetFont('mspgothic03', '', 10);
        $fontSizeSet = 9;

        $pdf->SetFont('mspgothic03', '', 8);
        // 横方向の列位置（1〜20列 + 過年度合計列）
        $pastX   =  58.0 + $originX;
        $col0X   = 112.5 + $originX;
        $colStep =  15.9;

        // 行位置
        $yIndex      = 29.0 + $originY;
        $yGiftYear   = 39.8 + $originY;
        $yDonor      = 46.0 + $originY;
        $yRecipient  = 52.5 + $originY;

        $yCalAmount  = 59.5 + $originY;
        $yCalBasic   = 66.5 + $originY;
        $yCalAfter   = 73.0 + $originY;
        $yCalTax     = 79.5 + $originY;
        $yCalCum     = 85.5 + $originY;

        $ySetAmount  =  92.0 + $originY;
        $ySetBasic   =  98.5 + $originY;
        $ySetAfter   = 105.0 + $originY;
        $ySetAfter25 = 111.5 + $originY;
        $ySetTax     = 117.9 + $originY;
        $ySetCum     = 124.5 + $originY;

        // 過年度合計列
        $before2022 = (array)$dataset['before2022'];
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['cal_amount'] ?? 0), $pastX, $yCalAmount, 16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['cal_basic']  ?? 0), $pastX, $yCalBasic,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['cal_after']  ?? 0), $pastX, $yCalAfter,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['cal_tax']    ?? 0), $pastX, $yCalTax,    16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['cal_cum']    ?? 0), $pastX, $yCalCum,    16, 'R', $fontSizeSet);

        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['set_amount'] ?? 0), $pastX, $ySetAmount, 16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['set_basic']  ?? 0), $pastX, $ySetBasic,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['set_after']  ?? 0), $pastX, $ySetAfter,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['set_after25']?? 0), $pastX, $ySetAfter25,16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['set_tax']    ?? 0), $pastX, $ySetTax,    16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $before2022['set_cum']    ?? 0), $pastX, $ySetCum,    16, 'R', $fontSizeSet);

        // 1〜20列
        $detailCols = (array)$dataset['detail_cols'];
        foreach ($detailCols as $i => $col) {
            $x = $col0X + (($i - 1) * $colStep) - 39.0;
            
            //年次
            $this->drawA3Text($pdf, (string)($col['gift_year'] ?? ''), $x + 1.0, $yGiftYear, 16, 'C');

            //贈与者の年齢
            $this->drawA3Text($pdf, isset($col['donor_age']) && $col['donor_age'] !== null ? ((string)$col['donor_age'] . '歳') : '', (float)$x + 2.0, $yDonor, 16, 'C', $fontSizeSet);
            

            //受贈者の年齢
            $this->drawA3Text($pdf, isset($col['age']) && $col['age'] !== null ? ((string)$col['age'] . '歳') : '', (float)$x + 2.0, $yRecipient, 16, 'C', $fontSizeSet);

            //暦年贈与
            //贈与金額
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_amount'] ?? 0), $x, $yCalAmount, 16, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_basic']  ?? 0), $x, $yCalBasic,  16, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_after']  ?? 0), $x, $yCalAfter,  16, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_tax']    ?? 0), $x, $yCalTax,    16, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_cum']    ?? 0), $x, $yCalCum,    16, 'R', $fontSizeSet);

            
            //精算課税贈与
            //贈与金額
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_amount'] ?? 0), $x, $ySetAmount, 16, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_basic']  ?? 0), $x, $ySetBasic,  16, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_after']  ?? 0), $x, $ySetAfter,  16, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_after25']?? 0), $x, $ySetAfter25,16, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_tax']    ?? 0), $x, $ySetTax,    16, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_cum']    ?? 0), $x, $ySetCum,    16, 'R', $fontSizeSet);
        }

        // 右端列：横合計
        $sum = (array)$dataset['sum'];
        $sumColX = 391.5 + $originX;

        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['cal_amount'] ?? 0), $sumColX, $yCalAmount, 16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['cal_basic']  ?? 0), $sumColX, $yCalBasic,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['cal_after']  ?? 0), $sumColX, $yCalAfter,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['cal_tax']    ?? 0), $sumColX, $yCalTax,    16, 'R', $fontSizeSet);
        //$this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['cal_cum']    ?? 0), $sumColX, $yCalCum,    16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, '    －', $sumColX, $yCalCum,    16, 'C', $fontSizeSet);

        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_amount'] ?? 0), $sumColX, $ySetAmount, 16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_basic']  ?? 0), $sumColX, $ySetBasic,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_after']  ?? 0), $sumColX, $ySetAfter,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_after25']?? 0), $sumColX, $ySetAfter25,16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_tax']    ?? 0), $sumColX, $ySetTax,    16, 'R', $fontSizeSet);
        //$this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_cum']    ?? 0), $sumColX, $ySetCum,    16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, '    －', $sumColX, $ySetCum,    16, 'C', $fontSizeSet);

        
    }

    private function drawA3Text(
        TCPDF $pdf,
        string $text,
        float $x,
        float $y,
        float $w = 16.0,
        string $align = 'C',
        float $fontSize = 9.0
    ): void {
        $pdf->SetFont('mspgothic03', '', $fontSize);
        $pdf->MultiCell($w, 4.0, $text, 0, $align, 0, 0, $x, $y, true, 0, false, true, 0, 'M', true);
    }


    /**
     * 年齢を「基準年の1月1日時点の満年齢」で計算する。
     *
     * Blade / JS 側の calcAgeAsOfJan1(by,bm,bd,baseYear) と同じロジック。
     */
    private function calcAgeAtJan1(?int $birthYear, ?int $birthMonth, ?int $birthDay, ?int $baseYear): ?int
    {
        if (!$birthYear || !$birthMonth || !$birthDay || !$baseYear) {
            return null;
        }

        try {
            $base = new \DateTimeImmutable(sprintf('%04d-01-01', $baseYear));
            $by   = max(1, $birthYear);
            $bm   = max(1, min(12, $birthMonth));
            $bd   = max(1, min(31, $birthDay));
            $birth = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $by, $bm, $bd));

            $age = $baseYear - $by;

            $birthdayThisYear = $birth->setDate(
                $baseYear,
                (int)$birth->format('m'),
                (int)$birth->format('d')
            );
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

    /**
     * 暦年贈与の贈与税額を「課税価格（基礎控除後）= afterK（千円）」から計算して「千円」で返す。
     * - 税率表は ZouyoGeneralRate / ZouyoTokureiRate を使用
     * - rate は 10.000 のような「%」想定（>1 なら /100）
     */
    private function calcGiftTaxKyen(int $afterK, bool $tokurei, int $kihuYear): int
    {
        if ($afterK <= 0) {
            return 0;
        }

        $baseYen = $afterK * 1000;
        $rows = $this->loadGiftRateRows($tokurei, $kihuYear);
        if (!$rows) {
            return 0;
        }

        foreach ($rows as $r) {
            $lower = (int)($r['lower'] ?? 0);
            $upper = $r['upper'] ?? null;
            $upperVal = $upper === null ? PHP_INT_MAX : (int)$upper;

            if ($baseYen < $lower || $baseYen > $upperVal) {
                continue;
            }

            $rate = (float)($r['rate'] ?? 0.0);
            if ($rate > 1.0) {
                $rate = $rate / 100.0;
            }

            $ded = (int)($r['ded'] ?? 0);

            $taxYen = (int)round($baseYen * $rate) - $ded;
            if ($taxYen < 0) {
                $taxYen = 0;
            }

            return (int)round($taxYen / 1000);
        }

        return 0;
    }

    /**
     * 税率表の行をキャッシュして返す
     *
     * @return array<int,array{lower:int,upper:?int,rate:float,ded:int}>
     */
    private function loadGiftRateRows(bool $tokurei, int $kihuYear): array
    {
        $key = $tokurei ? 1 : 0;
        if (isset($this->giftRateCache[$key][$kihuYear])) {
            return $this->giftRateCache[$key][$kihuYear];
        }

        if ($tokurei) {
            $ver = (int)(ZouyoTokureiRate::query()
                ->whereNull('company_id')
                ->where('kihu_year', $kihuYear)
                ->max('version') ?: 1);

            $list = ZouyoTokureiRate::query()
                ->whereNull('company_id')
                ->where('kihu_year', $kihuYear)
                ->where('version', $ver)
                ->orderBy('seq')
                ->get(['lower', 'upper', 'rate', 'deduction_amount']);
        } else {
            $ver = (int)(ZouyoGeneralRate::query()
                ->whereNull('company_id')
                ->where('kihu_year', $kihuYear)
                ->max('version') ?: 1);

            $list = ZouyoGeneralRate::query()
                ->whereNull('company_id')
                ->where('kihu_year', $kihuYear)
                ->where('version', $ver)
                ->orderBy('seq')
                ->get(['lower', 'upper', 'rate', 'deduction_amount']);
        }

        $rows = [];
        foreach ($list as $r) {
            $rows[] = [
                'lower' => (int)($r->lower ?? 0),
                'upper' => $r->upper === null ? null : (int)$r->upper,
                'rate'  => (float)($r->rate ?? 0),
                'ded'   => (int)($r->deduction_amount ?? 0),
            ];
        }

        $this->giftRateCache[$key][$kihuYear] = $rows;
        return $rows;
    }

    /**
 * future_zouyo.blade 相当の prefillFuture から、受贈者・行番号・候補キーで値を拾う。
     *
     * 想定される配列形の揺れ：
     * - $prefillFuture[recipient_no][row_no][key]
     * - $prefillFuture[recipient_no]['rows'][row_no][key]
     * - $prefillFuture[recipient_no][row_no - 1][key]
     *
     * @param array<string|int,mixed> $prefillFuture
     * @param array<int,string>       $candidateKeys
     * @return mixed|null
     */
    private function getPrefillFutureValue(array $prefillFuture, int $recipientNo, int $rowNo, array $candidateKeys)
    {
    
        if ($recipientNo <= 0 || $rowNo <= 0) {
            return null;
        }

        $recipientBlock = $prefillFuture[$recipientNo] ?? null;
        if (!is_array($recipientBlock)) {
            return null;
        }

        $candidates = [];

        if (isset($recipientBlock[$rowNo]) && is_array($recipientBlock[$rowNo])) {
            $candidates[] = $recipientBlock[$rowNo];
        }

        if (isset($recipientBlock['rows']) && is_array($recipientBlock['rows'])) {
            if (isset($recipientBlock['rows'][$rowNo]) && is_array($recipientBlock['rows'][$rowNo])) {
                $candidates[] = $recipientBlock['rows'][$rowNo];
            }
            if (isset($recipientBlock['rows'][$rowNo - 1]) && is_array($recipientBlock['rows'][$rowNo - 1])) {
                $candidates[] = $recipientBlock['rows'][$rowNo - 1];
            }
        }

        if (isset($recipientBlock[$rowNo - 1]) && is_array($recipientBlock[$rowNo - 1])) {
            $candidates[] = $recipientBlock[$rowNo - 1];
        }

        foreach ($candidates as $row) {
            foreach ($candidateKeys as $key) {
                if (!array_key_exists($key, $row)) {
                    continue;
                }
                $value = $row[$key];
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

}