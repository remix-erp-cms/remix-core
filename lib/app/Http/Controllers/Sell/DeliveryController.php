<?php

namespace App\Http\Controllers\Sell;

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

class DeliveryController extends Controller
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

    public function list(Request $request)
    {
        try {

            $business_id = Auth::guard('api')->user()->business_id;
            $location_id = $request->location_id;
            $stock_id = $request->stock_id;
            $type = request()->type;
            $user_id = Auth::guard('api')->user()->id;

            $transaction = Transaction::with([
                'contact:id,first_name,mobile,email,tax_number,type',
                'location:id,location_id,name,landmark',
                'business:id,name',
                'tax:id,name,amount',
                'sales_person:id,first_name,user_type',
                'accountants',
            ])
                ->leftJoin("contacts", "contacts.id", "=", "transactions.contact_id")
                ->leftJoin("users", "users.id", "=", "transactions.created_by")
                ->where('transactions.status', "approve")
                ->whereIn('transactions.res_order_status', ["deficient", "enough"])
                ->whereIn('transactions.type', ["sell", "purchase_return"])
                ->where('transactions.location_id', $location_id)
                ->where('transactions.business_id', $business_id);

            //Add condition for created_by,used in sales representative sales report
            if (!empty($request->id)) {
                $transaction->where('transactions.invoice_no',"LIKE", "%$request->id%");
            }

            $view_all = null;

            if(!empty($request->view_all)) {
                $view_all = $request->view_all;
            }

            if(!empty($request->header('view-all'))) {
                $view_all = $request->header('view-all');
            }

            if (empty($view_all) || $view_all != "1") {
                $transaction->where('transactions.created_by', $user_id);
            }

            if (!empty($request->contact_name)) {
                $transaction->where('contacts.first_name',"LIKE", "%$request->contact_name%");
            }

            if (!empty($request->employee)) {
                $transaction->where('users.first_name',"LIKE", "%$request->employee%");
            }

            if (!empty($type)) {
                $transaction->where('transactions.type', $type);
            }

            $contact_id = request()->get('contact_id');
            if (!empty($contact_id)) {
                $transaction->where('transactions.contact_id', $contact_id);
            }

            if (!empty(request()->start_date)) {
                $start = Carbon::createFromFormat('d/m/Y', request()->start_date);
                $transaction->where('transactions.transaction_date', '>=', $start);
            }

            if (!empty(request()->end_date)) {
                $end = Carbon::createFromFormat('d/m/Y', request()->end_date);
                $transaction->where('transactions.transaction_date', '<=', $end);
            }

            $status = request()->status;

            if (!empty($status) && $status != "all") {
                $transaction->where('transactions.shipping_status', $status);
            }

            $shipping_status = request()->shipping_status;

            if (!empty($shipping_status) && $shipping_status != "all") {
                $transaction->where('transactions.shipping_status', $shipping_status);
            }

            $res_order_status = request()->res_order_status;

            if (!empty($res_order_status) && $res_order_status != "all") {
                $transaction->where('transactions.res_order_status', $res_order_status);
            }


            $transaction->groupBy('transactions.id');
            $transaction->orderBy('transactions.updated_at', "desc");
            $transaction->select("transactions.*");

            $data = $transaction->paginate($request->limit);

//            $query = DB::table('transactions')
//                ->select(DB::raw('sum(final_total) as final_total, count(*) as total, status'))
//                ->where('business_id', $business_id)
//                ->where('location_id', $location_id)
//                ->where('transactions.status', "approve")
//                ->where('transactions.receipt_status', "approve")
//                ->whereIn('transactions.type', ["sell", "purchase_return"])
//                ->groupBy('shipping_status');
//
//            if (!empty($type)) {
//                $query->where('transactions.type', $type);
//            }
//
//            if (empty($view_all) || $view_all != "1") {
//                $query->where('transactions.created_by', $user_id);
//            }
//
//
//            if (!empty(request()->start_date)) {
//                $start = Carbon::createFromFormat('d/m/Y', request()->start_date);
//                $query->where('transactions.transaction_date', '>=', $start);
//            }
//
//            if (!empty(request()->end_date)) {
//                $end = Carbon::createFromFormat('d/m/Y', request()->end_date);
//                $query->where('transactions.transaction_date', '<=', $end);
//            }
//
//            $summary = $query->get();

            return $this->respondSuccess($data, null);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function detail($id)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $type = request()->type;

            $purchase = Transaction::where('business_id', $business_id)
                ->where('id', $id)
                ->with(
                    'contact:id,contact_id,first_name,name,mobile,email,type,address_line_1,created_at',
                    'sell_lines',
                    'stock:id,stock_name,stock_type,location_id',
                    'stock.location:id,name,landmark',
                    'sell_lines.product:id,sku,name,contact_id,unit_id',
                    'sell_lines.product.contact:id,first_name',
                    'sell_lines.product.unit:id,actual_name',
                    'sell_lines.variations',
                    'sell_lines.variations.product_variation',
                    'sell_lines.sub_unit',
                    'location:id,name,landmark,mobile',
                    'payment_lines',
                    'tax',
                    'sales_person:id,username,first_name,last_name,contact_number,email'
                )
                ->select([
                    'transactions.id',
                    'transactions.type',
                    'transactions.ref_no',
                    'transactions.transaction_date',
                    'transactions.invoice_no',
                    'transactions.status',
                    'transactions.shipping_status',
                    'transactions.res_order_status',
                    'transactions.receipt_status',
                    'transactions.payment_status',
                    'transactions.stock_id',
                    'transactions.contact_id',
                    'transactions.location_id',
                    'transactions.business_id',
                    'transactions.created_by',
                    'transactions.reject_by',
                    'transactions.updated_by',
                    'transactions.complete_by',
                    'transactions.approve_by',
                    'transactions.created_at',
                    'transactions.total_before_tax',
                    'transactions.final_total',
                    'transactions.tax_amount',
                    'transactions.tax_id',
                    'transactions.staff_note',
                    'transactions.discount_amount',
                    'transactions.discount_type',
                    'transactions.service_custom_field_1',
                    'transactions.service_custom_field_2',
                    'transactions.shipping_address',
                ]);

            $data = $purchase->firstOrFail();

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function changeStatus(Request $request)
    {
        try {
            $ids = $request->ids;

            if (!isset($ids) || count($ids) === 0) {
                $res = [
                    'status' => 'danger',
                    'msg' => "Không tìm thấy đơn hàng"
                ];

                return response()->json($res, 404);
            }

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $status = $request->status;

            $transaction = [];

            if ($status === "complete") {
                foreach ($ids as $id) {
                    $transaction = Transaction::findOrFail($id);

                    $transaction->shipping_status = "complete";
                    $transaction->save();
                }
            } else {
                $dataUpdate = [
                    'shipping_status' => $status
                ];
                $transaction = DB::table('transactions')
                    ->whereIn('id', $ids)
                    ->update($dataUpdate);
            }

            DB::commit();


            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function pending(Request $request, $id)
    {
        try {
            if (!isset($id) || !$id) {
                $res = [
                    'status' => 'danger',
                    'msg' => "Không tìm thấy đơn hàng"
                ];

                return response()->json($res, 404);
            }

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $transaction = Transaction::findOrFail($id);

            $transaction->shipping_status = "pending";
            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành đang chờ duyệt";

            Activity::history($message, "purchase_request", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        try {
            if (!isset($id) || !$id) {
                $res = [
                    'status' => 'danger',
                    'msg' => "Không tìm thấy đơn hàng"
                ];

                return response()->json($res, 404);
            }

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $transaction = Transaction::findOrFail($id);

            $transaction->shipping_status = "complete";
            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành đã được duyệt";

            Activity::history($message, "purchase_request", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            if (!isset($id) || !$id) {
                $res = [
                    'status' => 'danger',
                    'msg' => "Không tìm thấy đơn hàng"
                ];

                return response()->json($res, 404);
            }

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $transaction = Transaction::findOrFail($id);

            $transaction->shipping_status = "return";

            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành từ chối";

            Activity::history($message, "purchase_request", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function print($id)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $purchase = Transaction::where('business_id', $business_id)
                ->where('id', $id)
                ->with(
                    'contact',
                    'purchase_lines',
                    'purchase_lines.product',
                    'purchase_lines.variations',
                    'purchase_lines.variations.product_variation',
                    'location',
                    'payment_lines'
                )
                ->first();

            return $this->respondSuccess($purchase);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }
}
