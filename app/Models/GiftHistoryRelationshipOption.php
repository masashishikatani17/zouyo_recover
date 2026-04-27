<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftHistoryRelationshipOption extends Model
{
    protected $connection = 'gift_history';

    protected $table = 'gift_history_relationship_options';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'gift_history_case_id' => 'integer',
        'source_relationship_master_id' => 'integer',
        'source_relation_no' => 'integer',
    'relation_no' => 'integer',
    'is_editable' => 'boolean',
        'sort_order' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}