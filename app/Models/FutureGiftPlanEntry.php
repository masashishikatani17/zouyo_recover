<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FutureGiftPlanEntry extends Model
{
    protected $table = 'future_gift_plan_entries';
    protected $guarded = ['id', 'data_id', 'recipient_no', 'row_no'];

    public function data()
    {
        return $this->belongsTo(\App\Models\Data::class, 'data_id');
    }
}
