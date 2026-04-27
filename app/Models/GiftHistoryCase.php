<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
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
    

    public function familyMembers(): HasMany
    {
        return $this->hasMany(GiftHistoryFamilyMember::class, 'gift_history_case_id');
    }

    public function relationshipOptions(): HasMany
    {
        return $this->hasMany(GiftHistoryRelationshipOption::class, 'gift_history_case_id');
    }
    

    public function entries(): HasMany
    {
        return $this->hasMany(GiftHistoryEntry::class, 'gift_history_case_id');
    }    


}