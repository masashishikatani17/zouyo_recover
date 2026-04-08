<?php

namespace App\Services\Master;

use App\Models\RelationshipMaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RelationshipMasterService
{
    private const CACHE_TTL = 300;

    private const MIN_EDITABLE_NO = 42;
    private const MAX_EDITABLE_NO = 50;
    private const REQUIRED_EDITABLE_MAX = 45;

    /**
     * 既存テーブルの relation コード格納カラム候補
     * ここにある名前だけを探索対象にすることで誤爆を抑える。
     *
     * @var string[]
     */
    private const RELATION_COLUMNS = [
        'relationship_no',
        'relation_no',
        'relationship',
        'relation',
        'zokugara_no',
        'zokugara',
        'zokugara_cd',
    ];

    public function applyRuntimeConfig(?int $companyId): void
    {
        config()->set('relationships', $this->getRuntimeOptions($companyId));
    }

    /**
     * 画面表示用: No0〜50 を必ず返す
     */
    public function getAll(?int $companyId): Collection
    {
        if (!$companyId || !Schema::hasTable('relationship_masters')) {
            return $this->defaultRows();
        }

        $this->ensureCompanyRows($companyId);

        return RelationshipMaster::query()
            ->where('company_id', $companyId)
            ->orderBy('relation_no')
            ->get();
    }

    /**
     * 既存 config('relationships') 互換の配列を返す
     * - No0〜45 は名称があれば返す
     * - No46〜50 は空欄なら返さない（プルダウン非表示）
     *
     * @return array<int, string>
     */
    public function getRuntimeOptions(?int $companyId): array
    {
        if (!$companyId || !Schema::hasTable('relationship_masters')) {
            return $this->defaultRuntimeOptions();
        }

        return Cache::remember(
            $this->cacheKey($companyId),
            self::CACHE_TTL,
            function () use ($companyId): array {
                $rows = $this->getAll($companyId);
                $options = [];

                foreach ($rows as $row) {
                    $name = $this->normalizeName($row->name);
                    if ($name === null) {
                        continue;
                    }

                    $options[(int) $row->relation_no] = $name;
                }

                ksort($options);

                return $options;
            }
        );
    }

    /**
     * No42〜50 を保存する
     *
     * @param  array<int|string, array{name?: mixed}>  $inputRows
     */
    public function saveEditable(int $companyId, array $inputRows): void
    {
        if ($companyId <= 0) {
            throw ValidationException::withMessages([
                'relations' => '会社情報が取得できませんでした。',
            ]);
        }

        if (!Schema::hasTable('relationship_masters')) {
            throw ValidationException::withMessages([
                'relations' => 'relationship_masters テーブルが存在しません。先に migrate を実行してください。',
            ]);
        }

        $rows = $this->getAll($companyId)->keyBy('relation_no');
        $messages = [];
        $resolvedNames = [];

        foreach (range(0, self::MAX_EDITABLE_NO) as $no) {
            $currentName = $this->normalizeName(optional($rows->get($no))->name);

            if ($no < self::MIN_EDITABLE_NO) {
                $resolvedNames[$no] = $currentName;
                continue;
            }

            $inputName = data_get($inputRows, "{$no}.name");
            $name = $this->normalizeName(is_string($inputName) ? $inputName : null);

            if ($no <= self::REQUIRED_EDITABLE_MAX && $name === null) {
                $messages["relations.{$no}.name"] = "No{$no}は空欄にできません。";
            }

            if ($no > self::REQUIRED_EDITABLE_MAX && $name === null && $this->isRelationNoInUse($companyId, $no)) {
                $messages["relations.{$no}.name"] = "No{$no}は既存データで使用中のため空欄にできません。";
            }

            $resolvedNames[$no] = $name;
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        $this->assertNoDuplicateNames($resolvedNames);

        DB::transaction(function () use ($companyId, $resolvedNames): void {
            foreach (range(self::MIN_EDITABLE_NO, self::MAX_EDITABLE_NO) as $no) {
                RelationshipMaster::query()
                    ->where('company_id', $companyId)
                    ->where('relation_no', $no)
                    ->update([
                        'name'       => $resolvedNames[$no],
                        'updated_at' => now(),
                    ]);
            }
        });

        Cache::forget($this->cacheKey($companyId));
        $this->applyRuntimeConfig($companyId);
    }

    public function ensureCompanyRows(int $companyId): void
    {
        if ($companyId <= 0 || !Schema::hasTable('relationship_masters')) {
            return;
        }

        $defaults = $this->defaultNames();

        foreach (range(0, self::MAX_EDITABLE_NO) as $no) {
            RelationshipMaster::query()->firstOrCreate(
                [
                    'company_id'  => $companyId,
                    'relation_no' => $no,
                ],
                [
                    'name'        => $defaults[$no] ?? null,
                    'is_editable' => $no >= self::MIN_EDITABLE_NO,
                ]
            );
        }
    }

    /**
     * @return array<int, string|null>
     */
    private function defaultNames(): array
    {
        $defaults = config('relationship_defaults', []);
        $names = [];

        foreach ($defaults as $no => $name) {
            $names[(int) $no] = $this->normalizeName($name);
        }

        foreach (range(0, self::MAX_EDITABLE_NO) as $no) {
            if (!array_key_exists($no, $names)) {
                $names[$no] = null;
            }
        }

        ksort($names);

        return $names;
    }

    private function defaultRows(): Collection
    {
        $defaults = $this->defaultNames();

        return collect(range(0, self::MAX_EDITABLE_NO))->map(function (int $no) use ($defaults): object {
            return (object) [
                'company_id'  => null,
                'relation_no' => $no,
                'name'        => $defaults[$no] ?? null,
                'is_editable' => $no >= self::MIN_EDITABLE_NO,
            ];
        });
    }

    /**
     * @return array<int, string>
     */
    private function defaultRuntimeOptions(): array
    {
        $options = [];

        foreach ($this->defaultNames() as $no => $name) {
            if ($name === null) {
                continue;
            }
            $options[$no] = $name;
        }

        return $options;
    }

    /**
     * @param  array<int, string|null>  $names
     */
    private function assertNoDuplicateNames(array $names): void
    {
        $seen = [];
        $messages = [];

        foreach ($names as $no => $name) {
            if ($name === null) {
                continue;
            }

            $key = $this->duplicateKey($name);

            if (isset($seen[$key])) {
                if ($no >= self::MIN_EDITABLE_NO) {
                    $messages["relations.{$no}.name"] = "No{$no}の続柄「{$name}」は No{$seen[$key]} と重複しています。";
                }
                continue;
            }

            $seen[$key] = $no;
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function duplicateKey(string $name): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($name, 'UTF-8')
            : strtolower($name);
    }

    private function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return $normalized === '' ? null : $normalized;
    }

    private function cacheKey(int $companyId): string
    {
        return "relationship_masters:company:{$companyId}";
    }

    private function isRelationNoInUse(int $companyId, int $relationNo): bool
    {
        $targets = $this->usageTargets();

        foreach ($targets as $target) {
            $table = $target['table'];
            $relationColumn = $target['relation_column'];

            $query = DB::table($table)->where("{$table}.{$relationColumn}", $relationNo);

            if ($target['has_deleted_at']) {
                $query->whereNull("{$table}.deleted_at");
            }

            if ($target['scope'] === 'company') {
                $query->where("{$table}.company_id", $companyId);
            } else {
                $query->join('datas', "{$table}.data_id", '=', 'datas.id')
                    ->where('datas.company_id', $companyId);

                if ($target['datas_has_deleted_at']) {
                    $query->whereNull('datas.deleted_at');
                }
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * relation コードを持つテーブルを緩く検出する
     *
     * @return array<int, array{
     *   table: string,
     *   relation_column: string,
     *   scope: 'company'|'data',
     *   has_deleted_at: bool,
     *   datas_has_deleted_at: bool
     * }>
     */
    private function usageTargets(): array
    {
        $tables = $this->listTables();
        $targets = [];

        $hasDatas = in_array('datas', $tables, true);
        $datasColumns = $hasDatas ? Schema::getColumnListing('datas') : [];
        $datasHasDeletedAt = in_array('deleted_at', $datasColumns, true);

        foreach ($tables as $table) {
            if (in_array($table, ['migrations', 'relationship_masters'], true)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $relationColumn = $this->firstExistingColumn($columns, self::RELATION_COLUMNS);

            if ($relationColumn === null) {
                continue;
            }

            if (in_array('company_id', $columns, true)) {
                $targets[] = [
                    'table'               => $table,
                    'relation_column'     => $relationColumn,
                    'scope'               => 'company',
                    'has_deleted_at'      => in_array('deleted_at', $columns, true),
                    'datas_has_deleted_at'=> false,
                ];
                continue;
            }

            if ($hasDatas && in_array('data_id', $columns, true)) {
                $targets[] = [
                    'table'               => $table,
                    'relation_column'     => $relationColumn,
                    'scope'               => 'data',
                    'has_deleted_at'      => in_array('deleted_at', $columns, true),
                    'datas_has_deleted_at'=> $datasHasDeletedAt,
                ];
            }
        }

        return $targets;
    }

    /**
     * @param  string[]  $columns
     * @param  string[]  $candidates
     */
    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function listTables(): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return array_map(
                static fn ($row): string => (string) array_values((array) $row)[0],
                DB::select('SHOW TABLES')
            );
        }

        if ($driver === 'sqlite') {
            return array_map(
                static fn ($row): string => (string) ($row->name ?? ''),
                DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
            );
        }

        return [];
    }
}