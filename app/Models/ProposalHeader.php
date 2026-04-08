<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalHeader extends Model
{
    protected $table = 'proposal_headers';
    protected $guarded = ['id'];
    public function data(){ return $this->belongsTo(Data::class,'data_id'); }
}


