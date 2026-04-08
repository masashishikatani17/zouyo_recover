<?php

namespace App\Services\Zouyo\Cases;

final class HasFinanceLegalCase
{
    public static function config(): array
    {
        
        //金融資産あり　　法定相続割合
        
        return [
            'id' => 'has_finance_legal',
            'has_finance' => true,
            'allocation_mode' => 'legal',       // 民法上の法定相続割合
            'gift_funding_source' => 'cash1',   // No=1 の金融資産が原資
            'decedent_cash_grows' => false,     // ★重要：No=1 は運用しない（t=0..20 一定）
            'recipients_cash_grows' => true,    // No=2..10 は運用（persons側）
            'gifted_cash_grows_after_received' => true,
        ];
    }
}
