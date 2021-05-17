<?php
/**
 * Created by PhpStorm.
 * User: truong.nq
 * Date: 5/12/2020
 * Time: 2:45 PM
 */
namespace App\Helpers;

use App\Gallery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Image;

class Util
{
    public static function UploadResource($resource, $title = null, $folder = "")
    {
        try {
            $fileName = $title;

            $type = $resource->getClientMimeType();
            $size = $resource->getSize();
            $name = $resource->getClientOriginalName();

            $path_image = 'upload';

            if(isset($folder) && $folder) {
                $path_image = 'upload/' . $folder;
            }

            $path_image_thumb = 'upload/thumb/';

            $pathSave = public_path($path_image);
            $pathThumb = public_path($path_image_thumb);

            if (!isset($title)) {
                $fileName = now()->timestamp . '_' . str_replace(' ', '-', $name);
                $fileName = str_replace('(', '', $fileName);
                $fileName = str_replace(')', '', $fileName);
                $fileName = Str::lower($fileName);
                $title = $name;
            } else {
                $fileName = now()->timestamp . '_' . $fileName . '.' . $resource->getClientOriginalExtension();
            }

            $resource->move($pathSave, $fileName);

            if (isset($type) && strpos($type, 'image') > -1) {
                $image_resize = Image::make($pathSave . '/' . $fileName);
                $image_resize->resize(900, null, function($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                $image_resize->save($pathThumb . $fileName );
            }

            if (isset($type) && strpos($type, 'image') > -1) {
                $image_resize = Image::make($pathSave . '/' . $fileName);
                $image_resize->resize(900, null, function($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                $image_resize->save($pathThumb . $fileName );
            }

            $result = [
                'path' => 'assets/' . $path_image . '/' . $fileName,
                'thumb' => 'assets/' . $path_image_thumb . $fileName,
                'file_name' => $fileName,
                'size' => $size,
                'type' => $type
            ];

            return $result;
        } catch (\Throwable  $e) {
            throw new \Exception($e);
        }
    }

    public static function formatBytes($size, $precision = 2)
    {
        if ($size > 0) {
            $size = (int) $size;
            $base = log($size) / log(1024);
            $suffixes = array(' bytes', ' KB', ' MB', ' GB', ' TB');

            return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
        } else {
            return $size;
        }
    }

    public static function convertBytes($size, $precision = 2)
    {
        if ($size > 0) {
            $size = (int) $size;
            return round($size/1048576, $precision);
        } else {
            return 0;
        }
    }

    public static function WriteLog($file, $content)
    {
        $f = fopen($file,"a+");
        file_put_contents($file,$content,FILE_APPEND);
        fclose($f);
    }
}
