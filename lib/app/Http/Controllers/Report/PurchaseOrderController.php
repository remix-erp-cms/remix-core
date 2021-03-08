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

class PurchaseOrderController extends Controller
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
            $location_id =  $request->location_id;
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
                    $startDate = Carbon::createFromFormat('d/m/Y', request()->start_date) ;
                }

                if (!empty(request()->end_date)) {
                    $endEnd = Carbon::createFromFormat('d/m/Y', request()->end_date) ;
                }

                $fileSellOrder = __DIR__ . '/sql/order_by_day.sql';

                if(File::exists($fileSellOrder)) {
                    $querySellOrder = File::get($fileSellOrder);

                    $querySellOrder = str_replace('$business_id', $business_id, $querySellOrder);
                    $querySellOrder = str_replace('$location_id', $location_id, $querySellOrder);
                    $querySellOrder = str_replace('$type', "purchase", $querySellOrder);
                    $querySellOrder = str_replace('$start_date', $startDate, $querySellOrder);
                    $querySellOrder = str_replace('$end_date', "$endEnd", $querySellOrder);

                    $data = DB::select($querySellOrder);
                }
            }

            if ($type_group === "month") {
                $startDate = Carbon::now()->startOfYear();
                $endEnd = Carbon::now()->endOfYear();

                if (!empty(request()->start_date)) {
                    $startDate = Carbon::createFromFormat('d/m/Y', request()->start_date) ;
                }

                if (!empty(request()->end_date)) {
                    $endEnd = Carbon::createFromFormat('d/m/Y', request()->end_date) ;
                }

                $fileSellOrder = __DIR__ . '/sql/order_by_month.sql';

                if(File::exists($fileSellOrder)) {
                    $querySellOrder = File::get($fileSellOrder);

                    $querySellOrder = str_replace('$business_id', $business_id, $querySellOrder);
                    $querySellOrder = str_replace('$location_id', $location_id, $querySellOrder);
                    $querySellOrder = str_replace('$type', "purchase", $querySellOrder);
                    $querySellOrder = str_replace('$start_date', $startDate, $querySellOrder);
                    $querySellOrder = str_replace('$end_date', "$endEnd", $querySellOrder);

                    $data = DB::select($querySellOrder);
                }
            }

            if ($type_group === "year") {
                $fileSellOrder = __DIR__ . '/sql/order_by_year.sql';

                if(File::exists($fileSellOrder)) {
                    $querySellOrder = File::get($fileSellOrder);

                    $querySellOrder = str_replace('$business_id', $business_id, $querySellOrder);
                    $querySellOrder = str_replace('$location_id', $location_id, $querySellOrder);
                    $querySellOrder = str_replace('$type', "purchase", $querySellOrder);
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
            $business_id = Auth::guard('api')->user()->business_id;
            $location_id =  $request->location_id;
            $stock_id = $request->stock_id;

            $type_group = "month";

            if (!empty(request()->type_group)) {
                $type_group = request()->type_group;
            }

            $data = [];

            $startDate = Carbon::now()->startOfMonth();
            $endEnd = Carbon::now()->endOfMonth();

            if (!empty(request()->start_date)) {
                $startDate = Carbon::createFromFormat('d/m/Y', request()->start_date) ;
            }

            if (!empty(request()->end_date)) {
                $endEnd = Carbon::createFromFormat('d/m/Y', request()->end_date) ;
            }

            $fileSellOrder = __DIR__ . '/sql/order_summary.sql';

            if(File::exists($fileSellOrder)) {
                $querySellOrder = File::get($fileSellOrder);

                $querySellOrder = str_replace('$business_id', $business_id, $querySellOrder);
                $querySellOrder = str_replace('$location_id', $location_id, $querySellOrder);
                $querySellOrder = str_replace('$type', "purchase", $querySellOrder);
                $querySellOrder = str_replace('$start_date', $startDate, $querySellOrder);
                $querySellOrder = str_replace('$end_date', "$endEnd", $querySellOrder);

                $data = DB::select($querySellOrder);
            }

            return $this->respondSuccess($data, null);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

}
