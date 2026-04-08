<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

 class ProposalFamilyMember extends Model
 {
     use HasFactory;

    /**
     * 一括代入を許可するカラム
     */
    protected $fillable = [
        'data_id',
        'row_no',
        'name',
        'gender',
        'relationship_code',
        'adoption_note',
        'heir_category',
        'civil_share_bunsi',              // 民法上の法定相続割合（表示用）
        'civil_share_bunbo',              // 民法上の法定相続割合（表示用）
        'share_numerator',
        'share_denominator',
        'surcharge_twenty_percent',
        'tokurei_zouyo',
        'birth_year',
        'birth_month',
        'birth_day',
        'age',
        'property_thousand',
        'cash_thousand',
    ];

    /**
     * data_id は親から強制付与するためガードする
     */
    protected $guarded = ['data_id'];
 }
