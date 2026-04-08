<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ZouyoGeneralRate extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'company_id','kihu_year','version','seq','lower','upper','rate','deduction_amount','note',
        'basic_deduction_amount',        
    ];
    protected $casts = [
        'company_id'=>'integer','kihu_year'=>'integer','version'=>'integer','seq'=>'integer',
        'lower'=>'integer','upper'=>'integer','rate'=>'float','deduction_amount'=>'integer',
        'basic_deduction_amount' => 'integer',        
    ];
}
