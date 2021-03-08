<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DeliveryCompany extends Model
{
    protected $table = 'delivery_company';
    protected $guarded = ['id'];
    protected $appends = ['image_url'];

    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function created_by()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function getImageUrlAttribute()
    {
        if (!empty($this->image)) {
            $image_url = asset($this->image);
        } else {
            $image_url = asset('/img/default.png');
        }
        return $image_url;
    }
}
