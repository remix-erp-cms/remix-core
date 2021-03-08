<?php

namespace App\Http\Controllers\Common;

use App\Account;
use App\Accountant;
use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Contact;
use App\CustomerGroup;
use App\Http\Controllers\Controller;
use App\InvoiceBill;
use App\InvoiceScheme;
use App\PayBill;
use App\Product;
use App\SellingPriceGroup;
use App\Stock;
use App\StockBill;
use App\StockProduct;
use App\TaxRate;
use App\Transaction;
use App\TransactionPayment;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use \Carbon\Carbon;

class CommonController extends Controller
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

    public function locations(Request $request)
    {
        try {

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $user = Auth::guard('api')->user();
            $permitted_locations = [];

            $all_locations = BusinessLocation::where('business_id', $business_id)
                ->paginate($request->limit);

//            if ($user->can('access_all_locations')) {
//                $permitted_locations = $all_locations;
//            } else {
//                foreach ($all_locations as $location) {
//                    if ($user->can('location.' . $location->id)) {
//                        $permitted_locations[] = $location->id;
//                    }
//                }
//
//                if (count($permitted_locations) > 0) {
//                    $permitted_locations = BusinessLocation::where('business_id', $business_id)
//                        ->whereIn('id', $permitted_locations)
//                        ->paginate($request->limit);
//                }
//            }
//
//            $locations = User::where('business_id', $business_id)
//                ->select([
//                    'id',
//                    'location_id',
//                    'name',
//                    'email',
//                    'mobile',
//                ])
//                ->paginate(10);

            return $this->respondSuccess($all_locations);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }
}
