<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InheritanceDistributionMember extends Model
{
    protected $table = 'inheritance_distribution_members';
    protected $guarded = ['id', 'data_id', 'recipient_no']; // 複合ユニークのキー相当は保護

    public function data()
    {
        return $this->belongsTo(\App\Models\Data::class, 'data_id');
    }
}
