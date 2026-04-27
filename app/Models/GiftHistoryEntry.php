<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GiftHistoryEntry extends Model
{
    use SoftDeletes;

    protected $connection = 'gift_history';

    protected $table = 'gift_history_entries';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'gift_history_case_id' => 'integer',
        'gift_date' => 'date',
        'gift_year' => 'integer',
        'donor_family_member_id' => 'integer',
        'recipient_family_member_id' => 'integer',
        'donor_relationship_code_from_recipient' => 'integer',
        'recipient_relationship_code_from_donor' => 'integer',
        'gift_amount_yen' => 'integer',
        'addback_3year_deadline_date' => 'date',
        'addback_final_deadline_date' => 'date',
        'settlement_election_confirmed' => 'boolean',
        'settlement_no_return_confirmed' => 'boolean',
        'settlement_notification_date' => 'date',
        'tax_auto_amount_yen' => 'integer',
        'tax_override_enabled' => 'boolean',
        'tax_override_amount_yen' => 'integer',
        'tax_final_amount_yen' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}