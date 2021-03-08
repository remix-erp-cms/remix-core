<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WarrantyProduct extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'warranty_products';

    public function warranty()
    {
        return $this->belongsTo(\App\Warranty::class, 'warranty_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Product::class, 'product_id');
    }

    public function transaction_sells()
    {
        return $this->belongsTo(\App\TransactionSellLine::class, 'transaction_sell_id');
    }

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }


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
