<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Created by laravel_cms.
 * User: truong.nq
 * Date: 5/9/2020
 * Time: 3:20 PM
 */
class Gallery extends Model
{
    public $timestamps = false;

    protected $table = 'galleries';

    protected $fillable = array(
        'location_id',
        'business_id',
        'file_path',
        'file_thumb',
        'file_title',
        'file_alt',
        'file_description',
        'file_author',
        'file_size',
        'file_type',
        'is_delete',
        'is_active',
        'created_by',
        'created_at'
    );
}
