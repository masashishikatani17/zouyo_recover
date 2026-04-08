<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InheritanceDistributionHeader extends Model
{
    protected $table = 'inheritance_distribution_headers';
    protected $guarded = ['id', 'data_id']; // 親ファースト：data_idはサーバ側で強制代入

    public function data()
    {
        return $this->belongsTo(\App\Models\Data::class, 'data_id');
    }
}
