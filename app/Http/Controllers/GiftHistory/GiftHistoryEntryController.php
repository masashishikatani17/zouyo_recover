<?php

namespace App\Http\Controllers\GiftHistory;

use App\Http\Controllers\Controller;
use App\Models\GiftHistoryCase;
use App\Models\GiftHistoryEntry;
use App\Models\GiftHistoryFamilyMember;
use App\Models\GiftHistoryRelationshipOption;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GiftHistoryEntryController extends Controller
{
    private const ASSET_CATEGORIES = [
        'cash' => '現金',
        'deposit' => '預金',
        'listed_stock' => '上場株式',
        'unlisted_stock' => '非上場株式',
        'land' => '土地',
        'building' => '建物',
        'other' => 'その他',
    ];

    public function create(Request $request, GiftHistoryCase $case): View|RedirectResponse
    {
        $familyMembers = $this->familyMembers($case);

        if ($familyMembers->count() < 2) {
            return redirect()
                ->route('gift-history.family.edit', $case)
                ->with('error', '生前贈与を登録するには、親族情報を2名以上入力してください。');
        }

        return view('gift_history.entries.create', [
            'case' => $case,
            'familyMembers' => $familyMembers,
            'relationshipOptions' => $this->relationshipOptions($case),
            'assetCategories' => self::ASSET_CATEGORIES,
            'backUrl' => $request->session()->get(
                'gift_history.index_back_url',
                route('gift-history.index')
            ),
        ]);
    }

    public function store(Request $request, GiftHistoryCase $case): RedirectResponse
    {
        $validated = $request->validate([
            'gift_taxation_type' => ['required', Rule::in(['calendar', 'settlement'])],
            'gift_date' => ['required', 'date'],
            'donor_family_member_id' => ['required', 'integer'],
            'recipient_family_member_id' => ['required', 'integer'],
            'donor_relationship_code_from_recipient' => ['required', 'integer'],
            'recipient_relationship_code_from_donor' => ['required', 'integer'],
            'asset_category' => ['required', Rule::in(array_keys(self::ASSET_CATEGORIES))],
            'asset_name' => ['nullable', 'string', 'max:255'],
            'asset_description' => ['nullable', 'string'],
            'gift_amount_yen' => ['required', 'string', 'max:30'],
            'calendar_tax_type' => ['nullable', Rule::in(['general', 'tokurei'])],
            'settlement_notification_date' => ['nullable', 'date'],
            'tax_override_amount_yen' => ['nullable', 'string', 'max:30'],
            'tax_override_reason' => ['nullable', 'string', 'max:255'],
            'tax_return_status' => ['nullable', 'string', 'max:50'],
            'gift_contract_status' => ['nullable', 'string', 'max:50'],
            'memo' => ['nullable', 'string'],
        ], [], [
            'gift_taxation_type' => '贈与形態',
            'gift_date' => '贈与日',
            'donor_family_member_id' => '贈与者',
            'recipient_family_member_id' => '受贈者',
            'donor_relationship_code_from_recipient' => '贈与者の続柄',
            'recipient_relationship_code_from_donor' => '受贈者の続柄',
            'asset_category' => '贈与財産の種類',
            'gift_amount_yen' => '贈与額',
            'calendar_tax_type' => '暦年贈与の税率区分',
        ]);

        $errors = [];

        $giftTaxationType = (string) $validated['gift_taxation_type'];
        $giftDate = CarbonImmutable::parse((string) $validated['gift_date'])->startOfDay();
        $giftYear = (int) $giftDate->format('Y');

        $donor = $this->findFamilyMember($case, (int) $validated['donor_family_member_id']);
        $recipient = $this->findFamilyMember($case, (int) $validated['recipient_family_member_id']);

        if (! $donor) {
            $errors['donor_family_member_id'] = '贈与者を正しく選択してください。';
        }

        if (! $recipient) {
            $errors['recipient_family_member_id'] = '受贈者を正しく選択してください。';
        }

        if ($donor && $recipient && $donor->id === $recipient->id) {
            $errors['recipient_family_member_id'] = '贈与者と受贈者が同一です。';
        }

        $relationshipNames = $this->relationshipNameMap($case);

        $donorRelationshipCode = $this->nullableInt($validated['donor_relationship_code_from_recipient'] ?? null);
        $recipientRelationshipCode = $this->nullableInt($validated['recipient_relationship_code_from_donor'] ?? null);

        $donorRelationshipName = $donorRelationshipCode !== null
            ? ($relationshipNames[$donorRelationshipCode] ?? null)
            : null;

        $recipientRelationshipName = $recipientRelationshipCode !== null
            ? ($relationshipNames[$recipientRelationshipCode] ?? null)
            : null;

        if ($donorRelationshipName === null) {
            $errors['donor_relationship_code_from_recipient'] = '贈与者の続柄を正しく選択してください。';
        }

        if ($recipientRelationshipName === null) {
            $errors['recipient_relationship_code_from_donor'] = '受贈者の続柄を正しく選択してください。';
        }

        $giftAmountYen = $this->parseYen($validated['gift_amount_yen'] ?? null);

        if ($giftAmountYen === null || $giftAmountYen < 0) {
            $errors['gift_amount_yen'] = '贈与額を円単位で入力してください。';
        }

        $calendarTaxType = $giftTaxationType === 'calendar'
            ? (string) ($validated['calendar_tax_type'] ?? '')
            : null;

        if ($giftTaxationType === 'calendar' && ! in_array($calendarTaxType, ['general', 'tokurei'], true)) {
            $errors['calendar_tax_type'] = '暦年贈与の場合は、一般贈与または特例贈与を選択してください。';
        }

        $settlementElectionConfirmed = $this->toBool($request->input('settlement_election_confirmed', false));
        $settlementNoReturnConfirmed = $this->toBool($request->input('settlement_no_return_confirmed', false));

        if ($giftTaxationType === 'settlement') {
            if (! $settlementElectionConfirmed) {
                $errors['settlement_election_confirmed'] = '相続時精算課税選択届出書の提出済み、または提出予定を確認してください。';
            }

            if (! $settlementNoReturnConfirmed) {
                $errors['settlement_no_return_confirmed'] = 'この贈与者について、以後は暦年課税に戻れないことを確認してください。';
            }
        }

        $taxOverrideEnabled = $this->toBool($request->input('tax_override_enabled', false));
        $taxOverrideAmountYen = $this->parseNullableYen($validated['tax_override_amount_yen'] ?? null);

        if ($taxOverrideEnabled && $taxOverrideAmountYen === null) {
            $errors['tax_override_amount_yen'] = '手入力チェックONの場合は、手入力の贈与税額を入力してください。';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $deadlines = $this->calculateAddbackDeadlines($giftTaxationType, $giftDate);

        // 第1実装では税額自動計算は後続フェーズとし、0円を参考値として保存する。
        $taxAutoAmountYen = 0;
        $taxFinalAmountYen = $taxOverrideEnabled
            ? (int) $taxOverrideAmountYen
            : $taxAutoAmountYen;

        DB::connection('gift_history')->transaction(function () use (
            $request,
            $case,
            $validated,
            $giftTaxationType,
            $giftDate,
            $giftYear,
            $donor,
            $recipient,
            $donorRelationshipCode,
            $donorRelationshipName,
            $recipientRelationshipCode,
            $recipientRelationshipName,
            $giftAmountYen,
            $calendarTaxType,
            $settlementElectionConfirmed,
            $settlementNoReturnConfirmed,
            $deadlines,
            $taxAutoAmountYen,
            $taxOverrideEnabled,
            $taxOverrideAmountYen,
            $taxFinalAmountYen
        ): void {
            GiftHistoryEntry::query()->create([
                'gift_history_case_id' => $case->id,
                'gift_taxation_type' => $giftTaxationType,
                'gift_date' => $giftDate->toDateString(),
                'gift_year' => $giftYear,
                'donor_family_member_id' => $donor->id,
                'recipient_family_member_id' => $recipient->id,
                'donor_name_snapshot' => $donor->name,
                'recipient_name_snapshot' => $recipient->name,
                'donor_relationship_code_from_recipient' => $donorRelationshipCode,
                'donor_relationship_from_recipient' => $donorRelationshipName,
                'recipient_relationship_code_from_donor' => $recipientRelationshipCode,
                'recipient_relationship_from_donor' => $recipientRelationshipName,
                'asset_category' => $validated['asset_category'],
                'asset_category_name_snapshot' => self::ASSET_CATEGORIES[$validated['asset_category']] ?? null,
                'asset_name' => $this->nullableString($validated['asset_name'] ?? null),
                'asset_description' => $this->nullableString($validated['asset_description'] ?? null),
                'gift_amount_yen' => (int) $giftAmountYen,
                'calendar_tax_type' => $calendarTaxType,
                'addback_3year_deadline_date' => $deadlines['three_year'],
                'addback_final_deadline_date' => $deadlines['final'],
                'settlement_election_confirmed' => $settlementElectionConfirmed,
                'settlement_no_return_confirmed' => $settlementNoReturnConfirmed,
                'settlement_notification_date' => $giftTaxationType === 'settlement'
                    ? ($validated['settlement_notification_date'] ?? null)
                    : null,
                'tax_auto_amount_yen' => $taxAutoAmountYen,
                'tax_override_enabled' => $taxOverrideEnabled,
                'tax_override_amount_yen' => $taxOverrideAmountYen,
                'tax_final_amount_yen' => $taxFinalAmountYen,
                'tax_override_reason' => $this->nullableString($validated['tax_override_reason'] ?? null),
                'tax_return_status' => $this->nullableString($validated['tax_return_status'] ?? null),
                'gift_contract_status' => $this->nullableString($validated['gift_contract_status'] ?? null),
                'memo' => $this->nullableString($validated['memo'] ?? null),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            $case->forceFill([
                'entries_count' => GiftHistoryEntry::query()
                    ->where('gift_history_case_id', $case->id)
                    ->count(),
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ])->save();
        });

        return redirect()
            ->route('gift-history.entries.create', $case)
            ->with('status', '生前贈与明細を登録しました。続けて登録できます。');
    }

    private function familyMembers(GiftHistoryCase $case): Collection
    {
        return GiftHistoryFamilyMember::query()
            ->where('gift_history_case_id', $case->id)
            ->whereRaw("TRIM(COALESCE(name, '')) <> ''")
            ->orderBy('row_no')
            ->get();
    }

    private function relationshipOptions(GiftHistoryCase $case): Collection
    {
        return GiftHistoryRelationshipOption::query()
            ->where('gift_history_case_id', $case->id)
            ->whereNotNull('name')
            ->whereRaw("TRIM(COALESCE(name, '')) <> ''")
            ->orderBy('sort_order')
            ->orderBy('relation_no')
            ->get();
    }

    private function relationshipNameMap(GiftHistoryCase $case): array
    {
        return $this->relationshipOptions($case)
            ->pluck('name', 'relation_no')
            ->all();
    }

    private function findFamilyMember(GiftHistoryCase $case, int $id): ?GiftHistoryFamilyMember
    {
        return GiftHistoryFamilyMember::query()
            ->where('gift_history_case_id', $case->id)
            ->where('id', $id)
            ->whereRaw("TRIM(COALESCE(name, '')) <> ''")
            ->first();
    }

    private function calculateAddbackDeadlines(string $giftTaxationType, CarbonImmutable $giftDate): array
    {
        if ($giftTaxationType !== 'calendar') {
            return [
                'three_year' => null,
                'final' => null,
            ];
        }

        $threeYear = $giftDate->addYearsNoOverflow(3);
        $lawChangeDate = CarbonImmutable::create(2024, 1, 1)->startOfDay();
        $final = $giftDate->lt($lawChangeDate)
            ? $threeYear
            : $giftDate->addYearsNoOverflow(7);

        return [
            'three_year' => $threeYear->toDateString(),
            'final' => $final->toDateString(),
        ];
    }

    private function parseYen(mixed $value): ?int
    {
        $value = $this->normalizeNumericString($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    private function parseNullableYen(mixed $value): ?int
    {
        $value = $this->normalizeNumericString($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    private function normalizeNumericString(mixed $value): string
    {
        $string = mb_convert_kana((string) ($value ?? ''), 'n', 'UTF-8');
        $string = str_replace([',', ' ', '　'], '', $string);

        return trim($string);
    }

    private function nullableInt(mixed $value): ?int
    {
        $string = $this->normalizeNumericString($value);

        if ($string === '') {
            return null;
        }

        return preg_match('/^-?\d+$/', $string) ? (int) $string : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function toBool(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }
}