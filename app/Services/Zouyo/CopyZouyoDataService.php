<?php

namespace App\Services\Zouyo;

use App\Models\Data;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CopyZouyoDataService
{
    /**
     * 既存 data_id 配下の関連一式を新しい data_id へコピーする
     */
    public function copy(
        int $sourceDataId,
        int $companyId,
        int $targetGroupId,
        ?int $kihuYear,
        string $dataName
    ): Data {
        return DB::transaction(function () use ($sourceDataId, $companyId, $targetGroupId, $kihuYear, $dataName) {
            $source = Data::with([
                'proposalHeader',
                'proposalFamilyMembers',
                'pastGiftInput',
                'pastGiftRecipients',
                'pastGiftCalendarEntries',
                'pastGiftSettlementEntries',
                'futureGiftHeader',
                'futureGiftRecipients',
                'futureGiftPlanEntries',
                'inheritanceDistributionHeader',
                'inheritanceDistributionMembers',
            ])->findOrFail($sourceDataId);

            $newData = new Data();
            $newData->company_id = $companyId;
            $newData->group_id = $targetGroupId;
            $newData->kihu_year = $kihuYear;
            $newData->data_name = $dataName;
            $newData->save();

            $this->copyHasOne($source->proposalHeader, $newData, 'proposalHeader');
            $this->copyHasMany($source->proposalFamilyMembers, $newData, 'proposalFamilyMembers');

            $this->copyHasOne($source->pastGiftInput, $newData, 'pastGiftInput');
            $this->copyHasMany($source->pastGiftRecipients, $newData, 'pastGiftRecipients');
            $this->copyHasMany($source->pastGiftCalendarEntries, $newData, 'pastGiftCalendarEntries');
            $this->copyHasMany($source->pastGiftSettlementEntries, $newData, 'pastGiftSettlementEntries');

            $this->copyHasOne($source->futureGiftHeader, $newData, 'futureGiftHeader');
            $this->copyHasMany($source->futureGiftRecipients, $newData, 'futureGiftRecipients');
            $this->copyHasMany($source->futureGiftPlanEntries, $newData, 'futureGiftPlanEntries');

            $this->copyHasOne($source->inheritanceDistributionHeader, $newData, 'inheritanceDistributionHeader');
            $this->copyHasMany($source->inheritanceDistributionMembers, $newData, 'inheritanceDistributionMembers');

            return $newData;
        });
    }

    protected function copyHasOne(?Model $sourceModel, Data $newData, string $relationName): void
    {
        if (!$sourceModel || !$sourceModel->exists) {
            return;
        }

        $attrs = $this->filteredAttributes($sourceModel);
        /** @var HasOne $relation */
        $relation = $newData->{$relationName}();

        $newModel = $relation->getRelated()->newInstance();
        $newModel->forceFill($attrs);
        $newModel->{$relation->getForeignKeyName()} = $newData->id;
        $newModel->save();
    }

    protected function copyHasMany($models, Data $newData, string $relationName): void
    {
        /** @var HasMany $relation */
        $relation = $newData->{$relationName}();

        foreach ($models ?? [] as $model) {
            if (!$model || !$model->exists) {
                continue;
            }

            $attrs = $this->filteredAttributes($model);
            $newModel = $relation->getRelated()->newInstance();
            $newModel->forceFill($attrs);
            $newModel->{$relation->getForeignKeyName()} = $newData->id;
            $newModel->save();
        }
    }

    protected function filteredAttributes(Model $model): array
    {
        return Arr::except($model->getAttributes(), [
            'id',
            'data_id',
            'created_at',
            'updated_at',
        ]);
    }
}

