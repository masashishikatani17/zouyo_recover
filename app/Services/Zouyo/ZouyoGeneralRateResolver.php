<?php
 
namespace App\Services\Zouyo;
 
use App\Models\ZouyoGeneralRate;
use Illuminate\Support\Collection;
use RuntimeException;

class ZouyoGeneralRateResolver
{


     /**
      * 基本SELECT部分
      */
     private function baseQuery(): \Illuminate\Database\Eloquent\Builder
     {
        return ZouyoGeneralRate::query()
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
     }

     /**
      * company_id がある場合は company一致を優先し、無ければ global(null) を使う
      */
     private function applyCompanyScope(\Illuminate\Database\Eloquent\Builder $query, ?int $companyId = null): void
     {
        if ($companyId !== null && $companyId > 0) {
            $query->where(function ($q) use ($companyId): void {
                $q->where('company_id', $companyId)
                  ->orWhereNull('company_id');
            });
        } else {
            $query->whereNull('company_id');
        }
     }

     /**
      * 同一 seq に company 行と global 行が混在している場合の優先順位
      */
     private function pickPreferredRows(
        \Illuminate\Database\Eloquent\Builder $query,
        ?int $companyId = null
     ): Collection {
        return $query
            ->orderBy('seq')
            ->get()
            ->groupBy('seq')
            ->map(function (Collection $group) use ($companyId) {
                return $group->sortBy(
                    static function ($row) use ($companyId): int {
                        if ($companyId !== null && $companyId > 0) {
                            if ($row->company_id !== null && (int) $row->company_id === $companyId) {
                                return 0;
                            }

                            return $row->company_id === null ? 1 : 2;
                        }

                        return $row->company_id === null ? 0 : 1;
                    }
                )->first();
            })
            ->sortKeys()
            ->values();
     }






     public function getRows(?int $companyId = null, ?int $kihuYear = null): Collection
     {
        $preferredYear = ($kihuYear !== null && $kihuYear > 0) ? $kihuYear : null;

        $queryBase = $this->baseQuery();
        $this->applyCompanyScope($queryBase, $companyId);

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



            return $this->pickPreferredRows(
                (clone $base)
                ->where('kihu_year', (int) $picked->kihu_year)
                ->where('version', (int) $picked->version)
                ,
                $companyId
            );

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
 
 

     /**
      * 単票マスター(id=1固定)用:
      * id=1 の行をアンカーとして、その行と同じ kihu_year / version を持つ行群を返す。
      *
      * 現行テーブルは seq を持つ複数行構成のため、
      * 「id=1 の1行だけ」を返すのではなく、「id=1 が属する版一式」を返す。
      */
     public function getRowsFromSingleMaster(?int $companyId = null): Collection
     {
        $anchor = $this->baseQuery()->find(1);

        if (!$anchor) {
            // 旧ロジックへフォールバック（未初期化環境の保険）
            return $this->getRows($companyId, null);
        }

        $query = $this->baseQuery()
            ->where('kihu_year', (int) $anchor->kihu_year)
            ->where('version', (int) $anchor->version);

        $this->applyCompanyScope($query, $companyId);

        $rows = $this->pickPreferredRows($query, $companyId);

        if ($rows->isEmpty()) {
            throw new RuntimeException('贈与税の速算表(一般税率)の単票マスターが見つかりません。');
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
 
 
 

     /**
      * 単票マスター(id=1固定)から基礎控除額を取得
      */
     public function getBasicDeductionYenFromSingleMaster(?int $companyId = null): int
     {
        $rows = $this->getRowsFromSingleMaster($companyId);

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
     

     public function getBasicDeductionLabelFromSingleMaster(?int $companyId = null): string
     {
         return '年間' . number_format($this->getBasicDeductionYenFromSingleMaster($companyId)) . '円';
     }     
     
 
     public function getBasicDeductionFormulaLabel(?int $companyId = null, ?int $kihuYear = null): string
     {
         return '基礎控除額 ' . number_format($this->getBasicDeductionYen($companyId, $kihuYear)) . '円';
     }
     

     public function getBasicDeductionFormulaLabelFromSingleMaster(?int $companyId = null): string
     {
         return '基礎控除額 ' . number_format($this->getBasicDeductionYenFromSingleMaster($companyId)) . '円';
     }     
     
     
}