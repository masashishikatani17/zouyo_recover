<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZouyoInput extends Model
{
    protected $table = 'zouyo_inputs';
    protected $guarded = ['id'];
    protected $casts = [
        'payload' => 'array',
    ];

    public function data()
    {
        return $this->belongsTo(Data::class, 'data_id');
    }
}
