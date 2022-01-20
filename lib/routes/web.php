<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

include_once('install_r.php');

Route::middleware(['setData'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });

    Auth::routes();

    Route::get('/business/register', 'BusinessController@getRegister')->name('business.getRegister');
    Route::post('/business/register', 'BusinessController@postRegister')->name('business.postRegister');
    Route::post('/business/register/check-username', 'BusinessController@postCheckUsername')->name('business.postCheckUsername');
    Route::post('/business/register/check-email', 'BusinessController@postCheckEmail')->name('business.postCheckEmail');

    Route::get('/invoice/{token}', 'SellPosController@showInvoice')
        ->name('show_invoice');
});


//common route
Route::middleware(['auth'])->group(function () {
    Route::get('/logout', 'Auth\LoginController@logout')->name('logout');
});
Route::get('/install/autoload', function() {
    try {
        exec('composer dump-autoload');
		echo "dumpautoload successfully!!!!";
        return Artisan::output(); //Return anything
    } catch (\Exception $exception) {
        echo $exception->getMessage();
		exit;
    }
});

Route::get('/install/clear-cache', function() {
    try {
        $exitCode = Artisan::call('config:clear');
        $exitCode = Artisan::call('config:cache
        ');
		echo "Clear successfully!!!!";
        return Artisan::output(); //Return anything
    } catch (\Exception $exception) {
        echo $exception->getMessage();
		exit;
    }
});
