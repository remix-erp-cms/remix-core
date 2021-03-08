<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ActivityHistory extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'activity_log';
    protected $guarded = ['id'];

    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }

    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function created_by()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }
}
