<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductSerial extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
    protected $table = "product_serial";

    public function product()
    {
        return $this->belongsTo(\App\Product::class);
    }

    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class, "transaction_id");
    }
}
