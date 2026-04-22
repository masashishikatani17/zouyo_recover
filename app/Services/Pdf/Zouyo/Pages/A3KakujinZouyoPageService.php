<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;
use App\Models\FutureGiftRecipient;
use App\Models\FutureGiftPlanEntry;
use App\Models\ProposalFamilyMember;
use App\Models\ProposalHeader;
use App\Models\PastGiftCalendarEntry;
use App\Models\PastGiftSettlementEntry;
use App\Models\ZouyoGeneralRate;
use App\Models\ZouyoTokureiRate;

use Illuminate\Support\Str;
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

        $deathYear = $this->resolveDisplayDeathYear($payload, $dataId);
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
                'month' => $row->bir144th_month !== null ? (int)$row->birth_month : null,
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
                // ★重要：
                // calendar_tax_override_enabled は FutureGiftRecipient に保存される。
                // prefillFuture['header'] は「直近更新の1受贈者」分しか入らないため、
                // A3では受贈者ごとにDB値を持ち回る。
                'calendar_tax_override_enabled' => (int)(
                    $r->calendar_tax_override_enabled
                    ?? $r->calendar_basic_override_enabled
                    ?? 0
                ),                
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
            $pageNoZenkaku = mb_convert_kana((string)$pageNo, 'N', 'UTF-8');
            $pdf->MultiCell(38, 5, '５－' . $pageNoZenkaku . 'ページ', 0, 'R', 0, 0, 375, 280);

            // 上段
            if (isset($group[0])) {
                $dataset = $this->buildA3RecipientDataset(
                    $dataId,
                    $group[0],
                    $deathYear,                    
                    $birthByRow,
                    $inputAgeByRow,  
                    $payload,                    
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
                    $deathYear,                    
                    $birthByRow,
                    $inputAgeByRow,  
                    $payload,                    
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
        int $deathYear,        
        array $birthByRow,
        array $inputAgeByRow, 
        array $payload,        
        array $prefillFuture,
        int $rateYearGeneral,
        int $rateYearTokurei,
        callable $toInt
    ): array {
        $recipientNo   = (int)($info['recipient_no'] ?? 0);
        $recipientName = (string)($info['recipient_name'] ?? '');
        $tokureiFlag   = (int)($info['tokurei_flag'] ?? 0);
        $calendarTaxOverrideEnabled = (int)($info['calendar_tax_override_enabled'] ?? 0) === 1;


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

        $pastColKeys = $this->pastDetailColumnKeys($deathYear);
        $pastCols = $this->initPastDetailCols($pastColKeys);

        $birth = $birthByRow[$recipientNo] ?? ['year' => null, 'month' => null, 'day' => null];
        $donorBirth = $birthByRow[1] ?? ['year' => null, 'month' => null, 'day' => null];
        $recipientInputAge = $inputAgeByRow[$recipientNo] ?? null;
        $donorInputAge     = $inputAgeByRow[1] ?? null;        
        $isTokurei = ($tokureiFlag === 1);
        $rateYear  = $isTokurei ? $rateYearTokurei : $rateYearGeneral;
        
        foreach ($pastColKeys as $idx => $pastKey) {
            if ($idx === 0) {
                $pastCols[$pastKey]['gift_year'] = null;
                $pastCols[$pastKey]['donor_age'] = null;
                $pastCols[$pastKey]['age'] = null;
                continue;
            }

            $year = (int)$pastKey;
            $yearOffset = $year - $deathYear;

            $recipientAge = null;
            if ($recipientInputAge !== null) {
                $recipientAge = $recipientInputAge + $yearOffset;
            } else {
                $recipientAge = $this->calcAgeAtJan1(
                    $birth['year'],
                    $birth['month'],
                    $birth['day'],
                    $year
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
                    $year
                );
            }

            $pastCols[$pastKey]['gift_year'] = $year;
            $pastCols[$pastKey]['donor_age'] = $donorAge;
            $pastCols[$pastKey]['age'] = $recipientAge;
        }

        foreach ($zoyoByYear as $year => $amount) {
            $bucket = $this->resolvePastGiftBucket($deathYear, $year);
            if ($bucket === null || !isset($pastCols[$bucket])) {
                continue;
            }

            $basic = (int)($kojoByYear[$year] ?? 0);

            $pastCols[$bucket]['cal_amount'] += $amount;
            $pastCols[$bucket]['cal_basic']  -= $basic;
            $pastCols[$bucket]['cal_after']  += max(0, $amount - $basic);
            $pastCols[$bucket]['cal_tax']    += (int)($taxByYear[$year] ?? 0);
        }

        foreach ($seisanZoyoByYear as $year => $amount) {
            $bucket = $this->resolvePastGiftBucket($deathYear, $year);
            if ($bucket === null || !isset($pastCols[$bucket])) {
                continue;
            }

            $pastCols[$bucket]['set_amount'] += $amount;
            $pastCols[$bucket]['set_after']  += $amount;
            $pastCols[$bucket]['set_after25']+= max(0, $amount - 25000);
            $pastCols[$bucket]['set_tax']    += (int)($seisanTaxByYear[$year] ?? 0);
        }

        $runningPastCalCum = 0;
        $runningPastSetCum = 0;
        foreach ($pastColKeys as $pastKey) {
            $runningPastCalCum += (int)($pastCols[$pastKey]['cal_amount'] ?? 0);
            $runningPastSetCum += (int)($pastCols[$pastKey]['set_amount'] ?? 0);

            $pastCols[$pastKey]['cal_cum'] = $runningPastCalCum;
            $pastCols[$pastKey]['set_cum'] = $runningPastSetCum;
        }
        
        
        $planRows = FutureGiftPlanEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->orderBy('row_no')
            ->get();

        $plans = [];
        foreach ($planRows as $row) {
            $rawRowNo = (int)($row->row_no ?? 0);
            if ($rawRowNo >= 1 && $rawRowNo <= 20) {
                $plans[$rawRowNo] = $row;
            } elseif ($rawRowNo >= 0 && $rawRowNo <= 19) {
                $plans[$rawRowNo + 1] = $row;
            }
        }
        
        
        
        $runningSetCum = (int)PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->sum('amount_thousand');

        $detailCols = [];
        $firstGiftYear = null;        
        $sum = [
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

        foreach ($pastColKeys as $pastKey) {
            $sum['cal_amount'] += (int)($pastCols[$pastKey]['cal_amount'] ?? 0);
            $sum['cal_basic']  += (int)($pastCols[$pastKey]['cal_basic']  ?? 0);
            $sum['cal_after']  += (int)($pastCols[$pastKey]['cal_after']  ?? 0);
            $sum['cal_tax']    += (int)($pastCols[$pastKey]['cal_tax']    ?? 0);
            $sum['cal_cum']    += (int)($pastCols[$pastKey]['cal_cum']    ?? 0);
            $sum['set_amount'] += (int)($pastCols[$pastKey]['set_amount'] ?? 0);
            $sum['set_basic']  += (int)($pastCols[$pastKey]['set_basic']  ?? 0);
            $sum['set_after']  += (int)($pastCols[$pastKey]['set_after']  ?? 0);
            $sum['set_after25']+= (int)($pastCols[$pastKey]['set_after25']?? 0);
            $sum['set_tax']    += (int)($pastCols[$pastKey]['set_tax']    ?? 0);
            $sum['set_cum']    += (int)($pastCols[$pastKey]['set_cum']    ?? 0);
        }


        for ($i = 1; $i <= 20; $i++) {
            $plan = $plans[$i] ?? null;
            $giftYear = $plan && $plan->gift_year ? (int)$plan->gift_year : ($deathYear + $i - 1);
 
            $calAmountK  = $toInt($plan->calendar_amount_thousand ?? 0);
            $calBasicInK = $toInt($plan->calendar_basic_deduction_thousand ?? 0);
            $calBasicK   = (int)min($calAmountK, $calBasicInK);
            $calAfterK   = max(0, $calAmountK - $calBasicK);

            if ($calendarTaxOverrideEnabled) {
                // ★税額overrideは行データに保存される値を最優先
                $overrideRaw = $plan->calendar_tax_override_thousand ?? null;

                // 未保存時だけ payload / prefill へフォールバック
                if ($overrideRaw === null || $overrideRaw === '') {
                    $overrideRaw = $this->resolveA3PayloadValue(
                        $payload,
                        'calendar_tax_override_thousand',
                        $recipientNo,
                        $i
                    );
                }
                if (($overrideRaw === null || $overrideRaw === '') && !empty($prefillFuture)) {
                    $overrideRaw = $this->resolveA3PlanArrayValue(
                        $prefillFuture,
                        'calendar_tax_override_thousand',
                        $recipientNo,
                        $i
                    );
                }
                if (($overrideRaw === null || $overrideRaw === '') && !empty($prefillFuture)) {
                    $overrideRaw = $this->getPrefillFutureValue(
                        $prefillFuture,
                        $recipientNo,
                        $i,
                        ['calendar_tax_override_thousand', 'cal_tax', 'calendar_tax', 'calendar_tax_thousand']
                    );
                }

                $calTaxK = ($overrideRaw === null || $overrideRaw === '')
                    ? 0
                    : $toInt($overrideRaw);
            } else {
                $calTaxK = $this->calcGiftTaxKyen($calAfterK, $isTokurei, $rateYear);
            }


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

            $yearOffset = $giftYear - $deathYear;


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
            'past_col_keys'  => $pastColKeys,
            'past_cols'      => $pastCols,            
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
        
        $originY = $panel === 'top' ? 1.45 : 147.0;
        $originX = -2.0;
        $wakusen = 0;

        // ヘッダ
        $pdf->SetFont('mspgothic03', '', 10);
        //$this->drawA3Text($pdf, $donorName,                    28.0 + $originX,  29.5 + $originY, 20, 'C');
        $this->drawA3Text($pdf, (string)$dataset['recipient_name'], 41.0 + $originX,  24.3 + $originY, 20, 'L');
        
        $pdf->SetFont('mspmincho02', '', 9);
        //$this->drawA3Text($pdf, (string)$dataset['tokurei_label'],  37.7 + $originX,  72.1 + $originY, 20, 'L');
        $pdf->MultiCell(30, 4.0, (string)$dataset['tokurei_label'], 0,       'L', 0, 0, 36.6 + $originX, 78.7 + $originY);

        
        $pdf->SetFont('mspgothic03', '', 10);
        $fontSizeSet = 9;

        $pdf->SetFont('mspgothic03', '', 8.0);
        // 横方向の列位置（過年度4列 + 将来20列 + 合計列）
        $pastColKeys     = (array)($dataset['past_col_keys'] ?? []);
        $pastCols        = (array)($dataset['past_cols'] ?? []);
        $pastColStartX   = 58.2 + $originX;
        $colStep         = 12.2;
        $cellW           = 12.0;
        $futureColStartX = $pastColStartX + (count($pastColKeys) * $colStep) + 2.0;
        $sumColX         = 392.4 + $originX;


        // 行位置
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
        // 過年度4列（より以前 + 年別3列）
        foreach ($pastColKeys as $idx => $pastKey) {
            $x = $pastColStartX + ($idx * $colStep);
            $col = (array)($pastCols[$pastKey] ?? []);

            if (($col['gift_year'] ?? null) !== null) {
                $this->drawA3Text($pdf, (string)$col['gift_year'], $x, $yGiftYear, $cellW, 'C');
            }
            $this->drawA3Text(
                $pdf,
                isset($col['donor_age']) && $col['donor_age'] !== null ? ((string)$col['donor_age'] . '歳') : '',
                $x,
                $yDonor,
                $cellW,
                'C',
                $fontSizeSet
            );
            $this->drawA3Text(
                $pdf,
                isset($col['age']) && $col['age'] !== null ? ((string)$col['age'] . '歳') : '',
                $x,
                $yRecipient,
                $cellW,
                'C',
                $fontSizeSet
            );

            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_amount'] ?? 0), $x, $yCalAmount, $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_basic']  ?? 0), $x, $yCalBasic,  $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_after']  ?? 0), $x, $yCalAfter,  $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_tax']    ?? 0), $x, $yCalTax,    $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_cum']    ?? 0), $x, $yCalCum,    $cellW, 'R', $fontSizeSet);

            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_amount'] ?? 0), $x, $ySetAmount, $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_basic']  ?? 0), $x, $ySetBasic,  $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_after']  ?? 0), $x, $ySetAfter,  $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_after25']?? 0), $x, $ySetAfter25,$cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_tax']    ?? 0), $x, $ySetTax,    $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_cum']    ?? 0), $x, $ySetCum,    $cellW, 'R', $fontSizeSet);
        }



        // 1〜20列
        $colStep = 14.3;
        $detailCols = (array)$dataset['detail_cols'];
        foreach ($detailCols as $i => $col) {
            $x = $futureColStartX + (($i - 1) * $colStep);
             
            //年次
            $this->drawA3Text($pdf, (string)($col['gift_year'] ?? ''), $x, $yGiftYear, $cellW, 'C');
 
            //贈与者の年齢
            $this->drawA3Text($pdf, isset($col['donor_age']) && $col['donor_age'] !== null ? ((string)$col['donor_age'] . '歳') : '', $x, $yDonor, $cellW, 'C', $fontSizeSet);
             
 
            //受贈者の年齢
            $this->drawA3Text($pdf, isset($col['age']) && $col['age'] !== null ? ((string)$col['age'] . '歳') : '', $x, $yRecipient, $cellW, 'C', $fontSizeSet);

            //暦年贈与
            //贈与金額
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_amount'] ?? 0), $x, $yCalAmount, $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_basic']  ?? 0), $x, $yCalBasic,  $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_after']  ?? 0), $x, $yCalAfter,  $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_tax']    ?? 0), $x, $yCalTax,    $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['cal_cum']    ?? 0), $x, $yCalCum,    $cellW, 'R', $fontSizeSet);

            
            //精算課税贈与
            //贈与金額
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_amount'] ?? 0), $x, $ySetAmount, $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_basic']  ?? 0), $x, $ySetBasic,  $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_after']  ?? 0), $x, $ySetAfter,  $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_after25']?? 0), $x, $ySetAfter25,$cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_tax']    ?? 0), $x, $ySetTax,    $cellW, 'R', $fontSizeSet);
            $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $col['set_cum']    ?? 0), $x, $ySetCum,    $cellW, 'R', $fontSizeSet);

        }

        // 右端列：横合計
        $sum = (array)$dataset['sum'];
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['cal_amount'] ?? 0), $sumColX, $yCalAmount, 16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['cal_basic']  ?? 0), $sumColX, $yCalBasic,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['cal_after']  ?? 0), $sumColX, $yCalAfter,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['cal_tax']    ?? 0), $sumColX, $yCalTax,    16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, '  －', $sumColX, $yCalCum,    16, 'C', $fontSizeSet);
 
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_amount'] ?? 0), $sumColX, $ySetAmount, 16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_basic']  ?? 0), $sumColX, $ySetBasic,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_after']  ?? 0), $sumColX, $ySetAfter,  16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_after25']?? 0), $sumColX, $ySetAfter25,16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_tax']    ?? 0), $sumColX, $ySetTax,    16, 'R', $fontSizeSet);
        //$this->drawA3Text($pdf, (string)call_user_func($fmtStr, $sum['set_cum']    ?? 0), $sumColX, $ySetCum,    16, 'R', $fontSizeSet);
        $this->drawA3Text($pdf, '  －', $sumColX, $ySetCum,    16, 'C', $fontSizeSet);

        
    }
    
    /**
     * 過年度詳細列のキー
     *  - before_(deathYear-3)
     *  - deathYear-3
     *  - deathYear-2
     *  - deathYear-1
     *
     * @return array<int,string>
     */
    private function pastDetailColumnKeys(int $deathYear): array
    {
        $firstPastYear = $deathYear - 3;

        return [
            'before_' . $firstPastYear,
            (string)$firstPastYear,
            (string)($firstPastYear + 1),
            (string)($firstPastYear + 2),
        ];
    }

    /**
     * 過年度4列の初期化
     *
     * @param array<int,string> $keys
     * @return array<string,array<string,int|null>>
     */
    private function initPastDetailCols(array $keys): array
    {
        $cols = [];

        foreach ($keys as $key) {
            $cols[$key] = [
                'gift_year'  => null,
                'donor_age'  => null,
                'age'        => null,
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
        }

        return $cols;
    }

    /**
     * 過年度分の年バケット
     * - before_(deathYear-3)
     * - deathYear-3
     * - deathYear-2
     * - deathYear-1
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
     * 表示用の相続開始年を解決する。
     * A3KakuzoyoPlanPageService と同じ考え方で、
     * 過年度分を「より以前 + 年別3列」に切る基準年として使用する。
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
                }
            }
        }

        return (int)date('Y');
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

    /**
     * 画面で編集中の受贈者Noを推定する。
     * 1次元配列(plan.xxx[t])を読むときだけ使用。
     */
    private function resolveA3ActiveRecipientNo(array $payload): ?int
    {
        foreach ([
            'recipient_no',
            'current_recipient_no',
            'target_recipient_no',
            'selected_recipient_no',
            'active_recipient_no',
        ] as $key) {
            $no = (int) data_get($payload, $key, 0);
            if ($no >= 2 && $no <= 10) {
                return $no;
            }
        }

        return null;
    }

    /**
     * payload 本体から override系の値を拾う。
     * - *_all[recipient][t]
     * - field[recipient][t]
     * - plan.* 側
     * - 編集中受贈者だけ field[t] / plan.field[t]
     * - rowNo / rowNo-1 の両方を見る（0始まり対策）
     */
    private function resolveA3PayloadValue(
        array $payload,
        string $field,
        int $recipientNo,
        int $rowNo
    ) {
        $indexes = array_values(array_unique([$rowNo, $rowNo - 1]));
        $candidates = [];

        foreach ($indexes as $index) {
            if ($index < 0) {
                continue;
            }

            $candidates[] = data_get($payload, "{$field}_all.{$recipientNo}.{$index}");
            $candidates[] = data_get($payload, "{$field}.{$recipientNo}.{$index}");
            $candidates[] = data_get($payload, "plan.{$field}_all.{$recipientNo}.{$index}");
            $candidates[] = data_get($payload, "plan.{$field}.{$recipientNo}.{$index}");
        }

        $activeRecipientNo = $this->resolveA3ActiveRecipientNo($payload);
        if ($activeRecipientNo === $recipientNo) {
            foreach ($indexes as $index) {
                if ($index < 0) {
                    continue;
                }

                $candidates[] = data_get($payload, "{$field}.{$index}");
                $candidates[] = data_get($payload, "plan.{$field}.{$index}");
            }
        }

        foreach ($candidates as $raw) {
            if ($raw !== null && $raw !== '') {
                return $raw;
            }
        }

        return null;
    }



    /**
     * A3用：plan配列系の値を安全に拾う
     * - prefillFuture['plan'][field][rowNo]
     * - prefillFuture['plan'][field][rowNo-1]
     * - prefillFuture['plan'][field][recipientNo][rowNo]
     * - prefillFuture['plan'][field][recipientNo][rowNo-1]
     * - 念のため top-level 側も確認
     */
    private function resolveA3PlanArrayValue(
        array $prefillFuture,
        string $field,
        int $recipientNo,
        int $rowNo
    ) {
        $candidates = [
            data_get($prefillFuture, "plan.{$field}.{$rowNo}"),
            data_get($prefillFuture, 'plan.' . $field . '.' . ($rowNo - 1)),
            data_get($prefillFuture, "plan.{$field}.{$recipientNo}.{$rowNo}"),
            data_get($prefillFuture, 'plan.' . $field . '.' . $recipientNo . '.' . ($rowNo - 1)),
            data_get($prefillFuture, "{$field}.{$rowNo}"),
            data_get($prefillFuture, $field . '.' . ($rowNo - 1)),
            data_get($prefillFuture, "{$field}.{$recipientNo}.{$rowNo}"),
            data_get($prefillFuture, $field . '.' . $recipientNo . '.' . ($rowNo - 1)),
        ];

        foreach ($candidates as $raw) {
            if ($raw !== null && $raw !== '') {
                return $raw;
            }
        }

        return null;
    }

    /**
     * A3用：暦年贈与税overrideの生値を取得する
     * - DB保存値を優先
     * - enabled は header 側も確認
     * - amount は plan配列 / row配列 / cal_tax 表示値まで拾う
     */
    private function resolveA3CalendarOverrideRaw(
        ?FutureGiftPlanEntry $plan,
        array $payload,        
        array $prefillFuture,
        int $recipientNo,
        int $rowNo,
        string $field
    ) {


        if ($field === 'calendar_tax_override_enabled') {


            // 1. 現在のPOST値を最優先
            foreach ([
                data_get($payload, "header.{$field}"),
                is_array(data_get($payload, $field)) ? null : data_get($payload, $field),
                data_get($prefillFuture, "header.{$field}"),
                is_array(data_get($prefillFuture, $field)) ? null : data_get($prefillFuture, $field),
            ] as $raw) {
                if ($raw !== null && $raw !== '') {
                    return $raw;
                }
            }
    
            $payloadRaw = $this->resolveA3PayloadValue($payload, $field, $recipientNo, $rowNo);
            if ($payloadRaw !== null && $payloadRaw !== '') {
                return $payloadRaw;
            }
    
    
            $headerRaw = data_get($prefillFuture, "header.{$field}");
            if ($headerRaw !== null && $headerRaw !== '') {
                return $headerRaw;
            }

            $planRaw = $this->resolveA3PlanArrayValue($prefillFuture, $field, $recipientNo, $rowNo);
            if ($planRaw !== null && $planRaw !== '') {
                return $planRaw;
            }

            $rowRaw = $this->getPrefillFutureValue($prefillFuture, $recipientNo, $rowNo, [$field]);
            if ($rowRaw !== null && $rowRaw !== '') {
                return $rowRaw;
            }

        }

        if ($field === 'calendar_tax_override_thousand') {
            foreach ([
                'calendar_tax_override_thousand',
                // 画面表示済み税額の救済
                'cal_tax',
                'calendar_tax',
                'calendar_tax_thousand',
            ] as $candidateField) {
                
                
                $payloadRaw = $this->resolveA3PayloadValue($payload, $candidateField, $recipientNo, $rowNo);
                if ($payloadRaw !== null && $payloadRaw !== '') {
                    return $payloadRaw;
                }


                $planRaw = $this->resolveA3PlanArrayValue($prefillFuture, $candidateField, $recipientNo, $rowNo);
                if ($planRaw !== null && $planRaw !== '') {
                    return $planRaw;
                }

                $rowRaw = $this->getPrefillFutureValue($prefillFuture, $recipientNo, $rowNo, [$candidateField]);
                if ($rowRaw !== null && $rowRaw !== '') {
                    return $rowRaw;
                }
            }
        }


        // 2. 最後にDB保存値へフォールバック
        //    ※ DBの 0 が先に返ると、画面の最新override値が潰れるため最後に回す
        if ($plan) {
            $attrs = method_exists($plan, 'getAttributes') ? (array) $plan->getAttributes() : [];
            if (array_key_exists($field, $attrs) && $attrs[$field] !== null && $attrs[$field] !== '') {
                return $attrs[$field];
            }
        }

        return $this->getPrefillFutureValue($prefillFuture, $recipientNo, $rowNo, [$field]);
     
    }




    private function isA3CalendarTaxOverrideEnabled(
        ?FutureGiftPlanEntry $plan,
        array $payload,        
        array $prefillFuture,
        int $recipientNo,
        int $rowNo
    ): bool {
        $raw = $this->resolveA3CalendarOverrideRaw(
            $plan,
            $payload,            
            $prefillFuture,
            $recipientNo,
            $rowNo,
            'calendar_tax_override_enabled'
        );

        if ($raw === null || $raw === '') {
            return false;
        }
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_numeric($raw)) {
            return ((int)$raw) === 1;
        }

        $s = Str::lower(trim((string)$raw));
        return in_array($s, ['1', 'true', 'on', 'yes'], true);
    }

    private function resolveA3CalendarTaxOverrideK(
        ?FutureGiftPlanEntry $plan,
        array $payload,        
        array $prefillFuture,
        int $recipientNo,
        int $rowNo
    ): int {
        $raw = $this->resolveA3CalendarOverrideRaw(
            $plan,
            $payload,            
            $prefillFuture,
            $recipientNo,
            $rowNo,
            'calendar_tax_override_thousand'
        );

        if ($raw === null || $raw === '') {
            return 0;
        }

        $normalized = preg_replace('/[^\d\-]/u', '', (string)$raw) ?? '0';
        return max(0, (int)$normalized);
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

        return ($string === '' || $string === '-') ? 0 : (int)$string;
    }



}