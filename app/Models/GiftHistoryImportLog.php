<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftHistoryImportLog extends Model
{
    protected $connection = 'gift_history';

    protected $table = 'gift_history_import_logs';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'gift_history_case_id' => 'integer',
        'data_id' => 'integer',
        'source_count' => 'integer',
        'imported_count' => 'integer',
        'created_by' => 'integer',
        'payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}