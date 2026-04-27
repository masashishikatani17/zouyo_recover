<?php

namespace App\Http\Controllers\GiftHistory;

use App\Http\Controllers\Controller;
use App\Models\GiftHistoryCase;
use App\Models\GiftHistoryFamilyMember;
use App\Models\GiftHistoryImportLog;
use App\Models\GiftHistoryRelationshipOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GiftHistoryFamilyController extends Controller
{
    private const FAMILY_ROW_COUNT = 15;

    public function edit(Request $request, GiftHistoryCase $case): View
    {
        $this->ensureRelationshipOptions($case, $request);
        $this->ensureFamilyRows($case, $request);

        $members = GiftHistoryFamilyMember::query()
            ->where('gift_history_case_id', $case->id)
            ->orderBy('row_no')
            ->get()
            ->keyBy('row_no');

        $relationshipOptions = $this->relationshipOptions($case);

        return view('gift_history.family.edit', [
            'case' => $case,
            'members' => $members,
            'relationshipOptions' => $relationshipOptions,
            'hasNonEmptyFamilyMember' => $this->hasNonEmptyFamilyMember($case),
            'backUrl' => $request->session()->get(
                'gift_history.index_back_url',
                route('gift-history.index')
            ),
        ]);
    }

    public function importFromZouyo(Request $request, GiftHistoryCase $case): RedirectResponse
    {
        $this->ensureRelationshipOptions($case, $request);
        $this->ensureFamilyRows($case, $request);

        if ($this->hasNonEmptyFamilyMember($case)) {
            return redirect()
                ->route('gift-history.family.edit', $case)
                ->with('error', '既に親族情報が入力されているため、初回取り込みは実行しませんでした。');
        }

        $sourceRows = DB::connection('zouyo_readonly')
            ->table('proposal_family_members')
            ->where('data_id', $case->data_id)
            ->orderBy('row_no')
            ->get()
            ->keyBy('row_no');

        $relationshipNames = $this->relationshipNameMap($case);

        DB::connection('gift_history')->transaction(function () use ($request, $case, $sourceRows, $relationshipNames): void {
            for ($rowNo = 1; $rowNo <= self::FAMILY_ROW_COUNT; $rowNo++) {
                $source = $sourceRows->get($rowNo);

                $payload = $source
                    ? $this->familyPayloadFromSource($source, $relationshipNames, $request)
                    : $this->emptyFamilyPayload($request);

                GiftHistoryFamilyMember::query()->updateOrCreate(
                    [
                        'gift_history_case_id' => $case->id,
                        'row_no' => $rowNo,
                    ],
                    $payload
                );
            }

            GiftHistoryImportLog::query()->create([
                'gift_history_case_id' => $case->id,
                'source_system' => 'zouyo',
                'data_id' => $case->data_id,
                'import_type' => 'family_import',
                'status' => 'success',
                'source_table' => 'proposal_family_members',
                'source_count' => $sourceRows->count(),
                'imported_count' => self::FAMILY_ROW_COUNT,
                'message' => '贈与名人から親族情報を初回取り込みしました。',
                'payload' => [
                    'source_count' => $sourceRows->count(),
                    'created_rows' => self::FAMILY_ROW_COUNT,
                ],
                'created_by' => $request->user()?->id,
            ]);

            $case->forceFill([
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ])->save();
        });

        return redirect()
            ->route('gift-history.family.edit', $case)
            ->with('status', '贈与名人から親族情報を取り込みました。');
    }

    public function update(Request $request, GiftHistoryCase $case): RedirectResponse
    {
        $this->ensureRelationshipOptions($case, $request);
        $this->ensureFamilyRows($case, $request);

        $members = (array) $request->input('members', []);
        $relationshipNames = $this->relationshipNameMap($case);

        DB::connection('gift_history')->transaction(function () use ($request, $case, $members, $relationshipNames): void {
            for ($rowNo = 1; $rowNo <= self::FAMILY_ROW_COUNT; $rowNo++) {
                $raw = (array) ($members[$rowNo] ?? []);

                $relationshipCode = $this->nullableInt($raw['relationship_code'] ?? null);
                $relationshipName = $relationshipCode !== null
                    ? ($relationshipNames[$relationshipCode] ?? null)
                    : null;

                GiftHistoryFamilyMember::query()->updateOrCreate(
                    [
                        'gift_history_case_id' => $case->id,
                        'row_no' => $rowNo,
                    ],
                    [
                        'source_system' => 'zouyo',
                        'name' => $this->nullableString($raw['name'] ?? null),
                        'gender' => $this->nullableString($raw['gender'] ?? null),
                        'relationship_code' => $relationshipCode,
                        'relationship_name_snapshot' => $relationshipName,
                        'adoption_note' => $this->nullableString($raw['adoption_note'] ?? null),
                        'heir_category' => $this->nullableInt($raw['heir_category'] ?? null, 0, 9),
                        'civil_share_bunbo' => $this->nullableInt($raw['civil_share_bunbo'] ?? null, 1),
                        'civil_share_bunsi' => $this->nullableInt($raw['civil_share_bunsi'] ?? null, 0),
                        'share_numerator' => $this->nullableInt($raw['share_numerator'] ?? null, 0),
                        'share_denominator' => $this->nullableInt($raw['share_denominator'] ?? null, 1),
                        'surcharge_twenty_percent' => $this->toBool($raw['surcharge_twenty_percent'] ?? false),
                        'tokurei_zouyo' => $this->toBool($raw['tokurei_zouyo'] ?? false),
                        'birth_year' => $this->nullableInt($raw['birth_year'] ?? null, 1800, 2200),
                        'birth_month' => $this->nullableInt($raw['birth_month'] ?? null, 1, 12),
                        'birth_day' => $this->nullableInt($raw['birth_day'] ?? null, 1, 31),
                        'age' => $this->nullableInt($raw['age'] ?? null, 0, 150),
                        'property_thousand' => $this->nullableInt($raw['property_thousand'] ?? null),
                        'cash_thousand' => $this->nullableInt($raw['cash_thousand'] ?? null),
                        'updated_by' => $request->user()?->id,
                    ]
                );
            }

            $case->forceFill([
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ])->save();
        });

        return redirect()
            ->route('gift-history.family.edit', $case)
            ->with('status', '親族情報を保存しました。');
    }

    private function ensureRelationshipOptions(GiftHistoryCase $case, Request $request): void
    {
        $exists = GiftHistoryRelationshipOption::query()
            ->where('gift_history_case_id', $case->id)
            ->exists();

        if ($exists) {
            return;
        }

        $sourceRows = $this->sourceRelationshipRows($case);

        DB::connection('gift_history')->transaction(function () use ($request, $case, $sourceRows): void {
            foreach ($sourceRows as $index => $source) {
                GiftHistoryRelationshipOption::query()->create([
                    'gift_history_case_id' => $case->id,
                    'source_system' => 'zouyo',
                    'source_relationship_master_id' => (int) $source->id,
                    'source_relation_no' => (int) $source->relation_no,
                    'relation_no' => (int) $source->relation_no,
                    'name' => $this->nullableString($source->name ?? null),
                    'is_editable' => (bool) $source->is_editable,
                    'sort_order' => $index + 1,
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]);
            }

            GiftHistoryImportLog::query()->create([
                'gift_history_case_id' => $case->id,
                'source_system' => 'zouyo',
                'data_id' => $case->data_id,
                'import_type' => 'relationship_import',
                'status' => 'success',
                'source_table' => 'relationship_masters',
                'source_count' => $sourceRows->count(),
                'imported_count' => $sourceRows->count(),
                'message' => '続柄候補を初期取り込みしました。',
                'payload' => [
                    'source_count' => $sourceRows->count(),
                ],
                'created_by' => $request->user()?->id,
            ]);
        });
    }

    private function ensureFamilyRows(GiftHistoryCase $case, Request $request): void
    {
        $existingRowNos = GiftHistoryFamilyMember::query()
            ->where('gift_history_case_id', $case->id)
            ->pluck('row_no')
            ->map(fn ($value) => (int) $value)
            ->all();

        $existingRowNos = array_flip($existingRowNos);

        for ($rowNo = 1; $rowNo <= self::FAMILY_ROW_COUNT; $rowNo++) {
            if (isset($existingRowNos[$rowNo])) {
                continue;
            }

            GiftHistoryFamilyMember::query()->create([
                'gift_history_case_id' => $case->id,
                'source_system' => 'zouyo',
                'row_no' => $rowNo,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);
        }
    }

    private function sourceRelationshipRows(GiftHistoryCase $case): Collection
    {
        $query = DB::connection('zouyo_readonly')
            ->table('relationship_masters')
            ->whereNotNull('name')
            ->whereRaw("TRIM(COALESCE(name, '')) <> ''")
            ->orderBy('relation_no');

        if ($case->company_id) {
            $query->where('company_id', $case->company_id);
        }

        $rows = $query->get();

        if ($rows->isNotEmpty()) {
            return $rows;
        }

        return DB::connection('zouyo_readonly')
            ->table('relationship_masters')
            ->whereNotNull('name')
            ->whereRaw("TRIM(COALESCE(name, '')) <> ''")
            ->orderBy('relation_no')
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

    private function hasNonEmptyFamilyMember(GiftHistoryCase $case): bool
    {
        return GiftHistoryFamilyMember::query()
            ->where('gift_history_case_id', $case->id)
            ->where(function ($query): void {
                $query->whereRaw("TRIM(COALESCE(name, '')) <> ''")
                    ->orWhereNotNull('relationship_code')
                    ->orWhereNotNull('birth_year')
                    ->orWhereNotNull('birth_month')
                    ->orWhereNotNull('birth_day')
                    ->orWhereNotNull('property_thousand')
                    ->orWhereNotNull('cash_thousand');
            })
            ->exists();
    }

    private function familyPayloadFromSource(object $source, array $relationshipNames, Request $request): array
    {
        $relationshipCode = $this->nullableInt($source->relationship_code ?? null);

        return [
            'source_system' => 'zouyo',
            'source_family_member_id' => (int) $source->id,
            'source_data_id' => (int) $source->data_id,
            'name' => $this->nullableString($source->name ?? null),
            'gender' => $this->nullableString($source->gender ?? null),
            'relationship_code' => $relationshipCode,
            'relationship_name_snapshot' => $relationshipCode !== null
                ? ($relationshipNames[$relationshipCode] ?? null)
                : null,
            'adoption_note' => $this->nullableString($source->adoption_note ?? null),
            'heir_category' => $this->nullableInt($source->heir_category ?? null, 0, 9),
            'civil_share_bunbo' => $this->nullableInt($source->civil_share_bunbo ?? null, 1),
            'civil_share_bunsi' => $this->nullableInt($source->civil_share_bunsi ?? null, 0),
            'share_numerator' => $this->nullableInt($source->share_numerator ?? null, 0),
            'share_denominator' => $this->nullableInt($source->share_denominator ?? null, 1),
            'surcharge_twenty_percent' => $this->toBool($source->surcharge_twenty_percent ?? false),
            'tokurei_zouyo' => $this->toBool($source->tokurei_zouyo ?? false),
            'birth_year' => $this->nullableInt($source->birth_year ?? null, 1800, 2200),
            'birth_month' => $this->nullableInt($source->birth_month ?? null, 1, 12),
            'birth_day' => $this->nullableInt($source->birth_day ?? null, 1, 31),
            'age' => $this->nullableInt($source->age ?? null, 0, 150),
            'property_thousand' => $this->nullableInt($source->property_thousand ?? null),
            'cash_thousand' => $this->nullableInt($source->cash_thousand ?? null),
            'updated_by' => $request->user()?->id,
        ];
    }

    private function emptyFamilyPayload(Request $request): array
    {
        return [
            'source_system' => 'zouyo',
            'updated_by' => $request->user()?->id,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value, ?int $min = null, ?int $max = null): ?int
    {
        if ($value === null) {
            return null;
        }

        $string = mb_convert_kana((string) $value, 'n', 'UTF-8');
        $string = str_replace([',', ' ', '　'], '', $string);

        if ($string === '') {
            return null;
        }

        if (! preg_match('/^-?\d+$/', $string)) {
            return null;
        }

        $int = (int) $string;

        if ($min !== null && $int < $min) {
            return null;
        }

        if ($max !== null && $int > $max) {
            return null;
        }

        return $int;
    }

    private function toBool(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }
}