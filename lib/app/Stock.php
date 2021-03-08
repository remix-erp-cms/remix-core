<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class Stock extends Model
{
    protected $table = 'stocks';

    protected $guarded = ['id'];

    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }
}
