<?php

namespace App;

use DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

use Illuminate\Notifications\Notifiable;

class Contact extends Authenticatable
{
    use Notifiable;

    use SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    

    /**
    * Get the business that owns the user.
    */
    public function business()
    {
        return $this->belongsTo(\App\Business::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class, "user_id");
    }


    public function groups()
    {
        return $this->belongsTo(\App\CustomerGroup::class);
    }

    public function accountant()
    {
        return $this->hasMany(\App\Accountant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('contacts.contact_status', 'active');
    }

    public function scopeOnlySuppliers($query)
    {
        return $query->whereIn('contacts.type', ['supplier', 'both']);
    }

    public function scopeOnlyCustomers($query)
    {
        return $query->whereIn('contacts.type', ['customer', 'both']);
    }

    public function getContactAddressAttribute()
    {
        $address_array = [];
        if (!empty($this->address_line_1)) {
            $address_array[] = $this->address_line_1;
        }
        if (!empty($this->address_line_2)) {
            $address_array[] = $this->address_line_2;
        }
        if (!empty($this->city)) {
            $address_array[] = $this->city;
        }
        if (!empty($this->state)) {
            $address_array[] = $this->state;
        }
        if (!empty($this->country)) {
            $address_array[] = $this->country;
        }

        $address = '';
        if (!empty($address_array)) {
            $address = implode(', ', $address_array);
        }
        if (!empty($this->zip_code)) {
            $address .= ',<br>' . $this->zip_code;
        }

        return $address;
    }
}
