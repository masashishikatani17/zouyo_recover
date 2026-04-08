<?php

namespace App\Services\Zouyo;

use App\Models\Data;
use App\Models\Guest;
use Illuminate\Support\Facades\DB;

class DeleteZouyoDataService
{
    public function deleteData(Data $data): void
    {
        DB::transaction(function () use ($data) {
            $data->proposalFamilyMembers()->delete();
            $data->proposalHeader()->delete();

            $data->pastGiftCalendarEntries()->delete();
            $data->pastGiftSettlementEntries()->delete();
            $data->pastGiftRecipients()->delete();
            $data->pastGiftInput()->delete();

            $data->futureGiftPlanEntries()->delete();
            $data->futureGiftRecipients()->delete();
            $data->futureGiftHeader()->delete();

            $data->inheritanceDistributionMembers()->delete();
            $data->inheritanceDistributionHeader()->delete();

            $data->delete();
        });
    }

    public function deleteGuestAll(Guest $guest): void
    {
        DB::transaction(function () use ($guest) {
            $datas = Data::query()
                ->where('company_id', $guest->company_id)
                ->where('group_id', $guest->group_id)
                ->get();

            foreach ($datas as $data) {
            $this->deleteData($data);
            }
            Guest::query()
                ->where('company_id', $guest->company_id)
                ->where('group_id', $guest->group_id)
                ->delete();
        });
    }
}

