<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $table = 'guests'; // 実際のテーブル名に合わせてください
    protected $guarded = [];     // 一括代入を許可

    // Dataと1:1関係（双方向リレーション用、必須ではない）
    public function data()
    {
        return $this->belongsTo(Data::class, 'guest_id', 'id');
    }
}
