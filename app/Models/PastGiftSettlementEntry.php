<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PastGiftSettlementEntry extends Model
{
    protected $table = 'past_gift_settlement_entries';

    // 親ファースト：data_id は「フォームから受け取らない」ので guarded
    protected $guarded = ['id', 'data_id'];

    protected $fillable = [
        'data_id','recipient_no','row_no',
        'gift_year','gift_month','gift_day',
        'amount_thousand','tax_thousand',
    ];


    protected $casts = [
        'data_id'         => 'integer',
        'recipient_no'    => 'integer',
        'row_no'          => 'integer',
        'gift_year'       => 'integer',
        'gift_month'      => 'integer',
        'gift_day'        => 'integer',
        'amount_thousand' => 'integer',
        'tax_thousand'    => 'integer',
    ];
}


