<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $appends = ['display_name', 'display_url', 'display_thumb'];


    /**
     * Get all of the owning mediable models.
     */
    public function mediable()
    {
        return $this->morphTo();
    }

    /**
     * Get display name for the media
     */
    public function getDisplayNameAttribute()
    {
        $array = explode('_', $this->file_name, 3);
        return !empty($array[2]) ? $array[2] : $array[1];
    }

    /**
     * Get display link for the media
     */
    public function getDisplayUrlAttribute()
    {
        $path = asset('/uploads/media/' . rawurlencode($this->file_name));

        return $path;
    }

    /**
     * Get display path for the media
     */
    public function getDisplayPathAttribute()
    {
        if (!empty($this->path)) {
            $image_url = asset($this->path);
        } else {
            $image_url = asset('/img/default.png');
        }
        return $image_url;
    }

    public function getDisplayThumbAttribute()
    {
        if (!empty($this->thumb)) {
            $image_url = asset($this->thumb);
        } else {
            $image_url = asset('/img/default.png');
        }
        return $image_url;
    }

    /**
     * Deletes resource from database and storage
     *
     */
    public static function deleteMedia($business_id, $media_id)
    {
        $media = Media::where('business_id', $business_id)
                        ->findOrFail($media_id);

        $media_path = asset($media->path);

        if (file_exists($media_path)) {
            unlink($media_path);
        }

        $media->delete();
    }

    public function uploaded_by_user()
    {
        return $this->belongsTo(\App\User::class, 'uploaded_by');
    }
}
