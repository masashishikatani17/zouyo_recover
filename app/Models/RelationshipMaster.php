<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelationshipMaster extends Model
{
    protected $fillable = [
        'company_id',
        'relation_no',
        'name',
        'is_editable',
    ];

    protected $casts = [
        'company_id'  => 'integer',
        'relation_no' => 'integer',
        'is_editable' => 'boolean',
    ];
}