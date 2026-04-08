<?php

namespace App\Services\Zouyo\Cases;

final class NoFinanceManualCase
{
    public static function config(): array
    {
        
        //金融資産なし　　手入力

        return [
            'id' => 'no_finance_manual',
            'has_finance' => false,
            'allocation_mode' => 'manual',         // 手入力
            'gift_funding_source' => 'noncash1',   // No=1 のその他資産が原資
            'decedent_cash_grows' => false,
            'recipients_cash_grows' => true,
            'gifted_cash_grows_after_received' => true,
        ];
    }
}
