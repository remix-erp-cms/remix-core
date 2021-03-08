<?php
/**
 * Created by laravel_cms.
 * User: truong.nq
 * Date: 5/10/2020
 * Time: 12:19 AM
 */

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

if (!function_exists('getTableName')) {
    function getTableName($table) {
        $table_prefix = env('DB_PREFIX');

        $table_name = $table_prefix . $table;

        return $table_name;
    }
}

if (!function_exists('envUpdate')) {
    function envUpdate($envKey, $newValue, $oldvalue = null)
    {
        $path_env = App::environmentFilePath();
        if(file_exists($path_env)) {
            $oldValueForKey = env($envKey);
            if(isset($oldvalue)) {
                $oldValueForKey = $oldvalue;
            }

            file_put_contents($path_env, str_replace(
                $envKey . '=' .$oldValueForKey, $envKey . '=' . $newValue, file_get_contents($path_env)
            ));
        }
    }
}

if (!function_exists('admin_assets')) {
    function admin_assets($url = "")
    {
        return asset('assets/' . $url . '?v=' . env('APP_VERSION') );
    }
}

if (!function_exists('server_assets')) {
    function server_assets($url = "", $version = "0.0.0.beta")
    {
        return env('SERVER_URL') . '/assets/' . $url . '?v=' . $version;
    }
}




