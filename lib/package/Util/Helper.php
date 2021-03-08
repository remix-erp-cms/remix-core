<?php
/**
 * Created by PhpStorm.
 * User: truong.nq
 * Date: 5/12/2020
 * Time: 2:45 PM
 */
namespace Package\Util;

use App\Model\Gallery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Image;

class Helper
{
    public static function uploadAndSaveFile($files, $data = null, $author = "1", $folder = null)
    {
        try {
            $gallery_data = [];

            $title = isset($data->title) ? $data->title : null;

            if(isset($files) && count($files) > 0) {
                foreach ($files as $file) {
                    $filename = null;
                    if (isset($title)) {
                        $filename = str_replace(' ', '-', $title);
                    }


                    $files_result = Helper::UploadResource($file, $filename, $folder);

                    if ($files_result === false) {
                       $response = [
                           'status' => false,
                           'msg' =>  'Không thể tải lên tệp tin!',
                       ];

                        return $response;
                    }

                    if (!isset($title)) {
                        $filename = isset($files_result['file_name']) ? now()->timestamp . '_' . $files_result['file_name'] : now()->timestamp;
                    }

                    $gallery_data = [
                        'file_path' => isset($files_result['path']) ? $files_result['path'] : "",
                        'file_thumb' => isset($files_result['thumb']) ? $files_result['thumb'] : "",
                        'file_title'=> $filename,
                        'file_alt'=> isset($data->file_alt) ? $data->file_alt : "",
                        'file_description'=> isset($data->file_description) ? $data->file_description : "",
                        'file_author'=> $author,
                        'file_size'=> isset($files_result['size']) ? $files_result['size'] : 0,
                        'file_type'=> isset($files_result['type']) ? $files_result['type'] : "other",
                        'created_at'=> $author,
                        'business_id'=> 1,
                        'location_id'=> 1,
                    ];

                    $save_result = Gallery::updateOrCreate($gallery_data);

                    if (!$save_result) {

                        $image_path = public_path($gallery_data['file_path']);
                        if(File::exists($image_path)) {
                            File::delete($image_path);
                        }

                        $image_thumb = public_path($gallery_data['file_thumb']);
                        if(File::exists($image_thumb)) {
                            File::delete($image_thumb);
                        }

                        $response = [
                            'status' => false,
                            'msg' =>  'không thể tải lên tệp tin',
                        ];

                        return $response;
                    }

                    if(isset($save_result->id) && $save_result->id) {
                        $gallery_data['id'] = $save_result->id;
                    }
                }
            } else {
                $response = [
                    'status' => false,
                    'msg' =>  'Không có tệp tin nào được chọn để tải lên',
                ];

                return $response;
            }

            $gallery_data['status'] = true;

            return $gallery_data;
        } catch (\Exception $e) {
            $message = 'Lỗi hệ thống';

            if (env('APP_DEBUG') == true) {
                $message = $e->getMessage();
            }

            $response = [
                'status' => false,
                'msg' =>  $message,
            ];

            return $response;
        }
    }

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
                'path' => $path_image . '/' . $fileName,
                'thumb' => $path_image_thumb . $fileName,
                'file_name' => $title,
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
