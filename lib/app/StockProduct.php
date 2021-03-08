<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockProduct extends Model
{
    protected $table = 'stock_products';

    protected $guarded = ['id'];

    public function stock()
    {
        return $this->belongsTo(\App\Stock::class);
    }

    public function product()
    {
        return $this->belongsTo(\App\Product::class);
    }

    public function stocks()
    {
        return $this->hasMany(\App\Stock::class);
    }

    public function products()
    {
        return $this->hasMany(\App\Product::class);
    }
}
