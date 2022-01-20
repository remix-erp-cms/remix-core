<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['cors', 'json.response']], function () {
    Route::get('info', function () {
        phpinfo();
    });
    Route::get('/database/test', function () {
        $mysqli = new mysqli("localhost:3306", "erp_admin", "0123Admin@", "pna_erp");

        // Check connection
        if ($mysqli->connect_errno) {
            echo "Failed to connect to MySQL: " . env('DB_DATABASE', 'default') . $mysqli->connect_error;
            exit();
        }

        echo "connect to database successfully!!!!" . env('DB_DATABASE');
    });

    Route::post('/device/init', function () {
        $response = [
            'success' => true,
            'msg' => env('DB_DATABASE'),
            'data' => [
                "agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36",
                "created_at" => "2020-11-18 05:06:41",
                "device_code" => "7cf08564c067f157425f3d8458ead413",
                "device_type" => 0,
                "id" => "15150",
                "updated_at" => "2020-11-18 05:06:41",
                "user_id" => null,
            ],
            'code' => 200,
            'errors' => null
        ];
        return response($response, 200);
    });
});

Route::group(['middleware' => ['cors', 'json.response'], 'prefix' => 'file', 'namespace' => 'File'], function () {
    Route::post('/upload', 'AjaxController@upload')->name('file.upload');
    Route::post('/ckeditor-upload', 'AjaxController@ckeditorUpload')->name('file.ckeditorUpload');
});

Route::group(['middleware' => ['cors', 'json.response'], 'prefix' => 'auth'], function () {
    Route::post('/login', 'Auth\ApiAuthController@login')->name('login.api');
    Route::post('/register', 'Auth\ApiAuthController@register')->name('register.api');
    Route::get('/logout', 'Auth\ApiAuthController@logout')->name('logout.api');
});

Route::middleware([])->group(function () {
    Route::group(['prefix' => 'auth', 'namespace' => 'User'], function () {
        Route::get('/profile', 'AjaxController@getProfile')->name('api.user.get_profile');
    });

    // sell
    Route::group(['prefix' => 'sell', 'namespace' => 'Sell'], function () {
        // order
        Route::get('/order/list', 'OrderController@list')->name('api.sell.order.list');
        Route::get('/order/detail/{id}', 'OrderController@detail')->name('api.sell.order.detail');
        Route::get('/order/create-init/{id}', 'OrderController@createInit')->name('api.sell.order.create.init');
        Route::post('/order', 'OrderController@create')->name('api.sell.order.post');
        Route::put('/order/{id}', 'OrderController@update')->name('api.sell.order.update');
        Route::put('/order/{id}/request-approve', 'OrderController@pending')->name('api.sell.order.pending');
        Route::put('/order/{id}/approve', 'OrderController@approve')->name('api.sell.order.approve');
        Route::put('/order/{id}/unapprove', 'OrderController@unapprove')->name('api.sell.order.unapprove');
        Route::put('/order/{id}/reject', 'OrderController@reject')->name('api.sell.order.reject');
        Route::put('/order/{id}/accountant/approve', 'OrderController@accountantApprove')
            ->name('api.sell.order.accountant.approve');
        Route::put('/order/update/status', 'OrderController@changeStatus')
            ->name('api.sell.order.update.status');
        Route::put('/order/{id}/accountant/reject', 'OrderController@accountantReject')
            ->name('api.sell.order.accountant.reject');
        Route::delete('/order/{id}', 'OrderController@delete')->name('api.sell.order.delete');
        Route::post('/order/import-data', 'OrderController@importData')
            ->name('api.sell.order.import.data');
        Route::get('/order/list-product', 'OrderController@listProduct');

        // price quote
        Route::get('/price-quote/list', 'PriceQuoteController@list')->name('api.sell.price.quote.list');
        Route::get('/price-quote/detail/{id}', 'PriceQuoteController@detail')->name('api.sell.price.quote.detail');
        Route::get('/price-quote/create-init/{id}', 'PriceQuoteController@createInit')->name('api.sell.price.quote.create.init');
        Route::post('/price-quote', 'PriceQuoteController@create')->name('api.sell.price.quote.post');
        Route::put('/price-quote/{id}', 'PriceQuoteController@update')->name('api.sell.price.quote.update');
        Route::put('/price-quote/{id}', 'PriceQuoteController@update')->name('api.sell.price.quote.update');
        Route::put('/price-quote/{id}/request-approve', 'PriceQuoteController@pending')->name('api.sell.price.quote.pending');
        Route::put('/price-quote/{id}/approve', 'PriceQuoteController@approve')->name('api.sell.price.quote.approve');
        Route::put('/price-quote/{id}/reject', 'PriceQuoteController@reject')->name('api.sell.price.quote.reject');
        Route::put('/price-quote/{id}/accountant/approve', 'PriceQuoteController@accountantApprove')
            ->name('api.sell.price.quote.accountant.approve');
        Route::put('/price-quote/update/status', 'PriceQuoteController@changeStatus')
            ->name('api.sell.price.quote.update.status');
        Route::put('/price-quote/{id}/accountant/reject', 'PriceQuoteController@accountantReject')
            ->name('api.sell.price.quote.accountant.reject');
        Route::delete('/price-quote/{id}', 'PriceQuoteController@delete')->name('api.sell.price.quote.delete');
        Route::get('/price-quote/list-product', 'PriceQuoteController@listProduct');

        // invoice
        Route::get('/invoice/list', 'InvoiceController@list')
            ->name('api.sell.invoice.list');
        Route::get('/invoice/create-draft', 'InvoiceController@createDraft')
            ->name('api.sell.invoice.create.draft');
        Route::get('/invoice/detail/{id}', 'InvoiceController@detail')
            ->name('api.sell.invoice.detail');
        Route::post('/invoice', 'InvoiceController@create')
            ->name('api.sell.invoice.post');
        Route::put('/invoice/{id}', 'InvoiceController@update')
            ->name('api.sell.invoice.update');
        Route::delete('/invoice/{id}', 'InvoiceController@delete')
            ->name('api.sell.invoice.delete');

        // delivery
        Route::get('/delivery/list', 'DeliveryController@list')->name('api.sell.delivery.list');
        Route::get('/delivery/detail/{id}', 'DeliveryController@detail')->name('api.sell.delivery.detail');
        Route::put('/delivery/{id}/pending', 'DeliveryController@pending')->name('api.sell.delivery.pending');
        Route::put('/delivery/{id}/approve', 'DeliveryController@approve')->name('api.sell.delivery.approve');
        Route::put('/delivery/{id}/reject', 'DeliveryController@reject')->name('api.sell.delivery.reject');
        Route::put('/delivery/update/status', 'DeliveryController@changeStatus')
            ->name('api.sell.delivery.update.status');
        Route::delete('/delivery/{id}', 'DeliveryController@delete')->name('api.sell.delivery.delete');
    });

    // pos
    Route::group(['prefix' => 'sell-pos', 'namespace' => 'SellPos'], function () {
        // order
        Route::get('/order/list', 'OrderController@list')->name('api.sell.pos.order.list');
        Route::get('/order/detail/{id}', 'OrderController@detail')->name('api.sell.pos.order.detail');
        Route::post('/order', 'OrderController@create')->name('api.sell.pos.order.post');
        Route::delete('/order/{id}', 'OrderController@delete')->name('api.sell.pos.order.delete');
    });

    // purchase
    Route::group(['prefix' => 'purchase', 'namespace' => 'Purchase'], function () {
        // order
        Route::get('/order/list', 'OrderController@list')->name('api.purchase.order.list');
        Route::get('/order/detail/{id}', 'OrderController@detail')->name('api.purchase.order.detail');
        Route::get('/order/create-init', 'OrderController@createInit')->name('api.purchase.order.create.init');
        Route::post('/order', 'OrderController@create')->name('api.purchase.order.post');
        Route::put('/order/{id}', 'OrderController@update')->name('api.purchase.order.update');
        Route::put('/order/{id}/request-approve', 'OrderController@pending')->name('api.purchase.order.pending');
        Route::put('/order/{id}/approve', 'OrderController@approve')->name('api.purchase.order.approve');
        Route::put('/order/{id}/unapprove', 'OrderController@unapprove')->name('api.sell.order.unapprove');
        Route::put('/order/{id}/reject', 'OrderController@reject')->name('api.purchase.order.reject');
        Route::put('/order/{id}/accountant/approve', 'OrderController@accountantApprove')
            ->name('api.purchase.order.accountant.approve');
        Route::put('/order/{id}/accountant/reject', 'OrderController@accountantReject')
            ->name('api.purchase.order.accountant.reject');
        Route::put('/order/update/status', 'OrderController@changeStatus')
            ->name('api.purchase.order.update.status');
        Route::delete('/order/{id}', 'OrderController@delete')->name('api.purchase.order.delete');
        Route::post('/order/import-data', 'OrderController@importData')
            ->name('api.purchase.order.import.data');
        Route::get('/order/list-product', 'OrderController@listProduct');

        // invoice
        Route::get('/invoice/list', 'InvoiceController@list')
            ->name('api.purchase.invoice.list');
        Route::get('/invoice/create-draft', 'InvoiceController@createDraft')
            ->name('api.purchase.invoice.create.draft');
        Route::get('/invoice/product-contact', 'InvoiceController@listProductForContact')
            ->name('api.purchase.invoice.product.contact');
        Route::get('/invoice/detail/{id}', 'InvoiceController@detail')
            ->name('api.purchase.invoice.detail');
        Route::post('/invoice', 'InvoiceController@create')
            ->name('api.purchase.invoice.post');
        Route::put('/invoice/{id}', 'InvoiceController@update')
            ->name('api.purchase.invoice.update');
        Route::delete('/invoice/{id}', 'InvoiceController@delete')
            ->name('api.purchase.invoice.delete');

        // debt
        Route::get('/debt/list', 'DebtController@list')
            ->name('api.purchase.debt.list');
        Route::get('/debt/detail/{id}', 'DebtController@detail')
            ->name('api.purchase.debt.detail');
        Route::get('/debt/create-init', 'DebtController@createInit')
            ->name('api.purchase.debt.create.init');
        Route::post('/debt', 'DebtController@create')
            ->name('api.purchase.debt.post');
        Route::put('/debt/{id}', 'DebtController@update')
            ->name('api.purchase.debt.update');
        Route::delete('/debt/{id}', 'DebtController@delete')
            ->name('api.purchase.debt.delete');

        // delivery
        Route::get('/delivery/list', 'DeliveryController@list')->name('api.purchase.delivery.list');
        Route::get('/delivery/detail/{id}', 'DeliveryController@detail')->name('api.purchase.delivery.detail');
        Route::put('/delivery/{id}/pending', 'DeliveryController@pending')->name('api.purchase.delivery.pending');
        Route::put('/delivery/{id}/approve', 'DeliveryController@approve')->name('api.purchase.delivery.approve');
        Route::put('/delivery/{id}/reject', 'DeliveryController@reject')->name('api.purchase.delivery.reject');
        Route::put('/delivery/update/status', 'DeliveryController@changeStatus')
            ->name('api.purchase.delivery.update.status');
        Route::delete('/delivery/{id}', 'DeliveryController@delete')->name('api.purchase.delivery.delete');
    });

    // Debt
    Route::group(['prefix' => 'debt', 'namespace' => 'Debt'], function () {
        // customer
        Route::get('/customer/list', 'DebtCustomerController@list')->name('api.debt.customer.list');
        Route::get('/customer/list-order', 'DebtCustomerController@listOrder')
            ->name('api.debt.customer.list.order');
        Route::get('/customer/list-product', 'DebtCustomerController@listProduct')
            ->name('api.debt.customer.list.product');
        Route::get('/customer/detail/{id}', 'DebtCustomerController@detail')->name('api.debt.customer.detail');
        Route::get('/customer/create-init/{id}', 'DebtCustomerController@createInit')->name('api.debt.customer.create.init');
        Route::post('/customer', 'DebtCustomerController@create')->name('api.debt.customer.post');
        Route::post('/customer/pay-all', 'DebtCustomerController@paymentAll')
            ->name('api.debt.customer.pay.all');
        Route::put('/customer/{id}', 'DebtCustomerController@update')->name('api.customer.order.update');
        Route::delete('/order/{id}', 'DebtSupplierController@delete')->name('api.debt.customer.delete');

        // supplier
        Route::get('/supplier/list', 'DebtSupplierController@list')->name('api.debt.supplier.list');
        Route::get('/supplier/list-order', 'DebtSupplierController@listOrder')->name('api.debt.supplier.list.order');
        Route::get('/supplier/list-product', 'DebtSupplierController@listProduct')
            ->name('api.debt.supplier.list.product');
        Route::get('/supplier/detail/{id}', 'DebtSupplierController@detail')->name('api.debt.supplier.detail');
        Route::get('/supplier/create-init/{id}', 'DebtSupplierController@createInit')->name('api.debt.supplier.create.init');
        Route::post('/supplier', 'DebtSupplierController@create')->name('api.debt.supplier.post');
        Route::post('/supplier/pay-all', 'DebtSupplierController@paymentAll')
            ->name('api.debt.supplier.pay.all');
        Route::put('/supplier/{id}', 'DebtSupplierController@update')->name('api.supplier.order.update');
        Route::delete('/order/{id}', 'DebtSupplierController@delete')->name('api.debt.supplier.delete');

    });


    // purchase request
    Route::group(['prefix' => 'purchase-request', 'namespace' => 'PurchaseRequest'], function () {
        // order
        Route::get('/order/list', 'OrderController@list')->name('api.purchase.request.order.list');
        Route::get('/product-list', 'OrderController@listProduct');
        Route::get('/print/{id}', 'OrderController@print')->name('api.purchase.request.order.print');
        Route::get('/order/{id}', 'OrderController@detail')->name('api.purchase.request.order.detail');
        Route::get('/order/create-init/{id}', 'OrderController@createInit')->name('api.purchase.request.order.create.init');
        Route::post('/order', 'OrderController@create')->name('api.purchase.request.order.post');
        Route::put('/order/{id}', 'OrderController@update')->name('api.purchase.request.order.update');
        Route::put('/order/{id}/request-approve', 'OrderController@pending')->name('api.purchase.request.order.pending');
        Route::put('/order/{id}/approve', 'OrderController@approve')->name('api.purchase.request.order.approve');
        Route::put('/order/{id}/unapprove', 'OrderController@unapprove')->name('api.sell.order.unapprove');
        Route::put('/order/{id}/reject', 'OrderController@reject')->name('api.purchase.request.order.reject');
        Route::put('/order/update/status', 'OrderController@changeStatus')
            ->name('api.purchase.request.order.change.status');
        Route::delete('/order/{id}', 'OrderController@delete')->name('api.purchase.request.order.delete');
    });


    // purchase return
    Route::group(['prefix' => 'purchase-return', 'namespace' => 'PurchaseReturn'], function () {
        // order
        Route::get('/order/list', 'OrderController@list')->name('api.purchase.return.order.list');
        Route::get('/print/{id}', 'OrderController@print')->name('api.purchase.return.order.print');
        Route::get('/order/{id}', 'OrderController@detail')->name('api.purchase.return.order.detail');
        Route::get('/order/create-init/{id}', 'OrderController@createInit')->name('api.purchase.return.order.create.init');
        Route::post('/order', 'OrderController@create')->name('api.purchase.return.order.post');
        Route::put('/order/{id}', 'OrderController@update')->name('api.purchase.return.order.update');
        Route::put('/order/{id}/request-approve', 'OrderController@pending')->name('api.purchase.return.order.pending');
        Route::put('/order/{id}/approve', 'OrderController@approve')->name('api.purchase.return.order.approve');
        Route::put('/order/{id}/reject', 'OrderController@reject')->name('api.purchase.return.order.reject');
        Route::put('/order/{id}/accountant/approve', 'OrderController@accountantApprove')
            ->name('api.purchase.return.accountant.approve');
        Route::put('/order/{id}/accountant/reject', 'OrderController@accountantReject')
            ->name('api.purchase.return.accountant.reject');
        Route::put('/order/update/status', 'OrderController@changeStatus')
            ->name('api.purchase.return.update.status');
        Route::delete('/order/{id}', 'OrderController@delete')->name('api.purchase.return.order.delete');
    });

    // sale return
    Route::group(['prefix' => 'sell-return', 'namespace' => 'SellReturn'], function () {
        // order
        Route::get('/order/list', 'OrderController@list')->name('api.sell.return.order.list');
        Route::get('/print/{id}', 'OrderController@print')->name('api.sell.return.order.print');
        Route::get('/order/{id}', 'OrderController@detail')->name('api.sell.return.order.detail');
        Route::get('/order/create-init/{id}', 'OrderController@createInit')->name('api.sell.return.order.create.init');
        Route::post('/order', 'OrderController@create')->name('api.sell.return.order.post');
        Route::put('/order/{id}', 'OrderController@update')->name('api.sell.return.order.update');
        Route::put('/order/{id}/request-approve', 'OrderController@pending')->name('api.sell.return.order.pending');
        Route::put('/order/{id}/approve', 'OrderController@approve')->name('api.sell.return.order.approve');
        Route::put('/order/{id}/reject', 'OrderController@reject')->name('api.sell.return.order.reject');
        Route::put('/order/{id}/accountant/approve', 'OrderController@accountantApprove')
            ->name('api.sell.return.accountant.approve');
        Route::put('/order/{id}/accountant/reject', 'OrderController@accountantReject')
            ->name('api.sell.return.accountant.reject');
        Route::put('/order/update/status', 'OrderController@changeStatus')
            ->name('api.sell.return.update.status');
        Route::delete('/order/{id}', 'OrderController@delete')->name('api.sell.return.order.delete');
    });

    // payment
    Route::group(['namespace' => 'Payment'], function () {
        // receive
        Route::get('/payment/list', 'PaymentController@list')
            ->name('api.payment.list');
        Route::get('/payment/inventory', 'PaymentController@inventory')
            ->name('api.payment.inventory');
        Route::get('/payment/report', 'PaymentController@report')
            ->name('api.payment.report');
        Route::get('/payment/detail/{id}', 'PaymentController@detail')
            ->name('api.payment.detail');
        Route::post('/payment', 'PaymentController@create')
            ->name('api.payment.post');
        Route::put('/payment/{id}', 'PaymentController@update')
            ->name('api.payment.update');
        Route::delete('/payment/{id}', 'PaymentController@delete')
            ->name('api.payment.delete');
    });

    // product
    Route::group(['namespace' => 'Product'], function () {
        // list
        Route::get('/product/list', 'ProductController@list')
            ->name('api.product.list');
        Route::get('/product/list', 'ProductController@list')
            ->name('api.product.list');
        Route::get('/product/detail/{id}', 'ProductController@detail')
            ->name('api.product.detail');
        Route::post('/product', 'ProductController@create')
            ->name('api.product.post');
        Route::post('/product/import-data', 'ProductController@importData')
            ->name('api.product.import.data');
        Route::put('/product/{id}', 'ProductController@update')
            ->name('api.product.update');
        Route::put('/product', 'ProductController@updateStatus')
            ->name('api.product.update.status');
        Route::delete('/product/{id}', 'ProductController@delete')
            ->name('api.product.delete');
    });

    // Tax
    Route::group(['namespace' => 'Tax'], function () {
        // list
        Route::get('/tax/list', 'TaxController@list')
            ->name('api.tax.list');
        Route::get('/tax/detail/{id}', 'TaxController@detail')
            ->name('api.tax.detail');
        Route::post('/tax', 'TaxController@create')
            ->name('api.tax.post');
        Route::put('/tax/{id}', 'TaxController@update')
            ->name('api.tax.update');
        Route::delete('/tax/{id}', 'TaxController@delete')
            ->name('api.tax.delete');
    });

    // Category
    Route::group(['namespace' => 'Category'], function () {
        // list
        Route::get('/category/list', 'CategoryController@list')
            ->name('api.category.list');
        Route::get('/category/detail/{id}', 'CategoryController@detail')
            ->name('api.category.detail');
        Route::post('/category', 'CategoryController@create')
            ->name('api.category.post');
        Route::post('/category/import-data', 'CategoryController@importData')
            ->name('api.category.import.data');
        Route::put('/category/{id}', 'CategoryController@update')
            ->name('api.category.update');
        Route::delete('/category/{id}', 'CategoryController@delete')
            ->name('api.category.delete');
    });

    // brand
    Route::group(['namespace' => 'Brand'], function () {
        // list
        Route::get('/brand/list', 'BrandController@list')
            ->name('api.brand.list');
        Route::get('/brand/detail/{id}', 'BrandController@detail')
            ->name('api.brand.detail');
        Route::post('/brand', 'BrandController@create')
            ->name('api.brand.post');
        Route::put('/brand/{id}', 'BrandController@update')
            ->name('api.brand.update');
        Route::post('/brand/import-data', 'BrandController@importData')
            ->name('api.brand.import.data');
        Route::delete('/brand/{id}', 'BrandController@delete')
            ->name('api.brand.delete');
    });

    // transport
    Route::group(['namespace' => 'DeliveryCompany'], function () {
        // list
        Route::get('/delivery-company/list', 'AjaxController@list')
            ->name('api.delivery.company.list');
        Route::get('/delivery-company/detail/{id}', 'AjaxController@detail')
            ->name('api.delivery.company.detail');
        Route::post('/delivery-company', 'AjaxController@create')
            ->name('api.delivery.company.post');
        Route::put('/delivery-company/{id}', 'AjaxController@update')
            ->name('api.delivery-company.update');
        Route::delete('/delivery.company/{id}', 'AjaxController@delete')
            ->name('api.brand.delete');
    });

    // delivery

    // Unit
    Route::group(['namespace' => 'Unit'], function () {
        // list
        Route::get('/unit/list', 'UnitController@list')
            ->name('api.unit.list');
        Route::get('/unit/detail/{id}', 'UnitController@detail')
            ->name('api.unit.detail');
        Route::post('/unit', 'UnitController@create')
            ->name('api.unit.post');
        Route::post('/unit/import-data', 'UnitController@importData')
            ->name('api.unit.import.data');
        Route::put('/unit/{id}', 'UnitController@update')
            ->name('api.unit.update');
        Route::delete('/unit/{id}', 'UnitController@delete')
            ->name('api.unit.delete');
    });


    // Warranty
    Route::group(['namespace' => 'Warranty'], function () {
        // list
        Route::get('/warranty/list', 'WarrantyController@list')
            ->name('api.warranty.list');
        Route::get('/warranty/detail/{id}', 'WarrantyController@detail')
            ->name('api.warranty.detail');
        Route::post('/warranty', 'WarrantyController@create')
            ->name('api.warranty.post');
        Route::put('/warranty/{id}', 'WarrantyController@update')
            ->name('api.warranty.update');
        Route::delete('/warranty/{id}', 'WarrantyController@delete')
            ->name('api.warranty.delete');

        // history
        Route::get('/warranty/history/list', 'WarrantyHistoryController@list')
            ->name('api.warranty.history.list');
        Route::get('/warranty/history/detail/{id}', 'WarrantyHistoryController@detail')
            ->name('api.warranty.history.detail');
        Route::post('/warranty/history', 'WarrantyHistoryController@create')
            ->name('api.warranty.history.post');
        Route::put('/warranty/history/{id}', 'WarrantyHistoryController@update')
            ->name('api.warranty.history.update');
        Route::delete('/warranty/history/{id}', 'WarrantyHistoryController@delete')
            ->name('api.warranty.history.delete');
    });

    // stock
    Route::group(['namespace' => 'Stock'], function () {
        // list
        Route::get('/stock/list', 'StockController@list')
            ->name('api.stock.list');
        Route::get('/stock/detail/{id}', 'StockController@detail')
            ->name('api.stock.detail');
        Route::post('/stock', 'StockController@create')
            ->name('api.stock.post');
        Route::put('/stock/{id}', 'StockController@update')
            ->name('api.stock.update');
        Route::delete('/stock/{id}', 'StockController@delete')
            ->name('api.stock.delete');

        // order import
        Route::get('/stock/order-import/list', 'OrderImportController@list')->name('api.stock.import.list');
        Route::get('/stock/order-import/detail/{id}', 'OrderImportController@detail')->name('api.stock.import.detail');
        Route::get('/stock/order-import/create-init/{id}', 'OrderImportController@createInit')->name('api.stock.import.create.init');
        Route::post('/stock/order-import', 'OrderImportController@create')->name('api.stock.import.post');
        Route::put('/stock/order-import/{id}', 'OrderImportController@update')->name('api.stock.import.update');
        Route::put('/stock/order-import/{id}/pending', 'OrderImportController@pending')->name('api.stock.import.pending');
        Route::put('/stock/order-import/{id}/approve', 'OrderImportController@approve')->name('api.stock.import.approve');
        Route::put('/stock/order-import/{id}/reject', 'OrderImportController@reject')->name('api.stock.import.reject');
        Route::delete('/stock/order-import/{id}', 'OrderImportController@delete')->name('api.stock.import.delete');
        Route::get('/stock/order-import/product-list', 'OrderImportController@listProduct');

        // order export
        Route::get('/stock/order-export/list', 'OrderExportController@list')->name('api.stock.export.list');
        Route::get('/stock/order-export/detail/{id}', 'OrderExportController@detail')->name('api.stock.export.detail');
        Route::get('/stock/order-export/create-init/{id}', 'OrderExportController@createInit')->name('api.stock.export.create.init');
        Route::post('/stock/order-export', 'OrderExportController@create')->name('api.stock.export.post');
        Route::put('/stock/order-export/{id}', 'OrderExportController@update')->name('api.stock.export.update');
        Route::put('/stock/order-export/{id}/pending', 'OrderExportController@pending')->name('api.stock.export.pending');
        Route::put('/stock/order-export/{id}/approve', 'OrderExportController@approve')->name('api.stock.export.approve');
        Route::put('/stock/order-export/{id}/reject', 'OrderExportController@reject')->name('api.stock.export.reject');
        Route::delete('/stock/order-export/{id}', 'OrderExportController@delete')->name('api.stock.export.delete');
        Route::get('/stock/order-export/product-list', 'OrderExportController@listProduct');

    });


    Route::group(['prefix' => 'contact', 'namespace' => 'Contact'], function () {
        // customer
        Route::get('/customer/', 'AjaxController@listCustomer')->name('api.contact.customer.list');
        Route::get('/customer/{id}', 'AjaxController@detail')->name('api.contact.customer.detail');
        Route::delete('/customer/{id}', 'AjaxController@delete')->name('api.contact.customer.delete');

        // provider
        Route::get('/provider/', 'AjaxController@listSupplier')->name('api.contact.customer.list');
        Route::get('/provider/{id}', 'AjaxController@detail')->name('api.contact.customer.detail');
        Route::delete('/provider/{id}', 'AjaxController@delete')->name('api.contact.customer.delete');

        // employer
        Route::get('/employer/', 'AjaxController@listEmployer')->name('api.contact.customer.list');
        Route::get('/employer/{id}', 'AjaxController@detail')->name('api.contact.customer.detail');
        Route::delete('/employer/{id}', 'AjaxController@delete')->name('api.contact.customer.delete');

        // contact
        Route::get('/list', 'AjaxController@list')->name('api.contact.list');
        Route::get('/detail/{id}', 'AjaxController@detail')->name('api.contact.detail');
        Route::post('/create', 'AjaxController@create')->name('api.contact.post');
        Route::put('/update/{id}', 'AjaxController@update')->name('api.contact.update');
        Route::delete('/delete/{id}', 'AjaxController@delete')->name('api.contact.delete');
    });

    Route::group(['namespace' => 'Contact'], function () {
        Route::post('/customer/import-data', 'AjaxController@importDataCustomer')->name('api.customer.import.data');
        Route::post('/supplier/import-data', 'AjaxController@importDataSupplier')->name('api.supplier.import.data');
    });

    // common
    Route::group(['namespace' => 'Common'], function () {
        // customer
        Route::get('/locations', 'BussinessLocationController@list')->name('api.common.locations');
        Route::get('locations/{id}', 'BussinessLocationController@detail')->name('api.common.locations.detail');
        Route::post('locations/', 'BussinessLocationController@create')->name('api.common.locations.post');
        Route::put('locations/{id}', 'BussinessLocationController@update')->name('api.common.locations.update');
        Route::delete('locations/{id}', 'BussinessLocationController@delete')->name('api.common.locations.delete');
    });
    // options
    Route::group(['prefix' => 'options', 'namespace' => 'Options'], function () {
        // customer
        Route::get('/', 'AjaxController@list')->name('api.options.list');
        Route::get('/{id}', 'AjaxController@detail')->name('api.options.detail');
        Route::post('/', 'AjaxController@create')->name('api.options.post');
        Route::put('/{id}', 'AjaxController@update')->name('api.options.update');
        Route::delete('/{id}', 'AjaxController@delete')->name('api.options.delete');
    });

    // report
    // purchase request
    Route::group(['prefix' => 'report', 'namespace' => 'Report'], function () {
        // sell order
        Route::get('/sell/order/summary', 'SellOrderController@summary')
            ->name('api.report.sell.order.summary');
        Route::get('/sell/order/total-summary', 'SellOrderController@totalSummary')
            ->name('api.report.sell.order.total.summary');

        // purchase order
        Route::get('/purchase/order/summary', 'PurchaseOrderController@summary')
            ->name('api.report.purchase.order.summary');
        Route::get('/purchase/order/total-summary', 'PurchaseOrderController@totalSummary')
            ->name('api.report.purchase.order.total.summary');
    });

    // blog
    Route::group(['namespace' => 'Blog'], function () {
        // list
        Route::get('/blog/list', 'BlogController@list')
            ->name('api.blog.list');
        Route::get('/blog/detail/{id}', 'BlogController@detail')
            ->name('api.blog.detail');
        Route::post('/blog', 'BlogController@create')
            ->name('api.blog.post');
        Route::put('/blog/{id}', 'BlogController@update')
            ->name('api.blog.update');
        Route::delete('/blog/{id}', 'BlogController@delete')
            ->name('api.blog.delete');
    });

    // menu
    Route::group(['namespace' => 'Menu'], function () {
        // list
        Route::get('/menu/list', 'MenuController@list')
            ->name('api.menu.list');
        Route::get('/menu/detail/{id}', 'MenuController@detail')
            ->name('api.menu.detail');
        Route::post('/menu', 'MenuController@create')
            ->name('api.menu.post');
        Route::put('/menu/update/{id}', 'MenuController@update')
            ->name('api.menu.update');
        Route::put('/menu/update-node', 'MenuController@updateNode')
            ->name('api.menu.update.node');
        Route::delete('/menu/{id}', 'MenuController@delete')
            ->name('api.menu.delete');
    });

    // User
    Route::group(['namespace' => 'User', 'prefix' => 'user' ], function () {
        // role
        Route::get('/role/list', 'RoleController@list')
            ->name('api.role.list');
        Route::get('/role/detail/{id}', 'RoleController@detail')
            ->name('api.role.detail');
        Route::post('/role', 'RoleController@create')
            ->name('api.role.post');
        Route::put('/role/update/{id}', 'RoleController@update')
            ->name('api.role.update');
        Route::delete('/role/{id}', 'RoleController@delete')
            ->name('api.role.delete');

        // permission
        Route::get('/permission/list', 'PermissionController@list')
            ->name('api.permission.list');
        Route::get('/permission/detail/{id}', 'PermissionController@detail')
            ->name('api.permission.detail');
        Route::post('/permission', 'PermissionController@create')
            ->name('api.permission.post');
        Route::put('/permission/update/{id}', 'PermissionController@update')
            ->name('api.permission.update');
        Route::delete('/permission/{id}', 'PermissionController@delete')
            ->name('api.permission.delete');

        // profile
        Route::get('/profile/list', 'ProfileController@list')
            ->name('api.profile.list');
        Route::get('/profile/detail/{id}', 'ProfileController@detail')
            ->name('api.profile.detail');
        Route::post('/profile', 'ProfileController@create')
            ->name('api.profile.post');
        Route::put('/profile/update/{id}', 'ProfileController@update')
            ->name('api.profile.update');
        Route::delete('/profile/{id}', 'ProfileController@delete')
            ->name('api.profile.delete');
});

Route::get('/test', function () {
    $response = [
        'user' => "hello",
        'token' => "token"
    ];
    return response($response, 200);
});
});
