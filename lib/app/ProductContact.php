<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductContact extends Model
{
    protected $table = 'product_contacts';

    protected $guarded = ['id'];

    public function contacts()
    {
        return $this->hasMany(\App\Contact::class);
    }

    public function products()
    {
        return $this->hasMany(\App\Product::class);
    }
}
