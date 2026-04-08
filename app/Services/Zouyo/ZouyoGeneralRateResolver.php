 <?php
 
 namespace App\Services\Zouyo;
 
 use App\Models\ZouyoGeneralRate;
 use Illuminate\Support\Collection;
 use RuntimeException;
 
 class ZouyoGeneralRateResolver
 {
     public function getRows(?int $companyId = null, ?int $kihuYear = null): Collection
     {
        $preferredYear = ($kihuYear !== null && $kihuYear > 0) ? $kihuYear : null;

        $queryBase = ZouyoGeneralRate::query()
            ->select([
                'id',
                'company_id',
                'kihu_year',
                'version',
                'seq',
                'lower',
                'upper',
                'rate',
                'deduction_amount',
                'basic_deduction_amount',
            ]);

        if ($companyId !== null && $companyId > 0) {
            $queryBase->where(function ($q) use ($companyId): void {
                $q->where('company_id', $companyId)
                  ->orWhereNull('company_id');
            });
        } else {
            $queryBase->whereNull('company_id');
        }

        $pickRows = function (?int $year) use ($queryBase, $companyId): Collection {
            $base = clone $queryBase;

            if ($year !== null) {
                $base->where('kihu_year', $year);
            }

            $picked = (clone $base)
                ->when(
                    $companyId !== null && $companyId > 0,
                    fn ($q) => $q->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId]),
                    fn ($q) => $q->orderByRaw('CASE WHEN company_id IS NULL THEN 0 ELSE 1 END')
                )
                ->orderByDesc('kihu_year')
                ->orderByDesc('version')
                ->orderBy('id')
                ->first();

            if (!$picked) {
                return collect();
            }

            return (clone $base)
                ->where('kihu_year', (int) $picked->kihu_year)
                ->where('version', (int) $picked->version)
                ->orderBy('seq')
                ->get()
                ->groupBy('seq')
                ->map(function (Collection $group) use ($companyId) {
                    return $group->sortBy(
                        static function ($row) use ($companyId): int {
                            if ($companyId !== null && $companyId > 0 && $row->company_id !== null && (int) $row->company_id === $companyId) {
                                return 0;
                            }

                            return $row->company_id === null ? 1 : 2;
                        }
                    )->first();
                })
                ->sortKeys()
                ->values();
        };

        $rows = $pickRows($preferredYear);

        if ($rows->isEmpty()) {
            $rows = $pickRows(null);
        }

        if ($rows->isEmpty()) {
            throw new RuntimeException('贈与税の速算表(一般税率)が見つかりません。');
        }

        return $rows;
     }
 
     public function getBasicDeductionYen(?int $companyId = null, ?int $kihuYear = null): int
     {
        $rows = $this->getRows($companyId, $kihuYear);

        $value = (int) optional(
            $rows->first(static fn ($row) => (int) ($row->basic_deduction_amount ?? 0) > 0)
        )->basic_deduction_amount;
 
         if ($value <= 0) {
             throw new RuntimeException('贈与税の基礎控除額が一般税率マスターに設定されていません。');
         }
 
         return $value;
     }
 
     public function getBasicDeductionLabel(?int $companyId = null, ?int $kihuYear = null): string
     {
         return '年間' . number_format($this->getBasicDeductionYen($companyId, $kihuYear)) . '円';
     }
 
     public function getBasicDeductionFormulaLabel(?int $companyId = null, ?int $kihuYear = null): string
     {
         return '基礎控除額 ' . number_format($this->getBasicDeductionYen($companyId, $kihuYear)) . '円';
     }
 }