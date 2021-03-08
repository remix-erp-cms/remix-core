<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
    protected $appends = ['image_url'];

    public function product()
    {
        return $this->belongsTo(\App\Product::class);
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
