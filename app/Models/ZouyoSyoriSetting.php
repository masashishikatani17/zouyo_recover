<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZouyoSyoriSetting extends Model
{
    protected $table = 'zouyo_syori_settings';
    protected $guarded = ['id'];
    protected $casts = [
        'payload' => 'array',
    ];

    public function data()
    {
        return $this->belongsTo(Data::class, 'data_id');
    }
}
