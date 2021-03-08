<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Accountant extends Model
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
    protected $table = 'accountants';


    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class, 'transaction_id');
    }

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    public function pay_bills()
    {
        return $this->hasMany(\App\PayBill::class, 'accountant_id');
    }

    public function stock_bills()
    {
        return $this->hasMany(\App\StockBill::class, 'accountant_id');
    }

    public function payment_lines()
    {
        return $this->hasMany(\App\TransactionPayment::class, 'accountant_id');
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

    public function employer_by()
    {
        return $this->belongsTo(\App\User::class, 'employer_by');
    }

    public function manager_by()
    {
        return $this->belongsTo(\App\User::class, 'manager_by');
    }
}
