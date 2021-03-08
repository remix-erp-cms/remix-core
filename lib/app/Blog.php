<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    protected $table = 'blogs';
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

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function getImageUrlAttribute()
    {
        if (!empty($this->thumb)) {
            $image_url = asset($this->thumb);
        } else {
            $image_url = asset('/img/default.png');
        }

        return $image_url;
    }
}
