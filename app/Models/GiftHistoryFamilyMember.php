<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftHistoryFamilyMember extends Model
{
    protected $connection = 'gift_history';

    protected $table = 'gift_history_family_members';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'gift_history_case_id' => 'integer',
        'source_family_member_id' => 'integer',
        'source_data_id' => 'integer',
        'row_no' => 'integer',
        'relationship_code' => 'integer',
        'heir_category' => 'integer',
        'civil_share_bunbo' => 'integer',
        'civil_share_bunsi' => 'integer',
        'share_numerator' => 'integer',
        'share_denominator' => 'integer',
        'surcharge_twenty_percent' => 'boolean',
        'tokurei_zouyo' => 'boolean',
        'birth_year' => 'integer',
        'birth_month' => 'integer',
        'birth_day' => 'integer',
        'age' => 'integer',
        'property_thousand' => 'integer',
        'cash_thousand' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}