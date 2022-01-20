<?php

namespace App\Http\Controllers\Report;

use App\Account;
use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Contact;
use App\CustomerGroup;
use App\Helpers\Activity;
use App\Http\Controllers\Controller;
use App\InvoiceScheme;
use App\Product;
use App\PurchaseLine;
use App\SellingPriceGroup;
use App\Stock;
use App\StockProduct;
use App\TaxRate;
use App\Transaction;
use App\TransactionSellLine;
use App\TypesOfService;
use App\Unit;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Warranty;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\File;

class SellOrderController extends Controller
{
    /**
     * All Utils instance.
     *
     */
    protected $contactUtil;
    protected $businessUtil;
    protected $transactionUtil;
    protected $productUtil;


    /**
     * Constructor
     *
     * @param ProductUtils $product
     * @return void
     */
    public function __construct(ContactUtil $contactUtil, BusinessUtil $businessUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, ProductUtil $productUtil)
    {
        $this->contactUtil = $contactUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->productUtil = $productUtil;

        $this->dummyPaymentLine = ['method' => '', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => ''];

        $this->shipping_status_colors = [
            'ordered' => 'bg-yellow',
            'packed' => 'bg-info',
            'shipped' => 'bg-navy',
            'delivered' => 'bg-green',
            'cancelled' => 'bg-red',
        ];
    }

    public function summary(Request $request)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $location_id = $request->location_id;
            $stock_id = $request->stock_id;

            $type_group = "day";

            if (!empty(request()->type_group)) {
                $type_group = request()->type_group;
            }

            $data = [];

            if ($type_group === "day") {
                $startDate = Carbon::now()->startOfMonth();
                $endEnd = Carbon::now()->endOfMonth();

                if (!empty(request()->start_date)) {
                    $startDate = Carbon::createFromFormat('d/m/Y', request()->start_date);
                }

                if (!empty(request()->end_date)) {
                    $endEnd = Carbon::createFromFormat('d/m/Y', request()->end_date);
                }

                $fileSellOrder = __DIR__ . '/sql/order_by_day.sql';

                if (File::exists($fileSellOrder)) {
                    $querySellOrder = File::get($fileSellOrder);

                    $querySellOrder = str_replace('$business_id', $business_id, $querySellOrder);
                    $querySellOrder = str_replace('$location_id', $location_id, $querySellOrder);
                    $querySellOrder = str_replace('$type', "sell", $querySellOrder);
                    $querySellOrder = str_replace('$start_date', $startDate, $querySellOrder);
                    $querySellOrder = str_replace('$end_date', "$endEnd", $querySellOrder);

                    $data = DB::select($querySellOrder);
                }
            }

            if ($type_group === "month") {
                $startDate = Carbon::now()->startOfYear();
                $endEnd = Carbon::now()->endOfYear();

                if (!empty(request()->start_date)) {
                    $startDate = Carbon::createFromFormat('d/m/Y', request()->start_date);
                }

                if (!empty(request()->end_date)) {
                    $endEnd = Carbon::createFromFormat('d/m/Y', request()->end_date);
                }

                $fileSellOrder = __DIR__ . '/sql/order_by_month.sql';

                if (File::exists($fileSellOrder)) {
                    $querySellOrder = File::get($fileSellOrder);

                    $querySellOrder = str_replace('$business_id', $business_id, $querySellOrder);
                    $querySellOrder = str_replace('$location_id', $location_id, $querySellOrder);
                    $querySellOrder = str_replace('$type', "sell", $querySellOrder);
                    $querySellOrder = str_replace('$start_date', $startDate, $querySellOrder);
                    $querySellOrder = str_replace('$end_date', "$endEnd", $querySellOrder);

                    $data = DB::select($querySellOrder);
                }
            }

            if ($type_group === "year") {
                $fileSellOrder = __DIR__ . '/sql/order_by_year.sql';

                if (File::exists($fileSellOrder)) {
                    $querySellOrder = File::get($fileSellOrder);

                    $querySellOrder = str_replace('$business_id', $business_id, $querySellOrder);
                    $querySellOrder = str_replace('$location_id', $location_id, $querySellOrder);
                    $querySellOrder = str_replace('$type', "sell", $querySellOrder);
                    $querySellOrder = str_replace('$start_date', null, $querySellOrder);
                    $querySellOrder = str_replace('$end_date', null, $querySellOrder);

                    $data = DB::select($querySellOrder);
                }
            }

            return $this->respondSuccess($data, null);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function totalSummary(Request $request)
    {
        try {
            $user_id = Auth::guard('api')->user()->id;

            $location_id = $request->location_id;
            $stock_id = $request->stock_id;

            $startDate = Carbon::now()->startOfMonth();
            $endEnd = Carbon::now()->endOfMonth();

            if (!empty(request()->start_date)) {
                $startDate = Carbon::createFromFormat('d/m/Y', request()->start_date);
            }

            if (!empty(request()->end_date)) {
                $endEnd = Carbon::createFromFormat('d/m/Y', request()->end_date);
            }

            $view_all = null;

            if (!empty($request->view_all)) {
                $view_all = $request->view_all;
            }

            if (!empty($request->header('view-all'))) {
                $view_all = $request->header('view-all');
            }

            // summary sale order
            $fileSOrder = __DIR__ . '/sql/order_summary.sql';
            $fileDept = __DIR__ . '/sql/dept_summary.sql';
            $fileOrderSummary = __DIR__ . '/sql/sale_order_summary.sql';
            $file_stock = __DIR__ . '/sql/stock.sql';

            $data_sell = [];

            if (File::exists($fileSOrder)) {
                $querySellOrder = File::get($fileSOrder);

                $querySellOrder = str_replace('$location_id', $location_id, $querySellOrder);
                $querySellOrder = str_replace('$type', "sell", $querySellOrder);
                $querySellOrder = str_replace('$start_date', $startDate, $querySellOrder);
                $querySellOrder = str_replace('$end_date', "$endEnd", $querySellOrder);

                if (empty($view_all) || $view_all != "1") {
                    $querySellOrder = str_replace('$create_by', "created_by = '$user_id'", $querySellOrder);
                } else {
                    $querySellOrder = str_replace('$create_by', "1 = 1", $querySellOrder);
                }

                $data_sell = DB::select($querySellOrder);
            }

            // summary debt customer
            $data_dept_customer = [];

            if (File::exists($fileDept)) {
                $queryDebtCustomer = File::get($fileDept);

                $queryDebtCustomer = str_replace('$location_id', $location_id, $queryDebtCustomer);
                $queryDebtCustomer = str_replace('$type', "sell", $queryDebtCustomer);
                $queryDebtCustomer = str_replace('$start_date', $startDate, $queryDebtCustomer);
                $queryDebtCustomer = str_replace('$end_date', "$endEnd", $queryDebtCustomer);

                if (empty($view_all) || $view_all != "1") {
                    $queryDebtCustomer = str_replace('$create_by', "created_by = '$user_id'", $queryDebtCustomer);
                } else {
                    $queryDebtCustomer = str_replace('$create_by', "1 = 1", $queryDebtCustomer);
                }

                $data_dept_customer = DB::select($queryDebtCustomer);
            }

            // summary purchase order
            $data_purchase = [];

            if (File::exists($fileSOrder)) {
                $queryPurchaseOrder = File::get($fileSOrder);

                $queryPurchaseOrder = str_replace('$location_id', $location_id, $queryPurchaseOrder);
                $queryPurchaseOrder = str_replace('$type', "purchase", $queryPurchaseOrder);
                $queryPurchaseOrder = str_replace('$start_date', $startDate, $queryPurchaseOrder);
                $queryPurchaseOrder = str_replace('$end_date', "$endEnd", $queryPurchaseOrder);

                if (empty($view_all) || $view_all != "1") {
                    $queryPurchaseOrder = str_replace('$create_by', "created_by = '$user_id'", $queryPurchaseOrder);
                } else {
                    $queryPurchaseOrder = str_replace('$create_by', "1 = 1", $queryPurchaseOrder);
                }

                $data_purchase = DB::select($queryPurchaseOrder);
            }

            // summary debt supplier
            $data_dept_supplier = [];

            if (File::exists($fileDept)) {
                $queryDebtSupplier = File::get($fileDept);

                $queryDebtSupplier = str_replace('$location_id', $location_id, $queryDebtSupplier);
                $queryDebtSupplier = str_replace('$type', "purchase", $queryDebtSupplier);
                $queryDebtSupplier = str_replace('$start_date', $startDate, $queryDebtSupplier);
                $queryDebtSupplier = str_replace('$end_date', "$endEnd", $queryDebtSupplier);

                if (empty($view_all) || $view_all != "1") {
                    $queryDebtSupplier = str_replace('$create_by', "created_by = '$user_id'", $queryDebtSupplier);
                } else {
                    $queryDebtSupplier = str_replace('$create_by', "1 = 1", $queryDebtSupplier);
                }

                $data_dept_supplier = DB::select($queryDebtSupplier);
            }

            // data sale order summary
            $data_sale_order_summary = [];

            if (File::exists($fileOrderSummary)) {
                $querySOSummary = File::get($fileOrderSummary);

                $querySOSummary = str_replace('$location_id', $location_id, $querySOSummary);
                $querySOSummary = str_replace('$type', "sell", $querySOSummary);
                $querySOSummary = str_replace('$status', "status", $querySOSummary);
                $querySOSummary = str_replace('$condition', "1 = 1", $querySOSummary);
                $querySOSummary = str_replace('$start_date', $startDate, $querySOSummary);
                $querySOSummary = str_replace('$end_date', "$endEnd", $querySOSummary);

                if (empty($view_all) || $view_all != "1") {
                    $querySOSummary = str_replace('$create_by', "created_by = '$user_id'", $querySOSummary);
                } else {
                    $querySOSummary = str_replace('$create_by', "1 = 1", $querySOSummary);
                }

                $data_sale_order_summary = DB::select($querySOSummary);
            }

            // data purchase order summary
            $data_purchase_order_summary = [];

            if (File::exists($fileOrderSummary)) {
                $queryPOSummary = File::get($fileOrderSummary);

                $queryPOSummary = str_replace('$location_id', $location_id, $queryPOSummary);
                $queryPOSummary = str_replace('$type', "purchase", $queryPOSummary);
                $queryPOSummary = str_replace('$status', "status", $queryPOSummary);
                $queryPOSummary = str_replace('$condition', "1 = 1", $queryPOSummary);
                $queryPOSummary = str_replace('$start_date', $startDate, $queryPOSummary);
                $queryPOSummary = str_replace('$end_date', "$endEnd", $queryPOSummary);

                if (empty($view_all) || $view_all != "1") {
                    $queryPOSummary = str_replace('$create_by', "created_by = '$user_id'", $queryPOSummary);
                } else {
                    $queryPOSummary = str_replace('$create_by', "1 = 1", $queryPOSummary);
                }

                $data_purchase_order_summary = DB::select($queryPOSummary);
            }

            // data res order sale order summary
            $data_res_sale_order_summary = [];

            if (File::exists($fileOrderSummary)) {
                $queryResOrderSOSummary = File::get($fileOrderSummary);

                $queryResOrderSOSummary = str_replace('$location_id', $location_id, $queryResOrderSOSummary);
                $queryResOrderSOSummary = str_replace('$type', "sell", $queryResOrderSOSummary);
                $queryResOrderSOSummary = str_replace('$status', "res_order_status", $queryResOrderSOSummary);
                $queryResOrderSOSummary = str_replace('$condition', "status = 'approve'", $queryResOrderSOSummary);
                $queryResOrderSOSummary = str_replace('$start_date', $startDate, $queryResOrderSOSummary);
                $queryResOrderSOSummary = str_replace('$end_date', "$endEnd", $queryResOrderSOSummary);

                if (empty($view_all) || $view_all != "1") {
                    $queryResOrderSOSummary = str_replace('$create_by', "created_by = '$user_id'", $queryResOrderSOSummary);
                } else {
                    $queryResOrderSOSummary = str_replace('$create_by', "1 = 1", $queryResOrderSOSummary);
                }

                $data_res_sale_order_summary = DB::select($queryResOrderSOSummary);
            }

            // data res order purchase order summary
            $data_res_purchase_order_summary = [];

            if (File::exists($fileOrderSummary)) {
                $queryResOrderPOSummary = File::get($fileOrderSummary);

                $queryResOrderPOSummary = str_replace('$location_id', $location_id, $queryResOrderPOSummary);
                $queryResOrderPOSummary = str_replace('$type', "purchase", $queryResOrderPOSummary);
                $queryResOrderPOSummary = str_replace('$status', "res_order_status", $queryResOrderPOSummary);
                $queryResOrderPOSummary = str_replace('$condition', "status = 'approve'", $queryResOrderPOSummary);
                $queryResOrderPOSummary = str_replace('$start_date', $startDate, $queryResOrderPOSummary);
                $queryResOrderPOSummary = str_replace('$end_date', "$endEnd", $queryResOrderPOSummary);

                if (empty($view_all) || $view_all != "1") {
                    $queryResOrderPOSummary = str_replace('$create_by', "created_by = '$user_id'", $queryResOrderPOSummary);
                } else {
                    $queryResOrderPOSummary = str_replace('$create_by', "1 = 1", $queryResOrderPOSummary);
                }

                $data_res_purchase_order_summary = DB::select($queryResOrderPOSummary);
            }

            // data receipt sale order summary
            $data_receipt_so_status = [];
            if (File::exists($fileOrderSummary)) {
                $queryReceiptSO = File::get($fileOrderSummary);

                $queryReceiptSO = str_replace('$location_id', $location_id, $queryReceiptSO);
                $queryReceiptSO = str_replace('$type', "sell", $queryReceiptSO);
                $queryReceiptSO = str_replace('$status', "receipt_status", $queryReceiptSO);
                $queryReceiptSO = str_replace('$condition', "status = 'approve'", $queryReceiptSO);
                $queryReceiptSO = str_replace('$start_date', $startDate, $queryReceiptSO);
                $queryReceiptSO = str_replace('$end_date', "$endEnd", $queryReceiptSO);

                if (empty($view_all) || $view_all != "1") {
                    $queryReceiptSO = str_replace('$create_by', "created_by = '$user_id'", $queryReceiptSO);
                }else {
                    $queryReceiptSO = str_replace('$create_by', "1 = 1", $queryReceiptSO);
                }

                $data_receipt_so_status = DB::select($queryReceiptSO);
            }

            // data receipt purchase order summary
            $data_receipt_po_status = [];
            if (File::exists($fileOrderSummary)) {
                $queryReceiptPO = File::get($fileOrderSummary);

                $queryReceiptPO = str_replace('$location_id', $location_id, $queryReceiptPO);
                $queryReceiptPO = str_replace('$type', "purchase", $queryReceiptPO);
                $queryReceiptPO = str_replace('$status', "receipt_status", $queryReceiptPO);
                $queryReceiptPO = str_replace('$condition', "status = 'approve'", $queryReceiptPO);
                $queryReceiptPO = str_replace('$start_date', $startDate, $queryReceiptPO);
                $queryReceiptPO = str_replace('$end_date', "$endEnd", $queryReceiptPO);

                if (empty($view_all) || $view_all != "1") {
                    $queryReceiptPO = str_replace('$create_by', "created_by = '$user_id'", $queryReceiptPO);
                } else {
                    $queryReceiptPO = str_replace('$create_by', "1 = 1", $queryReceiptPO);
                }

                $data_receipt_po_status = DB::select($queryReceiptPO);
            }

            // data shipping sale order summary
            $shipping_so = [];
            if (File::exists($fileOrderSummary)) {
                $queryShippingSO = File::get($fileOrderSummary);

                $queryShippingSO = str_replace('$location_id', $location_id, $queryShippingSO);
                $queryShippingSO = str_replace('$type', "sell", $queryShippingSO);
                $queryShippingSO = str_replace('$status', "shipping_status", $queryShippingSO);
                $queryShippingSO = str_replace('$condition', "status = 'approve'", $queryShippingSO);
                $queryShippingSO = str_replace('$start_date', $startDate, $queryShippingSO);
                $queryShippingSO = str_replace('$end_date', "$endEnd", $queryShippingSO);

                if (empty($view_all) || $view_all != "1") {
                    $queryShippingSO = str_replace('$create_by', "created_by = '$user_id'", $queryShippingSO);
                } else {
                    $queryShippingSO = str_replace('$create_by', "1 = 1", $queryShippingSO);
                }

                $shipping_so = DB::select($queryShippingSO);
            }

            // data shipping sale order summary
            $shipping_po = [];
            if (File::exists($fileOrderSummary)) {
                $queryShippingPO = File::get($fileOrderSummary);

                $queryShippingPO = str_replace('$location_id', $location_id, $queryShippingPO);
                $queryShippingPO = str_replace('$type', "purchase", $queryShippingPO);
                $queryShippingPO = str_replace('$status', "shipping_status", $queryShippingPO);
                $queryShippingPO = str_replace('$condition', "status = 'approve'", $queryShippingPO);
                $queryShippingPO = str_replace('$start_date', $startDate, $queryShippingPO);
                $queryShippingPO = str_replace('$end_date', "$endEnd", $queryShippingPO);

                if (empty($view_all) || $view_all != "1") {
                    $queryShippingPO = str_replace('$create_by', "created_by = '$user_id'", $queryShippingPO);
                } else {
                    $queryShippingPO = str_replace('$create_by', "1 = 1", $queryShippingPO);
                }

                $shipping_po = DB::select($queryShippingPO);
            }

            // data stock
            $data_stock = [];

            if (File::exists($file_stock)) {
                $queryStock = File::get($file_stock);

                $data_stock = DB::select($queryStock);
            }

            $data = [
                'sell' => $data_sell,
                'debt_customer' => $data_dept_customer,
                'purchase' => $data_purchase,
                'debt_supplier' => $data_dept_supplier,
                'sale_order_summary' => $data_sale_order_summary,
                'purchase_order_summary' => $data_purchase_order_summary,
                'res_order_so' => $data_res_sale_order_summary,
                'res_order_po' => $data_res_purchase_order_summary,
                'receipt_so' => $data_receipt_so_status,
                'receipt_po' => $data_receipt_po_status,
                'shipping_so' => $shipping_so,
                'shipping_po' => $shipping_po,
                'data_stock' => $data_stock
            ];

            return $this->respondSuccess($data, null);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

}
