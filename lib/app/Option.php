<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Option extends Model
{
    use SoftDeletes;
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    
    
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
    protected $appends = ['image_url'];

    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function created_user()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }


    public function getImageUrlAttribute()
    {
        if (!empty($this->images)) {
            $image_url = asset($this->images);
        } else {
            $image_url = asset('/img/default.png');
        }
        return $image_url;
    }
}
