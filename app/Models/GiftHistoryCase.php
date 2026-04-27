<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftHistoryCase extends Model
{
    protected $connection = 'gift_history';

    protected $table = 'gift_history_cases';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'data_id' => 'integer',
        'proposal_header_id' => 'integer',
        'company_id' => 'integer',
        'group_id' => 'integer',
        'entries_count' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'source_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}