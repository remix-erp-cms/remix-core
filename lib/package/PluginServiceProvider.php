<?php

namespace Package;
use App\Model\Option;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\File;

class PluginServiceProvider extends ServiceProvider
{
    public function __construct($app)
    {
        parent::__construct($app);
    }
    /**
     * Bootstrap services.
     * @return void
     */
    public function boot()
    {
        try {
            DB::connection()->getPdo();

            if(DB::connection()->getDatabaseName()) {
                // init service for themes
                $this->registerDynamicTheme();

                // init service for plugins
                $this->registerDynamicPlugin();
            }
        } catch (\Exception $exception) {
            //
        }
    }
    /**
     * Register services.
     * @return void
     */
    public function register()
    {
        try {
            DB::connection()->getPdo();

            if(DB::connection()->getDatabaseName()) {
                $this->deacvativeTheme();
                // Register service for themes
                $this->registerDynamicTheme();

                // Register service for plugin
                $this->registerDynamicPlugin();

                //
                $path_util = __DIR__ . "/Util/Utils.php";

                if (File::exists($path_util)) {
                    require_once $path_util;
                }
            }
        } catch (\Exception $exception) {
            //
        }

    }

    private function registerDynamicPlugin() {
        $dir_path = __DIR__ . '/Plugin';

        if (File::exists($dir_path)) {
            $list_folder = array_map('basename', File::directories($dir_path));

            foreach ($list_folder as $folder) {
                $filename = "Package\\Plugin\\{$folder}\\PluginProvider";

                $this->app->register($filename);
            }
        }
    }

    private function deacvativeTheme() {
        try {
            $dir_path = __DIR__ . '/Theme';

            if (File::exists($dir_path)) {
                $list_folder = array_map('basename', File::directories($dir_path));

                foreach ($list_folder as $folder) {
                    $filename = "Package\\Theme\\{$folder}\\ThemeProvider";

                    $instance = new $filename($this->app);

                    $instance->deactivate();
                }
            }
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            Log::channel('package')->error($message);
        }
    }

    private function registerDynamicTheme() {
        try {
            $dir_path = __DIR__ . '/setting.php';

            if (File::exists($dir_path)) {
                $json = require $dir_path;

                $name_theme = null;

                if (isset($json['current_theme']) && $json['current_theme']) {
                    $name_theme = $json['current_theme'];
                }

                $path_theme = "Package\\Theme\\{$name_theme}\\ThemeProvider";

                $this->app->register($path_theme);
            }
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            Log::channel('package')->error($message);
        }
    }
}
