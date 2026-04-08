<?php

namespace App\Services\Zouyo\Cases;

final class NoFinanceLegalCase
{
    
    //金融資産なし　　法定相続割合
    
    public static function config(): array
    {
        return [
            'id' => 'no_finance_legal',
            'has_finance' => false,
            'allocation_mode' => 'legal',          // 民法上の法定相続割合
            'gift_funding_source' => 'noncash1',   // No=1 のその他資産が原資
            'decedent_cash_grows' => false,        // No=1 金融資産は運用しない
            'recipients_cash_grows' => true,       // No=2..10 は運用する（persons側で処理）
            'gifted_cash_grows_after_received' => true,
        ];
    }
}
