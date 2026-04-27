<?php

namespace App\Http\Controllers\GiftHistory;

use App\Http\Controllers\Controller;
use App\Models\GiftHistoryCase;
use App\Models\GiftHistoryImportLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GiftHistoryCaseController extends Controller
{
    private const PER_PAGE_OPTIONS = [20, 50, 100];

    private const SORT_FIELDS = [
        'no',
        'customer_name',
        'data_name',
        'entry_count',
        'updated_at',
    ];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $sort = $this->resolveSort((string) $request->query('sort', 'updated_at'));
        $direction = $this->resolveDirection((string) $request->query('direction', 'desc'));
        $perPage = $this->resolvePerPage((int) $request->query('per_page', 20));
        $page = max(1, (int) $request->query('page', 1));

        $sourceRows = $this->fetchSourceRows($request);

        if ($q !== '') {
            $sourceRows = $sourceRows->filter(function (object $row) use ($q): bool {
                return str_contains((string) ($row->customer_name ?? ''), $q);
            })->values();
        }

        $rows = $this->attachGiftHistoryCases($sourceRows);
        $rows = $this->sortRows($rows, $sort, $direction);

        $cases = $this->paginateRows($rows, $perPage, $page, $request);

        return view('gift_history.cases.index', [
            'cases' => $cases,
            'q' => $q,
            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'data_id' => ['required', 'integer', 'min:1'],
            'back_url' => ['nullable', 'string'],
        ]);

        $source = $this->findSourceRow((int) $validated['data_id']);

        abort_unless($source, 404, '対象データが見つかりません。');

        $this->abortIfCompanyMismatch($request, $source);

        $case = GiftHistoryCase::query()->firstOrCreate(
            [
                'source_system' => 'zouyo',
                'data_id' => (int) $source->data_id,
            ],
            [
                'proposal_header_id' => $source->proposal_header_id ? (int) $source->proposal_header_id : null,
                'company_id' => $source->company_id ? (int) $source->company_id : null,
                'group_id' => $source->group_id ? (int) $source->group_id : null,
                'customer_name_snapshot' => $source->customer_name,
                'data_name_snapshot' => $source->data_name,
                'title_snapshot' => $source->title,
                'source_updated_at' => $source->source_updated_at,
                'entries_count' => 0,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]
        );

        if ($case->wasRecentlyCreated) {
            GiftHistoryImportLog::query()->create([
                'gift_history_case_id' => $case->id,
                'source_system' => 'zouyo',
                'data_id' => (int) $source->data_id,
                'import_type' => 'case_start',
                'status' => 'success',
                'source_table' => 'datas,proposal_headers',
                'source_count' => 1,
                'imported_count' => 1,
                'message' => '贈与履歴管理データを開始しました。',
                'payload' => [
                    'data_id' => (int) $source->data_id,
                    'proposal_header_id' => $source->proposal_header_id ? (int) $source->proposal_header_id : null,
                    'customer_name' => $source->customer_name,
                    'data_name' => $source->data_name,
                    'title' => $source->title,
                ],
                'created_by' => $request->user()?->id,
            ]);
        }

        $this->rememberBackUrl($request);

        return redirect()
            ->route('gift-history.show', $case)
            ->with('status', $case->wasRecentlyCreated
                ? '贈与履歴管理データを開始しました。'
                : '既存の贈与履歴管理データを開きます。');
    }

    public function show(Request $request, GiftHistoryCase $case): View
    {
        $backUrl = $request->session()->get('gift_history.index_back_url', route('gift-history.index'));

        return view('gift_history.cases.show', [
            'case' => $case,
            'backUrl' => $backUrl,
        ]);
    }

    private function fetchSourceRows(Request $request): Collection
    {
        $bindings = [];
        $where = '';
        $whereParts = [
            "TRIM(COALESCE(h.customer_name, '')) <> ''",
        ];        

        $companyId = $request->user()?->getAttribute('company_id');

        if ($companyId !== null && $companyId !== '') {
            $whereParts[] = 'd.company_id = ?';
            $bindings[] = $companyId;
        }

 
        $where = 'WHERE ' . implode(' AND ', $whereParts);


        $sql = <<<SQL
            SELECT
                d.id AS data_id,
                d.company_id,
                d.group_id,
                d.data_name,
                h.id AS proposal_header_id,
                h.customer_name,
                h.title,
                COALESCE(h.updated_at, d.updated_at) AS source_updated_at
            FROM datas d
            INNER JOIN proposal_headers h
                ON h.data_id = d.id
            {$where}
        SQL;

        return collect(DB::connection('zouyo_readonly')->select($sql, $bindings));
    }

    private function findSourceRow(int $dataId): ?object
    {
        return DB::connection('zouyo_readonly')->selectOne(
            <<<SQL
                SELECT
                    d.id AS data_id,
                    d.company_id,
                    d.group_id,
                    d.data_name,
                    h.id AS proposal_header_id,
                    h.customer_name,
                    h.title,
                    COALESCE(h.updated_at, d.updated_at) AS source_updated_at
                FROM datas d
                INNER JOIN proposal_headers h                
                    ON h.data_id = d.id
                WHERE d.id = ?
                  AND TRIM(COALESCE(h.customer_name, '')) <> ''                
                LIMIT 1
            SQL,
            [$dataId]
        );
    }

    private function attachGiftHistoryCases(Collection $sourceRows): Collection
    {
        $dataIds = $sourceRows
            ->pluck('data_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $casesByDataId = $dataIds->isEmpty()
            ? collect()
            : GiftHistoryCase::query()
                ->where('source_system', 'zouyo')
                ->whereIn('data_id', $dataIds->all())
                ->get()
                ->keyBy('data_id');

        return $sourceRows->map(function (object $source) use ($casesByDataId): object {
            /** @var GiftHistoryCase|null $case */
            $case = $casesByDataId->get((int) $source->data_id);

            return (object) [
                'data_id' => (int) $source->data_id,
                'customer_name' => (string) ($source->customer_name ?? ''),
                'data_name' => (string) ($source->data_name ?? ''),
                'title' => (string) ($source->title ?? ''),
                'source_updated_at' => $source->source_updated_at,
                'case_id' => $case?->id,
                'entry_count' => $case?->entries_count ?? 0,
                'history_updated_at' => $case?->updated_at,
                'has_case' => $case !== null,
            ];
        })->values();
    }

    private function sortRows(Collection $rows, string $sort, string $direction): Collection
    {
        return $rows->sort(function (object $a, object $b) use ($sort, $direction): int {
            if ($sort === 'updated_at') {
                $cmp = $this->compareUpdatedAt($a, $b, $direction);
            } else {
                $cmp = match ($sort) {
                    'no' => $a->data_id <=> $b->data_id,
                    'customer_name' => strcmp($this->sortString($a->customer_name), $this->sortString($b->customer_name)),
                    'data_name' => strcmp($this->sortString($a->data_name), $this->sortString($b->data_name)),
                    'entry_count' => $a->entry_count <=> $b->entry_count,
                    default => 0,
                };

                $cmp = $direction === 'asc' ? $cmp : -$cmp;
            }

            return $cmp !== 0 ? $cmp : ($a->data_id <=> $b->data_id);
        })->values();
    }

    private function compareUpdatedAt(object $a, object $b, string $direction): int
    {
        // 贈与履歴作成済みを常に上に出す。未作成は最終更新日がないため後ろ。
        if ($a->has_case !== $b->has_case) {
            return $a->has_case ? -1 : 1;
        }

        $aTimestamp = $a->history_updated_at?->getTimestamp() ?? 0;
        $bTimestamp = $b->history_updated_at?->getTimestamp() ?? 0;

        $cmp = $aTimestamp <=> $bTimestamp;

        return $direction === 'asc' ? $cmp : -$cmp;
    }

    private function paginateRows(Collection $rows, int $perPage, int $page, Request $request): LengthAwarePaginator
    {
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
            'query' => $request->query(),
            ]
        );
    }

    private function resolveSort(string $sort): string
    {
        return in_array($sort, self::SORT_FIELDS, true) ? $sort : 'updated_at';
    }

    private function resolveDirection(string $direction): string
    {
        return $direction === 'asc' ? 'asc' : 'desc';
    }

    private function resolvePerPage(int $perPage): int
    {
        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 20;
    }

    private function sortString(?string $value): string
    {
        $value = trim((string) $value);

        return function_exists('mb_strtolower')
            ? mb_strtolower($value)
            : strtolower($value);
    }

    private function rememberBackUrl(Request $request): void
    {
        $backUrl = (string) $request->input('back_url', '');

        if ($backUrl !== '' && str_starts_with($backUrl, url('/gift-history'))) {
            $request->session()->put('gift_history.index_back_url', $backUrl);
        }
    }

    private function abortIfCompanyMismatch(Request $request, object $source): void
    {
        $companyId = $request->user()?->getAttribute('company_id');

        if ($companyId === null || $companyId === '') {
            return;
        }

        abort_if((int) $source->company_id !== (int) $companyId, 403);
    }
}