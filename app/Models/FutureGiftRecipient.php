<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FutureGiftRecipient extends Model
{
    protected $table = 'future_gift_recipients';
    protected $guarded = ['id', 'data_id']; // recipient_no は実質キー相当

    public function data()
    {
        return $this->belongsTo(\App\Models\Data::class, 'data_id');
    }
}
