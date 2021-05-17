<?php

namespace App\Http\Controllers\File;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\Http\Controllers\Controller;
use App\Media;
use App\Notifications\CustomerNotification;
use App\PurchaseLine;
use App\System;
use App\Transaction;
use App\User;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\NotificationUtil;
use App\Utils\TransactionUtil;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;
use App\Helpers\Util;

class AjaxController extends Controller
{
    protected $commonUtil;
    protected $contactUtil;
    protected $transactionUtil;
    protected $moduleUtil;
    protected $notificationUtil;

    /**
     * Constructor
     *
     * @param Util $commonUtil
     * @return void
     */
    public function __construct(
        Util $commonUtil,
        ModuleUtil $moduleUtil,
        TransactionUtil $transactionUtil,
        NotificationUtil $notificationUtil,
        ContactUtil $contactUtil
    )
    {
        $this->commonUtil = $commonUtil;
        $this->contactUtil = $contactUtil;
        $this->moduleUtil = $moduleUtil;
        $this->transactionUtil = $transactionUtil;
        $this->notificationUtil = $notificationUtil;
    }

    public function upload(Request $request)
    {
        DB::beginTransaction();
        try {
            // $business_id = Auth::guard('api')->user()->business_id;
            // $user_id = Auth::guard('api')->user()->id;
//            $business_id = Auth::guard('api')->user()->business_id;
//            $user_id = Auth::guard('api')->user()->id;

            $input = $request->only([
                'app_id',
                'upload_time',
                'secure_code',
                'file',
                'title',
                'description'
            ]);


            $request->validate([
                'file' => 'required',
                'app_id' => 'required',
                'upload_time' => 'required',
                'secure_code' => 'required',
            ]);

             if ($request->app_id != 2) {
                 DB::rollBack();
                 $message = "Tải file lên không thành công! Thiết bị bị từ chối";

                 return $this->respondWithError($message, [], 500);
             }

            $file = $request->file('file');


            if (!$file) {
                DB::rollBack();
                $message = "Tải file lên không thành công! Thiết bị bị từ chối";

                return $this->respondWithError($message, [], 500);
            }

            $uploadFile_1 = Util::UploadResource($file);

            $res = [
                "message" => "Tải file lên hệ thống thành công"
            ];

            if (isset($uploadFile_1['path']) && $uploadFile_1['path']) {
                $res['image'] = url($uploadFile_1['path']);
            }

            if (isset($uploadFile_1['thumb']) && $uploadFile_1['thumb']) {
                $res['thumb'] = url($uploadFile_1['thumb']);
            }

            $dataUpdate = [
                'path' => $uploadFile_1['path'],
                'thumb' => $uploadFile_1['thumb'],
                'file_name' =>  $uploadFile_1['file_name'],
                'title' => $request->title,
                'description' => $request->description,
                'type' => $request->type,
            ];

            Media::create($dataUpdate);

            DB::commit();
            return $this->respondSuccess($res);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function ckeditorUpload(Request $request)
    {
        DB::beginTransaction();
        try {
            // $business_id = Auth::guard('api')->user()->business_id;
            // $user_id = Auth::guard('api')->user()->id;
            $user_id = 1;

            $input = $request->only([
                'ckCsrfToken',
                'upload',
            ]);


            $request->validate([
                'upload' => 'required',
                'ckCsrfToken' => 'required',
            ]);

            // if ($input["app_id"] !== 2) {
            //     DB::rollBack();
            //     $message = "Tải file lên không thành công! Thiết bị bị từ chối";

            //     return $this->respondWithError($message, [], 500);
            // }

            $file = $request->file('upload');


            if (!$file) {
                DB::rollBack();
                $message = "Tải file lên không thành công! Thiết bị bị từ chối";

                return $this->respondWithError($message, [], 500);
            }

            $data_file_1 = (object)[
                'location_id' => 1,
                'business_id' => 1,
                'created_by' => 1
            ];

            $uploadFile_1 = Util::UploadResource($file);

            $res  = [];

            if (isset($uploadFile_1['path']) && $uploadFile_1['path']) {
                $res['default'] = url($uploadFile_1['path']);
            }

            if (isset($uploadFile_1['thumb']) && $uploadFile_1['thumb']) {
                $res['900'] = url($uploadFile_1['thumb']);
            }

            DB::commit();
            return $this->respondSuccess($res);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }
}
