<?php

namespace App\Services\Pdf\Zouyo\Pages\Support;

use App\Models\InheritanceDistributionHeader;
use App\Models\InheritanceDistributionMember;
use App\Models\PastGiftCalendarEntry;
use App\Models\PastGiftSettlementEntry;
use App\Models\ProposalFamilyMember;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class A3InheritanceTaxTableDataBuilder
{
    public function build(array $payload): array
    {
        $dataId = (int)($payload['data_id'] ?? 0);
        $header = is_array($payload['header'] ?? null) ? $payload['header'] : [];
        $family = $this->normalizeFamilyRows($payload['family'] ?? []);

        $prefillFamily = $this->normalizeFamilyRows($payload['prefillFamily'] ?? []);
        if ($prefillFamily === []) {
            $prefillFamily = $family;
        }

        $prefillInheritance = is_array($payload['prefillInheritance'] ?? null)
            ? $payload['prefillInheritance']
            : [];

        if ($dataId > 0 && empty($prefillInheritance)) {
            $prefillInheritance = $this->resolvePrefillInheritance($dataId);
        }

        $resultsData = $this->resolveResultsData($payload);
        $root = is_array($resultsData) ? $resultsData : [];
        $calc = [];
        if (isset($root['before']) && is_array($root['before'])) {
        $calc = $root['before'];
        } elseif (isset($root['after']) && is_array($root['after'])) {
            $calc = $root['after'];
        }

        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];
        $methodCode = (int)($prefillInheritance['method_code'] ?? 0);
        $heirsByIdx = $this->buildHeirsByIdx($calc['heirs'] ?? []);

        $familyRowsDb = collect();
        if ($dataId > 0) {
            $familyRowsDb = ProposalFamilyMember::query()
                ->where('data_id', $dataId)
                ->orderBy('row_no')
                ->get()
                ->keyBy('row_no');
        }

        $relationships = config('relationships');
        $displayNames = [];
        $displayRelationships = [];
        for ($no = 1; $no <= 10; $no++) {
            $name = trim((string)(
                ($family[$no]['name'] ?? null)
                ?: ($prefillFamily[$no]['name'] ?? null)
                ?: ($familyRowsDb->get($no)->name ?? null)
                ?: ''
            ));
            $displayNames[$no] = $name;

            if ($name === '') {
                $displayRelationships[$no] = '';
                continue;
            }

            $relCode = $family[$no]['relationship_code']
                ?? $prefillFamily[$no]['relationship_code']
                ?? ($familyRowsDb->get($no)->relationship_code ?? null);

            $displayRelationships[$no] = $relationships[$relCode] ?? '';
    }

        $legalHeirCount = 0;
        for ($no = 2; $no <= 10; $no++) {
            $bunsi = $prefillFamily[$no]['bunsi'] ?? null;
            $bunbo = $prefillFamily[$no]['bunbo'] ?? null;

            $bunsiInt = ($bunsi === null || $bunsi === '') ? null : (int)preg_replace('/[^\d\-]/u', '', (string)$bunsi);
            $bunboInt = ($bunbo === null || $bunbo === '') ? null : (int)preg_replace('/[^\d\-]/u', '', (string)$bunbo);

            if ($bunsiInt !== null && $bunboInt !== null && $bunsiInt >= 1 && $bunboInt >= 1) {
                $legalHeirCount++;
            }
        }

        $basicDedKyen = (int)round(((int)($summary['basic_deduction'] ?? 0)) / 1000);
        $basicDeductionFormulaLabel = $this->resolveBasicDeductionFormulaLabel($payload, $summary, $legalHeirCount);
        $taxableEstate = (int)round(((int)($summary['taxable_estate'] ?? 0)) / 1000);

        $basePropertyKyen = (int)Arr::get($prefillFamily, '1.property', 0);

        $civilShareTargets = [];
        for ($no = 2; $no <= 10; $no++) {
            $civilBunsi = $prefillFamily[$no]['civil_share_bunsi'] ?? null;
            $civilBunbo = $prefillFamily[$no]['civil_share_bunbo'] ?? null;

            $civilBunsiInt = ($civilBunsi === null || $civilBunsi === '') ? 0 : (int)preg_replace('/[^\d\-]/u', '', (string)$civilBunsi);
            $civilBunboInt = ($civilBunbo === null || $civilBunbo === '') ? 0 : (int)preg_replace('/[^\d\-]/u', '', (string)$civilBunbo);

            if ($civilBunsiInt >= 1 && $civilBunboInt >= 1) {
                $civilShareTargets[$no] = [
                    'bunsi' => $civilBunsiInt,
                    'bunbo' => $civilBunboInt,
                ];
            }
        }

        $propertyShareKByHeir = $this->emptyHeirIntMap();
        if ($methodCode !== 9 && $basePropertyKyen > 0) {
            $propertyShareKByHeir = $this->distributeKyenByCivilShares($basePropertyKyen, $civilShareTargets);
        }

        $taxableEstateShareKByHeir = $this->distributeKyenByTaxShares($taxableEstate, $heirsByIdx);

        $lifetimeGiftKyenByNo = $this->emptyHeirIntMap();
        $sumLifetimeGiftKyen = 0;
        for ($no = 2; $no <= 10; $no++) {
            $yen = (int)($heirsByIdx[$no]['past_gift_included_yen'] ?? 0);
            $kyen = (int)round($yen / 1000);
            $lifetimeGiftKyenByNo[$no] = $kyen;
            $sumLifetimeGiftKyen += $kyen;
        }

        $calendarGiftAddKyen = $dataId > 0
            ? (int)PastGiftCalendarEntry::query()
                ->where('data_id', $dataId)
                ->whereBetween('recipient_no', [2, 10])
                ->sum('amount_thousand')
            : 0;

        $settlementGiftAddKyen = $dataId > 0
            ? (int)PastGiftSettlementEntry::query()
                ->where('data_id', $dataId)
                ->whereBetween('recipient_no', [2, 10])
                ->sum('amount_thousand')
            : 0;

        $propertyBeforeGiftKyen = $basePropertyKyen;
        $propertyAfterGiftKyen = max(0, $propertyBeforeGiftKyen);
        $taxablePriceCalcKyen = $propertyAfterGiftKyen + $calendarGiftAddKyen + $settlementGiftAddKyen;
        $taxableTotalKyen = $basePropertyKyen + $sumLifetimeGiftKyen;

        $baseFinancialKyen = $this->pickFirstInt([
            Arr::get($payload, 'financial_assets_thousand'),
            Arr::get($payload, 'financial_assets'),
            Arr::get($prefillFamily, '1.financial_assets_thousand'),
            Arr::get($prefillFamily, '1.financial_assets'),
            Arr::get($prefillFamily, '1.kinyu_shisan_thousand'),
            Arr::get($prefillFamily, '1.kinyu_shisan'),
            Arr::get($prefillFamily, '1.cash_share_value_thousand'),
            Arr::get($prefillFamily, '1.cash_share_thousand'),
            Arr::get($prefillFamily, '1.cash_share'),
            Arr::get($prefillFamily, '1.cash_value_thousand'),
            Arr::get($prefillFamily, '1.cash_thousand'),
            Arr::get($prefillFamily, '1.cash'),
        ], 0);

        $baseOtherKyen = $this->pickFirstInt([
            Arr::get($payload, 'other_assets_thousand'),
            Arr::get($payload, 'other_assets'),
            Arr::get($prefillFamily, '1.other_assets_thousand'),
            Arr::get($prefillFamily, '1.other_assets'),
            Arr::get($prefillFamily, '1.sonota_shisan_thousand'),
            Arr::get($prefillFamily, '1.sonota_shisan'),
            Arr::get($prefillFamily, '1.other_share_value_thousand'),
            Arr::get($prefillFamily, '1.other_share_thousand'),
            Arr::get($prefillFamily, '1.other_share'),
            Arr::get($prefillFamily, '1.other_value_thousand'),
            Arr::get($prefillFamily, '1.other_thousand'),
            Arr::get($prefillFamily, '1.other'),
        ], 0);

        if ($basePropertyKyen > 0 && $baseFinancialKyen >= 0) {
            $baseOtherKyen = max(0, $basePropertyKyen - $baseFinancialKyen);
        }

        $financialShareKByHeir = $this->emptyHeirIntMap();
        $otherShareKByHeir = $this->emptyHeirIntMap();
        if ($methodCode === 9) {
            for ($no = 2; $no <= 10; $no++) {
                $member = $prefillInheritance['members'][$no] ?? [];

                $financialShareKByHeir[$no] = $this->pickFirstInt([
                    $member['cash_share'] ?? null,
                    $member['cash_share_value_thousand'] ?? null,
                    $member['cash_share_thousand'] ?? null,
                    $member['financial_assets'] ?? null,
                    $member['financial_assets_value_thousand'] ?? null,
                    $member['financial_assets_thousand'] ?? null,
                    $member['kinyu_shisan'] ?? null,
                    $member['kinyu_shisan_value_thousand'] ?? null,
                    $member['kinyu_shisan_thousand'] ?? null,
                    $member['cash'] ?? null,
                    $member['cash_value_thousand'] ?? null,
                    $member['cash_thousand'] ?? null,
                ], 0);

                $otherShareKByHeir[$no] = $this->pickFirstInt([
                    $member['other_share'] ?? null,
                    $member['other_share_value_thousand'] ?? null,
                    $member['other_share_thousand'] ?? null,
                    $member['other_assets'] ?? null,
                    $member['other_assets_value_thousand'] ?? null,
                    $member['other_assets_thousand'] ?? null,
                    $member['sonota_shisan'] ?? null,
                    $member['sonota_shisan_value_thousand'] ?? null,
                    $member['sonota_shisan_thousand'] ?? null,
                    $member['other'] ?? null,
                    $member['other_value_thousand'] ?? null,
                    $member['other_thousand'] ?? null,
                ], 0);
            }
        } else {
            $financialShareKByHeir = $this->distributeKyenByCivilShares($baseFinancialKyen, $civilShareTargets);
            $otherShareKByHeir = $this->distributeKyenByCivilShares($baseOtherKyen, $civilShareTargets);
        }

        $sozokuTaxTotalKyen = (int)round(((int)($summary['sozoku_tax_total'] ?? 0)) / 1000);

        $sumSanzutsuYen = 0;
        $twoWarYenByNo = $this->emptyHeirIntMap();
        $twoWarSumYen = 0;
        $totalSettlementGiftYen = 0;
        $sumPayableYen = 0;
        $sumRefundYen = 0;
        for ($no = 2; $no <= 10; $no++) {
            $sumSanzutsuYen += (int)($heirsByIdx[$no]['sanzutsu_tax_yen'] ?? 0);

            $inc = ((int)($heirsByIdx[$no]['final_tax_yen'] ?? 0)) - ((int)($heirsByIdx[$no]['sanzutsu_tax_yen'] ?? 0));
            $inc = $inc > 0 ? $inc : 0;
            $twoWarYenByNo[$no] = $inc;
            $twoWarSumYen += $inc;

            $totalSettlementGiftYen += (int)($heirsByIdx[$no]['settlement_gift_tax_yen'] ?? 0);
            $sumPayableYen += (int)($heirsByIdx[$no]['payable_tax_yen'] ?? 0);
            $sumRefundYen += (int)($heirsByIdx[$no]['refund_tax_yen'] ?? 0);
        }

        $sumSanzutsuTotalKyen = (int)round($sumSanzutsuYen / 1000);
        $twoWarSumKyen = (int)round($twoWarSumYen / 1000);
        $totalGiftTaxCreditsKyen = (int)round(((int)($summary['total_gift_tax_credits'] ?? 0)) / 1000);
        $totalSpouseReliefKyen = (int)round(((int)($summary['total_spouse_relief'] ?? 0)) / 1000);
        $totalOtherCreditsKyen = (int)round(((int)($summary['total_other_credits'] ?? 0)) / 1000);
        $totalCreditsAllKyen = $totalGiftTaxCreditsKyen + $totalSpouseReliefKyen + $totalOtherCreditsKyen;
        $totalSashihikiTaxKyen = (int)round(((int)($summary['total_sashihiki_tax'] ?? 0)) / 1000);
        $totalSettlementGiftKyen = (int)round($totalSettlementGiftYen / 1000);
        $totalFinalAfterSettlementKyen = (int)round(((int)($summary['total_raw_final_after_settlement'] ?? $summary['final_after_settlement_yen'] ?? 0)) / 1000);
        $sumPayableKyen = (int)round($sumPayableYen / 1000);
        $sumRefundKyen = (int)round($sumRefundYen / 1000);

        $donorName = trim((string)(
            ($family[1]['name'] ?? null)
            ?: ($familyRowsDb->get(1)->name ?? null)
            ?: ($header['customer_name'] ?? '')
        ));

        $totalColumn = [
            'financial_assets'        => $baseFinancialKyen,
            'other_assets'            => $baseOtherKyen,
            'property_total'          => $basePropertyKyen,
            'lifetime_gift'           => $sumLifetimeGiftKyen,
            'taxable_total'           => $taxableTotalKyen,
            'basic_deduction'         => $basicDedKyen,
            'taxable_estate'          => $taxableEstate,
            'law_share'               => '',
            'legal_tax'               => $sozokuTaxTotalKyen,
            'anbun_ratio'             => '1.0000',
            'sanzutsu_tax'            => $sumSanzutsuTotalKyen,
            'twowari'                 => $twoWarSumKyen,
            'calendar_gift_credit'    => $totalGiftTaxCreditsKyen,
            'spouse_relief'           => $totalSpouseReliefKyen,
            'other_credit'            => $totalOtherCreditsKyen,
            'credits_total'           => $totalCreditsAllKyen,
            'sashihiki_tax'           => $totalSashihikiTaxKyen,
            'settlement_gift_credit'  => $totalSettlementGiftKyen,
            'subtotal'                => $totalFinalAfterSettlementKyen,
            'payable_tax'             => $sumPayableKyen,
            'refund_tax'              => $sumRefundKyen,
            'ratio'                   => ($sumPayableKyen > 0 && $taxableTotalKyen > 0)
                ? number_format(($sumPayableKyen / $taxableTotalKyen) * 100, 2) . '％'
                : '',
        ];

        $heirRows = [];
        for ($no = 2; $no <= 10; $no++) {
            $name = trim((string)($displayNames[$no] ?? ''));
            $relationship = (string)($displayRelationships[$no] ?? '');
            $hasName = $name !== '';

            $row = [
                'has_name'                 => $hasName,
                'name'                     => $name,
                'relationship'             => $relationship,
                'financial_assets'         => 0,
                'other_assets'             => 0,
                'property_total'           => 0,
                'lifetime_gift'            => 0,
                'taxable_total'            => 0,
                'basic_deduction'          => 0,
                'taxable_estate'           => 0,
                'law_share'                => '',
                'legal_tax'                => 0,
                'anbun_ratio'              => '',
                'sanzutsu_tax'             => 0,
                'twowari'                  => 0,
                'calendar_gift_credit'     => 0,
                'spouse_relief'            => 0,
                'other_credit'             => 0,
                'credits_total'            => 0,
                'sashihiki_tax'            => 0,
                'settlement_gift_credit'   => 0,
                'subtotal'                 => 0,
                'payable_tax'              => 0,
                'refund_tax'               => 0,
                'ratio'                    => '',
            ];

            if (!$hasName) {
                $heirRows[$no] = $row;
                continue;
            }

            $member = $prefillInheritance['members'][$no] ?? [];

            if ($methodCode === 9) {
                $heirPropertyKyen = (int)($member['taxable_manu'] ?? $member['taxable_auto'] ?? 0);
            } else {
                $heirPropertyKyen = (int)($propertyShareKByHeir[$no] ?? 0);
            }

            $heirFinancialKyen = (int)($financialShareKByHeir[$no] ?? 0);
            $heirOtherKyen = (int)($otherShareKByHeir[$no] ?? 0);
            if ($heirPropertyKyen > 0 && $heirFinancialKyen >= 0) {
                $heirOtherKyen = max(0, $heirPropertyKyen - $heirFinancialKyen);
            }
            if (($heirFinancialKyen + $heirOtherKyen) > 0) {
                $heirPropertyKyen = $heirFinancialKyen + $heirOtherKyen;
            }

            $heirLifetimeGiftKyen = (int)($lifetimeGiftKyenByNo[$no] ?? 0);
            $heirTaxableTotalKyen = $heirPropertyKyen + $heirLifetimeGiftKyen;

            if ($methodCode === 9) {
                $heirTaxableEstateKyen = (int)round(((int)($heirsByIdx[$no]['manual_taxable_share_yen'] ?? 0)) / 1000);
                if ($heirTaxableEstateKyen <= 0) {
                    $heirTaxableEstateKyen = (int)($taxableEstateShareKByHeir[$no] ?? 0);
                }
            } else {
                $heirTaxableEstateKyen = (int)($taxableEstateShareKByHeir[$no] ?? 0);
            }

            $souzokunin = $prefillFamily[$no]['souzokunin'] ?? ($family[$no]['souzokunin'] ?? null);
            $souzokunin = trim((string)$souzokunin);
            $bunsi = $prefillFamily[$no]['bunsi'] ?? null;
            $bunbo = $prefillFamily[$no]['bunbo'] ?? null;
            if (($bunsi === null || $bunsi === '') && ($bunbo === null || $bunbo === '')) {
                $bunsi = $family[$no]['bunsi'] ?? null;
                $bunbo = $family[$no]['bunbo'] ?? null;
            }
            $bunsiInt = ($bunsi === null || $bunsi === '') ? null : (int)preg_replace('/[^\d\-]/u', '', (string)$bunsi);
            $bunboInt = ($bunbo === null || $bunbo === '') ? null : (int)preg_replace('/[^\d\-]/u', '', (string)$bunbo);
            $lawShareVal = '';
            if ($souzokunin === '法定相続人' && $bunsiInt !== null && $bunboInt !== null && $bunsiInt >= 1 && $bunboInt >= 1) {
                $lawShareVal = $bunsiInt . '/' . $bunboInt;
            }

            $heirSozokuTaxKyen = isset($heirsByIdx[$no]['legal_tax_yen'])
                ? (int)round(((int)$heirsByIdx[$no]['legal_tax_yen']) / 1000)
                : 0;

            $heirAnbunRatio = $heirsByIdx[$no]['anbun_ratio'] ?? null;
            $heirAnbunRatioText = ($heirAnbunRatio !== null && $heirTaxableTotalKyen > 0)
                ? number_format((float)$heirAnbunRatio, 4, '.', '')
                : '';

            $heirSanzutsuKyen = isset($heirsByIdx[$no]['sanzutsu_tax_yen'])
                ? (int)round(((int)$heirsByIdx[$no]['sanzutsu_tax_yen']) / 1000)
                : 0;

            $heirTwoWarKyen = 0;
            $incYen = (int)($twoWarYenByNo[$no] ?? 0);
            if ($incYen > 0) {
                $heirTwoWarKyen = (int)round($incYen / 1000);
            }

            $heirGiftCreditCalKyen = isset($heirsByIdx[$no]['gift_tax_credit_calendar_yen'])
                ? (int)round(((int)$heirsByIdx[$no]['gift_tax_credit_calendar_yen']) / 1000)
                : 0;
            $heirSpouseReliefKyen = isset($heirsByIdx[$no]['spouse_relief_yen'])
                ? (int)round(((int)$heirsByIdx[$no]['spouse_relief_yen']) / 1000)
                : 0;
            $heirOtherCreditKyen = isset($heirsByIdx[$no]['other_credit_yen'])
                ? (int)round(((int)$heirsByIdx[$no]['other_credit_yen']) / 1000)
                : 0;
            $heirTotalCreditsKyen = $heirGiftCreditCalKyen + $heirSpouseReliefKyen + $heirOtherCreditKyen;
            $heirSashihikiKyen = isset($heirsByIdx[$no]['sashihiki_tax_yen'])
                ? (int)round(((int)$heirsByIdx[$no]['sashihiki_tax_yen']) / 1000)
                : 0;
            $heirSettlementGiftKyen = isset($heirsByIdx[$no]['settlement_gift_tax_yen'])
                ? (int)round(((int)$heirsByIdx[$no]['settlement_gift_tax_yen']) / 1000)
                : 0;

            $heirFinalAfterSettlementYen = (int)(
                $heirsByIdx[$no]['raw_final_after_settlement_yen']
                ?? $heirsByIdx[$no]['final_after_settlement_yen']
                ?? 0
            );
            $heirFinalAfterSettlementKyen = $heirFinalAfterSettlementYen !== 0
                ? (int)round($heirFinalAfterSettlementYen / 1000)
                : 0;

            $heirPayableKyen = isset($heirsByIdx[$no]['payable_tax_yen'])
                ? (int)round(((int)$heirsByIdx[$no]['payable_tax_yen']) / 1000)
                : 0;
            $heirRefundKyen = isset($heirsByIdx[$no]['refund_tax_yen'])
                ? (int)round(((int)$heirsByIdx[$no]['refund_tax_yen']) / 1000)
                : 0;
            $heirRatioText = ($heirPayableKyen > 0 && $heirTaxableTotalKyen > 0)
                ? number_format($heirPayableKyen / $heirTaxableTotalKyen * 100, 2) . '％'
                : '';

        $row['financial_assets'] = $heirFinancialKyen;
            $row['other_assets'] = $heirOtherKyen;
            $row['property_total'] = $heirPropertyKyen;
            $row['lifetime_gift'] = $heirLifetimeGiftKyen;
            $row['taxable_total'] = $heirTaxableTotalKyen;
            $row['taxable_estate'] = $heirTaxableEstateKyen;
            $row['law_share'] = $lawShareVal;
            $row['legal_tax'] = $heirSozokuTaxKyen;
            $row['anbun_ratio'] = $heirAnbunRatioText;
            $row['sanzutsu_tax'] = $heirSanzutsuKyen;
            $row['twowari'] = $heirTwoWarKyen;
            $row['calendar_gift_credit'] = $heirGiftCreditCalKyen;
            $row['spouse_relief'] = $heirSpouseReliefKyen;
            $row['other_credit'] = $heirOtherCreditKyen;
            $row['credits_total'] = $heirTotalCreditsKyen;
            $row['sashihiki_tax'] = $heirSashihikiKyen;
            $row['settlement_gift_credit'] = $heirSettlementGiftKyen;
            $row['subtotal'] = $heirFinalAfterSettlementKyen;
            $row['payable_tax'] = $heirPayableKyen;
            $row['refund_tax'] = $heirRefundKyen;
            $row['ratio'] = $heirRatioText;

            $heirRows[$no] = $row;
        }

        return [
            'data_id' => $dataId,
            'method_code' => $methodCode,
            'donor_name' => $donorName,
            'basic_deduction_formula_label' => $basicDeductionFormulaLabel,
            'left_calc' => [
                'property_before' => $propertyBeforeGiftKyen,
                'property_after' => $propertyAfterGiftKyen,
                'gift_add_calendar' => $calendarGiftAddKyen,
                'gift_add_settlement' => $settlementGiftAddKyen,
                'taxable_price' => $taxablePriceCalcKyen,
            ],
            'total' => $totalColumn,
            'heirs' => $heirRows,
        ];
    }

    private function normalizeFamilyRows(array $familyRows): array
    {
        $normalized = [];
        $sequential = 1;

        foreach ($familyRows as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $rowNo = isset($item['row_no']) ? (int)$item['row_no'] : null;
            if ($rowNo === null || $rowNo <= 0) {
                $rowNo = (is_numeric($index) && (int)$index >= 1) ? (int)$index : $sequential;
            }
            if ($rowNo <= 0) {
                $rowNo = $sequential;
            }

            $item['row_no'] = $rowNo;
            $normalized[$rowNo] = $item;
            $sequential++;
        }

        ksort($normalized);
        return $normalized;
    }

    private function resolvePrefillInheritance(int $dataId): array
    {
        $prefillInheritance = [
            'method_code' => null,
            'members' => [],
            'other_credit' => [],
        ];

        if (class_exists(InheritanceDistributionHeader::class)) {
            if ($ih = InheritanceDistributionHeader::where('data_id', $dataId)->first()) {
                $prefillInheritance['method_code'] = $ih->method_code;
            }
        }

        if (class_exists(InheritanceDistributionMember::class)) {
            $rows = InheritanceDistributionMember::where('data_id', $dataId)->get();
            foreach ($rows as $r) {
                $no = (int)$r->recipient_no;
                if ($no < 2 || $no > 10) {
                    continue;
                }

                $prefillInheritance['members'][$no] = [
                    'taxable_auto' => $r->taxable_auto_value_thousand,
                    'taxable_manu' => $r->taxable_manu_value_thousand,
                    'cash_share'   => $r->cash_share_value_thousand
                        ?? $r->cash_share_thousand
                        ?? $r->cash_share
                        ?? $r->financial_assets_value_thousand
                        ?? $r->financial_assets_thousand
                        ?? $r->financial_assets
                        ?? $r->kinyu_shisan_value_thousand
                        ?? $r->kinyu_shisan_thousand
                        ?? $r->kinyu_shisan,
                    'other_share'  => $r->other_share_value_thousand
                        ?? $r->other_share_thousand
                        ?? $r->other_share
                        ?? $r->other_assets_value_thousand
                        ?? $r->other_assets_thousand
                        ?? $r->other_assets
                        ?? $r->sonota_shisan_value_thousand
                        ?? $r->sonota_shisan_thousand
                        ?? $r->sonota_shisan,
                ];
                $prefillInheritance['other_credit'][$no] = $r->other_tax_credit_thousand;
            }
        }

        return $prefillInheritance;
    }

private function resolveResultsData(array $payload): array
    {
        $resultsData = is_array($payload['resultsData'] ?? null) ? $payload['resultsData'] : [];
        if ($resultsData === []) {
            $resultsData = Session::get('zouyo.results', []);
        }
        if ($resultsData === []) {
            $resultsKey = Session::get('zouyo.results_key');
            if ($resultsKey) {
                $resultsData = Cache::get($resultsKey, []);
            }
        }

        return is_array($resultsData) ? $resultsData : [];
    }

    private function buildHeirsByIdx($heirs): array
    {
        $result = [];
        if (!is_iterable($heirs)) {
            return $result;
        }

        foreach ($heirs as $heir) {
            if (isset($heir['row_index'])) {
                $result[(int)$heir['row_index']] = $heir;
            }
        }

        return $result;
    }

    private function emptyHeirIntMap(): array
    {
        $map = [];
        for ($no = 2; $no <= 10; $no++) {
            $map[$no] = 0;
        }

        return $map;
    }

    private function pickFirstInt(array $candidates, int $default = 0): int
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            if (is_numeric($candidate)) {
                return (int)round((float)$candidate);
            }

            $normalized = preg_replace('/[^\d\-]/u', '', (string)$candidate);
            if ($normalized === '' || $normalized === null) {
                continue;
            }

            if (is_numeric($normalized)) {
                return (int)$normalized;
            }
        }

        return $default;
    }

    private function distributeKyenByCivilShares(int $baseKyen, array $civilShareTargets): array
    {
        $result = $this->emptyHeirIntMap();

        if ($baseKyen <= 0 || empty($civilShareTargets)) {
            return $result;
        }

        $sumShares = 0;
        $lastNo = null;
        foreach ($civilShareTargets as $no => $share) {
            $bunsi = (int)($share['bunsi'] ?? 0);
            $bunbo = (int)($share['bunbo'] ?? 0);
            if ($bunsi <= 0 || $bunbo <= 0) {
                continue;
            }

            $shareKyen = (int)round($baseKyen * ($bunsi / $bunbo));
            $result[$no] = $shareKyen;
            $sumShares += $shareKyen;
            if ($shareKyen > 0) {
                $lastNo = $no;
            }
        }

        $diff = $baseKyen - $sumShares;
        if ($diff !== 0 && $lastNo !== null) {
            $result[$lastNo] = (int)($result[$lastNo] ?? 0) + $diff;
        }

        return $result;
    }

    private function distributeKyenByTaxShares(int $baseKyen, array $heirsByIdx): array
    {
        $result = $this->emptyHeirIntMap();
        if ($baseKyen <= 0) {
            return $result;
        }

        $gcd = function (int $a, int $b): int {
            $a = abs($a);
            $b = abs($b);
            while ($b !== 0) {
                $t = $a % $b;
                $a = $b;
                $b = $t;
            }
            return $a === 0 ? 1 : $a;
        };
        $lcm = function (int $a, int $b) use ($gcd): int {
            $a = abs($a);
            $b = abs($b);
            if ($a === 0 || $b === 0) {
            return 0;
            }
            return (int)($a / $gcd($a, $b) * $b);
        };

        $bunboLcm = 1;
        $targets = [];
        for ($no = 2; $no <= 10; $no++) {
            $bunsi = (int)($heirsByIdx[$no]['bunsi'] ?? 0);
            $bunbo = (int)($heirsByIdx[$no]['bunbo'] ?? 0);
            if ($bunsi >= 1 && $bunbo >= 1) {
                $targets[] = $no;
                $bunboLcm = $lcm($bunboLcm, $bunbo);
            }
        }

        $weights = [];
        $sumWeights = 0;
        foreach ($targets as $no) {
            $bunsi = (int)($heirsByIdx[$no]['bunsi'] ?? 0);
            $bunbo = (int)($heirsByIdx[$no]['bunbo'] ?? 0);
            $weight = ($bunbo > 0) ? (int)($bunsi * ($bunboLcm / $bunbo)) : 0;
            $weights[$no] = $weight;
            $sumWeights += $weight;
        }

        $sumShares = 0;
        $lastNo = null;
        if ($sumWeights > 0) {
            foreach ($targets as $no) {
                $weight = (int)($weights[$no] ?? 0);
                if ($weight <= 0) {
                    continue;
                }

                $shareKyen = (int)floor($baseKyen * $weight / $sumWeights);
                $result[$no] = $shareKyen;
                $sumShares += $shareKyen;
                if ($shareKyen > 0) {
                    $lastNo = $no;
                }
            }
        }

        $diff = $baseKyen - $sumShares;
        if ($diff !== 0 && $lastNo !== null) {
            $result[$lastNo] = (int)($result[$lastNo] ?? 0) + $diff;
        }

        return $result;
    }

    private function resolveBasicDeductionFormulaLabel(array $payload, array $summary, int $legalHeirCount): string
    {
        $label = trim((string)(
            $payload['basic_deduction_formula_label']
            ?? $summary['basic_deduction_formula_label']
            ?? ''
        ));

        if ($label !== '') {
            return $label;
        }

        $baseKyen = $summary['basic_deduction_base_kyen']
            ?? $summary['basic_deduction_base_thousand']
            ?? null;
        $perHeirKyen = $summary['basic_deduction_per_heir_kyen']
            ?? $summary['basic_deduction_per_heir_thousand']
            ?? null;

        if (is_numeric($baseKyen) && is_numeric($perHeirKyen) && $legalHeirCount > 0) {
            return number_format((int)$baseKyen) . '千円＋'
                . number_format((int)$perHeirKyen) . '千円× ' . $legalHeirCount . '人';
        }

        return '';
    }
}