<?php
/**
 * Created by laravel_cms.
 * User: truong.nq
 * Date: 5/8/2020
 * Time: 10:38 PM
 */
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

use App\Model\Plugin;
use App\Model\Menu;
use App\Model\Theme;

if (!function_exists('package_path')) {
    function package_path($path = '')
    {
        return '/package/' . $path;
    }
}

if (!function_exists('package_url')) {

    function package_url($url = '')
    {
        return asset('lib/package/' . $url);
    }
}

if (!function_exists('plugin_path')) {
    function plugin_path($path = '')
    {
        return 'package/Plugin/' . $path;
    }
}

if (!function_exists('plugin_url')) {
    function plugin_url($url = '')
    {
        return asset('lib/package/Plugin/' . $url);
    }
}

if (!function_exists('theme_path')) {
    function theme_path($path = '')
    {
        return 'package/Theme/' . $path;
    }
}

if (!function_exists('theme_url')) {
    function theme_url($url = '')
    {
        return asset('lib/package/Theme/' . $url);
    }
}

if (!function_exists('register_router')) {
    function register_router($arg_group, $router, $controller, $function)
    {

        Route::group($arg_group , function() use($router, $controller, $function) {
            if (gettype($function) == "object") {
                Route::get($router, $function);
            }

            if (gettype($function) == "string") {
                Route::get($router, $controller . '@' . $function);
            }
        });
    }
}

if (!function_exists('register_api')) {
    function register_api($url = '')
    {
        return asset('lib/package/Theme/' . $url);
    }
}

if(!function_exists('addParentMenu')) {
    function addParentMenu($menu, $code, $target = "plugin", $role = "") {
        DB::beginTransaction();
        try {
            $parentMenu = Menu::where('menu_scope', $code)
                ->where('menu_role', $role)
                ->first();

            if (isset($parentMenu->id) && $parentMenu->id) {
                DB::rollBack();
                return false;
            }

            $data = array(
                'parent_id' => 0,
                'menu_icon' => 'fas fa-external-link-alt',
                'menu_description' => null,
                'menu_rating' => null,
                'menu_role' => $role,
                'menu_scope' => $code,
                'menu_target' => $target,
                'menu_view' => null,
                'is_delete' => 0,
                'is_active' => 1,
            );

            if(isset($menu->title) && $menu->title) {
                $data['menu_title'] = $menu->title;
            }

            if(isset($menu->slug) && $menu->slug) {
                $prefix = env('ADMIN_PREFIX');

                $data['menu_url'] = $prefix . $menu->slug;
            }

            if(isset($menu->icon) && $menu->icon) {
                $data['menu_icon'] = $menu->icon;
            }

            if(isset($menu->order) && $menu->order) {
                $data['menu_order'] = $menu->order;
            }

            if(isset($menu->role) && $menu->role) {
                $data['menu_role'] = $menu->role;
            }

            $result = Menu::updateOrCreate($data);

            if (!$result) {
                DB::rollBack();
                return false;
            }

            DB::commit();

            return true;
        } catch (\Exception $exception) {
            DB::rollBack();

            return false;
        }
    }
}

if(!function_exists('addSubMenu')) {
    function addSubMenu($list_menu, $code, $target = "plugin", $role = "") {
        DB::beginTransaction();
        try {
            $parentMenu = Menu::where('menu_scope', $code)
                ->where('menu_role', $role)
                ->first();

            if (!$parentMenu) {
                DB::rollBack();
                return false;
            }

            if (isset($parentMenu->id) && !$parentMenu->id) {
                DB::rollBack();
                return false;
            }

            if (count($list_menu) > 0) {
                foreach ($list_menu as $menu) {
                    $data = array(
                        'parent_id' => $parentMenu->id,
                        'menu_icon' => 'fas fa-external-link-alt',
                        'menu_description' => null,
                        'menu_rating' => null,
                        'menu_role' => null,
                        'menu_scope' => $code,
                        'menu_target' => $target,
                        'menu_view' => null,
                        'is_delete' => 0,
                        'is_active' => 1,
                    );


                    if(isset($menu->title) && $menu->title) {
                        $data['menu_title'] = $menu->title;
                    }

                    if(isset($menu->title) && $menu->title) {
                        $prefix = isset($parentMenu->menu_url) ? $parentMenu->menu_url : "";

                        $url = $prefix . $menu->slug;
                        $data['menu_url'] = $url;

                        $childMenu = Menu::where('menu_url', $url)
                            ->first();

                        if ($childMenu && $childMenu->id) {
                            DB::rollBack();
                            return false;
                        }
                    }

                    if(isset($menu->icon) && $menu->icon) {
                        $data['menu_icon'] = $menu->icon;
                    }

                    if(isset($menu->order) && $menu->order) {
                        $data['menu_order'] = $menu->order;
                    }

                    $result = Menu::updateOrCreate($data);

                    if (!$result) {
                        DB::rollBack();
                        return false;
                    }
                }
            }


            DB::commit();

            return true;
        } catch (\Exception $exception) {
            DB::rollBack();

            return false;
        }
    }
}


if(!function_exists('removeParentMenu')) {
    function removeParentMenu($code, $role = "") {
        DB::beginTransaction();
        try {
            $query = DB::table(getTableName('menus'))
                ->where('menu_scope',$code)
                ->where('menu_role',$role)
                ->delete();

            if($query == false) {
                DB::rollBack();

                return false;
            }

            DB::commit();

            return true;
        } catch (\Exception $exception) {
            DB::rollBack();

            return false;
        }
    }
}

if(!function_exists('installPlugin')) {
    function installPlugin($code, $plugin_name, $plugin_author, $plugin_image, $attribute) {
        DB::beginTransaction();

        try {
            $plugin = Plugin::where('plugin_code', $code)
                ->first();

            if (isset($plugin)) {
                DB::rollBack();
                return false;
            }

            $data = array(
                'plugin_code' => $code,
                'plugin_name' => $plugin_name,
                'plugin_author' => $plugin_author,
                'plugin_image' => $plugin_image,
            );

            if(count($attribute) > 0) {
                if(isset($attribute['plugin_uri'])) {
                    $data['plugin_uri'] = $attribute['plugin_uri'];
                }

                if(isset($attribute['plugin_description'])) {
                    $data['plugin_description'] = $attribute['plugin_description'];
                }

                if(isset($attribute['plugin_tags'])) {
                    $data['plugin_tags'] = $attribute['plugin_tags'];
                }

                if(isset($attribute['plugin_version'])) {
                    $data['plugin_version'] = $attribute['plugin_version'];
                }

                if(isset($attribute['plugin_require'])) {
                    $data['plugin_require'] = $attribute['plugin_require'];
                }

                if(isset($attribute['autoload'])) {
                    $data['autoload'] = $attribute['autoload'];
                }

                if(isset($attribute['is_delete'])) {
                    $data['is_delete'] = $attribute['is_delete'];
                }

                if(isset($attribute['is_active'])) {
                    $data['is_active'] = $attribute['is_active'];
                }
            }

            $result = Plugin::updateOrCreate($data);

            if (!$result) {
                DB::rollBack();
                return false;
            }

            DB::commit();

            return true;
        } catch (\exception $exception) {
            DB::rollBack();
            return false;
        }
    }
}

if(!function_exists('activatePlugin')) {
    function activatePlugin($code) {
        DB::beginTransaction();
        try {
            $query = DB::table(getTableName('plugins'))
                ->where('plugin_code',$code)
                ->update([
                    'is_active' => 1
                ]);

            if($query == false) {
                DB::rollBack();

                return false;
            }

            DB::commit();

            return true;
        } catch (\Exception $exception) {

            DB::rollBack();

            return false;
        }
    }
}

if(!function_exists('deactivatePlugin')) {
    function deactivatePlugin($code) {
        DB::beginTransaction();
        try {
            $query = DB::table(getTableName('plugins'))
                ->where('plugin_code',$code)
                ->update([
                    'is_active' => 0
                ]);

            if($query == false) {
                DB::rollBack();

                return false;
            }

            DB::commit();

            return true;
        } catch (\Exception $exception) {
            DB::rollBack();

            return false;
        }
    }
}

if(!function_exists('installTheme')) {
    function installTheme($code, $theme_name, $theme_author, $theme_image, $attribute) {
        DB::beginTransaction();

        try {
            $plugin = Plugin::where('plugin_code', $code)
                ->first();

            if (isset($plugin)) {
                DB::rollBack();
                return false;
            }

            $data = array(
                'theme_code' => $code,
                'theme_name' => $theme_name,
                'theme_author' => $theme_author,
                'theme_image' => $theme_image,
            );

            if(count($attribute) > 0) {
                if(isset($attribute['theme_uri'])) {
                    $data['theme_uri'] = $attribute['theme_uri'];
                }

                if(isset($attribute['theme_description'])) {
                    $data['theme_description'] = $attribute['theme_description'];
                }

                if(isset($attribute['theme_tags'])) {
                    $data['theme_tags'] = $attribute['theme_tags'];
                }

                if(isset($attribute['theme_version'])) {
                    $data['theme_version'] = $attribute['theme_version'];
                }

                if(isset($attribute['theme_require'])) {
                    $data['theme_require'] = $attribute['theme_require'];
                }

                if(isset($attribute['autoload'])) {
                    $data['autoload'] = $attribute['autoload'];
                }

                if(isset($attribute['is_delete'])) {
                    $data['is_delete'] = $attribute['is_delete'];
                }

                if(isset($attribute['is_active'])) {
                    $data['is_active'] = $attribute['is_active'];
                }
            }

            $result = Theme::updateOrCreate($data);

            if (!$result) {
                DB::rollBack();
                return false;
            }

            DB::commit();

            return true;
        } catch (\exception $exception) {
            DB::rollBack();
            return false;
        }
    }
}

if(!function_exists('activateTheme')) {
    function activateTheme($code) {
        DB::beginTransaction();
        try {
            $query = DB::table(getTableName('themes'))
                ->where('theme_code',$code)
                ->update([
                    'is_active' => 1
                ]);

            if($query == false) {
                DB::rollBack();

                return false;
            }

            DB::commit();

            return true;
        } catch (\Exception $exception) {

            DB::rollBack();

            return false;
        }
    }
}

if(!function_exists('deactivateTheme')) {
    function deactivateTheme($code) {
        DB::beginTransaction();
        try {
            $query = DB::table(getTableName('themes'))
                ->where('theme_code',$code)
                ->update([
                    'is_active' => 0
                ]);

            if($query == false) {
                DB::rollBack();

                return false;
            }

            DB::commit();

            return true;
        } catch (\Exception $exception) {
            DB::rollBack();

            return false;
        }
    }
}


if (!function_exists('get_meta')) {
    function get_meta($data = [])
    {
        $header = (object)$data;

        $headerHtml = "";
        $headerHtml .= "<meta http-equiv=\"REFRESH\" content=\"1800\" />\n";

        // ROBOT
        $headerHtml .= "<meta name=\"googlebot\" content=\"noodp, index, follow\" />". "\n";
        $headerHtml .= "<meta name=\"robots\" content=\"noodp, index, follow\" />". "\n";

        // IOS
        $headerHtml .= "<meta name=\"format-detection\" content=\"telephone=no\" />\n";
        $headerHtml .= "<meta name=\"format-detection\" content=\"address=no\" />\n";
        $headerHtml .= "<meta name=\"apple-mobile-web-app-capable\" content=\"yes\">\n";
        $headerHtml .= "<meta name=\"apple-mobile-web-app-status-bar-style\" content=\"black\"> \n";

        // FACEBOOK APP
        $headerHtml .= "<meta property=\"fb:app_id\" content=\"557013228523395\" />\n";

        if(isset($header->site_name) &&  $header->site_name) {
            $headerHtml .= '<meta property="og:site_name" content="'.$header->site_name.'">' . "\n";
        }

        if(isset($header->site_url) &&  $header->site_url) {
            $headerHtml .= '<meta property="og:url" content="'.$header->site_url.'">'. "\n";
            $headerHtml .= '<meta property="twitter:url" content="'.$header->site_url.'">'. "\n";
        }

        if(isset($header->site_title) &&  $header->site_title) {
            $headerHtml .= '<meta name="title" content="'.$header->site_title.'">'. "\n";
            $headerHtml .= '<meta property="og:title" content="'.$header->site_title.'">'. "\n";
            $headerHtml .= '<meta property="twitter:title" content="'.$header->site_title.'">'. "\n";
            $headerHtml .= '<meta name="twitter:card" content="summary_large_image">'. "\n";
        }

        if(isset($header->site_type) &&  $header->site_type) {
            $headerHtml .= '<meta property="og:type" content="'.$header->site_type.'">'. "\n";
        }

        if(isset($header->site_des) &&  $header->site_des) {
            $headerHtml .= '<meta name="description" content="'.$header->site_des.'">'. "\n";
            $headerHtml .= '<meta property="og:description" content="'.$header->site_des.'">'. "\n";
            $headerHtml .= '<meta property="twitter:description" content="'.$header->site_des.'">'. "\n";
        }


        if(isset($header->site_image) &&  $header->site_image) {
            $headerHtml .= '<meta property="og:image" content="'.$header->site_image.'" />'. "\n";
            $headerHtml .= '<meta property="twitter:image" content="'.$header->site_image.'" />'. "\n";
            $headerHtml .= '<meta property="og:image:type" content="image/jpeg, image/jpg, image/png" />'. "\n";
        }

        if(isset($header->author) &&  $header->author) {
            $headerHtml .= '<meta name="author" content="'.$header->author.'">'. "\n";
        }

        if(isset($header->copyright) &&  $header->copyright) {
            $headerHtml .= '<meta name="copyright" content="'.$header->copyright.'">'. "\n";
        }

        if(isset($header->city) &&  $header->city) {
            $headerHtml .= '<meta name="city" content="'.$header->city.'">'. "\n";
        }

        if(isset($header->url) &&  $header->url) {
            $headerHtml .= '<link rel="canonical" href="'.$header->url.'" />'. "\n";
        }

        if(isset($header->tag) &&  $header->tag) {
            $headerHtml .= '<link property="article:tag" content="'.$header->tag.'" />'. "\n";
        }

        if(isset($header->keyword) &&  $header->keyword) {
            $headerHtml .= '<meta name="keywords" content="'.$header->keyword.'">' . "\n";
        }

        if(isset($header->googleVerify) &&  $header->googleVerify) {
            $headerHtml .= '<meta name="google-site-verification" content="'.$header->googleVerify.'">' . "\n";
        }

        return $headerHtml;
    }
}

if (!function_exists('get_header')) {
    function get_header($data = [])
    {
        $header = (object)$data;
        return $header;
    }
}

if (!function_exists('get_setting')) {
    function get_setting()
    {
        try {
            $list_options = App\Model\Option::where("is_delete", 0)
                ->where("is_active", 1)
                ->where("type", config('setting.type_config'))
                ->get();

            $setting = [
                config('setting.logo') => null,
                config('setting.icon') => null,
                config('setting.title_website') => null,
                config('setting.des_website') => null,
                config('setting.color_header') => null,
                config('setting.color_text') => null,
                config('setting.color_background') => null,
                'image_slide' => [],
                config('setting.image_intro') => [],
                config('setting.title_intro') => null,
                config('setting.css_content') => null,
                config('setting.script_header') => null,
                config('setting.script_content') => null,
            ];

            $keyIntroImage = config('setting.image_intro');
            $keyBackgroundSlider = config('setting.background_slide');
            $keyTopicSlider = config('setting.topic_slide');
            $keyTitleSlider = config('setting.title_slide');
            $keyDesSlider = config('setting.des_slide');

            $keySlide = "slide_";

            $item_slide = [];

            foreach ($list_options as $item) {
                if(strpos($item->name, $keyIntroImage) !== false) {
                    array_push($setting[$keyIntroImage], $item );

                    continue;
                }

                if(strpos($item->name, $keyBackgroundSlider) !== false) {
                    $numImage = (integer)str_replace($keyBackgroundSlider . '_' , '', $item->name);

                    $item_slide[$keySlide . $numImage]['key'] = $numImage;
                    $item_slide[$keySlide . $numImage]['background'] = $item->thumb;

                    $setting['image_slide'] = $item_slide;

                    continue;
                }

                if(strpos($item->name, $keyTopicSlider) !== false) {
                    $numImage = (integer)str_replace($keyTopicSlider . '_' , '', $item->name);

                    $item_slide[$keySlide . $numImage]['topic'] = $item->thumb;
                    $setting['image_slide'] = $item_slide;

                    continue;
                }

                if(strpos($item->name, $keyTitleSlider) !== false) {
                    $numImage = (integer)str_replace($keyTitleSlider . '_' , '', $item->name);

                    $item_slide[$keySlide . $numImage]['title'] = $item->value;
                    $setting['image_slide'] = $item_slide;

                    continue;
                }

                if(strpos($item->name, $keyDesSlider) !== false) {
                    $numImage = (integer)str_replace($keyDesSlider . '_' , '', $item->name);

                    $item_slide[$keySlide . $numImage]['description'] = $item->value;
                    $setting['image_slide'] = $item_slide;

                    continue;
                }

                $setting[$item->name] = $item;
            }

            return (object)$setting;
        } catch (\Exception $exception) {
            return null;
        }
    }
}
