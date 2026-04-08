<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PastGiftRecipient extends Model
{
    protected $table = 'past_gift_recipients';

    // 親ファースト：data_id は外から受けない
    protected $guarded = ['id', 'data_id'];

    /**
     * 実際に許容する入力カラムのみを列挙（例）
     * プロジェクトの実テーブルに合わせて調整してください。
     */
    protected $fillable = [
        'recipient_no',     // ※ Controller 側では key として attributes に入る
        'recipient_name',
        'relationship',
        'birthday',
        'amount',
        'notes',
    ];
 
     protected $casts = [
         'data_id'      => 'integer',
         'recipient_no' => 'integer',
     ];


 }
    
    
