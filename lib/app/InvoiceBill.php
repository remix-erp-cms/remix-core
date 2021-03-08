<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvoiceBill extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $table = "invoice_bills";

    public function payment_accountant()
    {
        return $this->belongsTo(\App\Accountant::class, 'accountant_id');
    }

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    public function payment_account()
    {
        return $this->belongsTo(\App\Account::class, 'account_id');
    }

    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class, 'transaction_id');
    }

    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }

    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function created_user()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

}
