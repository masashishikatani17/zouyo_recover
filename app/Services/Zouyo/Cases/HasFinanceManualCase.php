<?php

namespace App\Services\Zouyo\Cases;

final class HasFinanceManualCase
{
    public static function config(): array
    {
        
        //金融資産あり　　手入力
        
        return [
            'id' => 'has_finance_manual',
            'has_finance' => true,
            'allocation_mode' => 'manual',      // 手入力
            'gift_funding_source' => 'cash1',   // No=1 の金融資産が原資
            'decedent_cash_grows' => false,
            'recipients_cash_grows' => true,
            'gifted_cash_grows_after_received' => true,
        ];
    }
}
