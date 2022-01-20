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

class PriceQuoteController extends Controller
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
            $user_id = Auth::guard('api')->user()->id;
            $stock_id = $request->stock_id;

            $purchase = Transaction::with([
                'contact:id,first_name,mobile,email,tax_number,type',
                'location:id,location_id,name,landmark',
                'business:id,name',
                'tax:id,name,amount',
                'sales_person:id,first_name,user_type',
                'accountants',
            ])
                ->join("contacts", "contacts.id", "=", "transactions.contact_id")
                ->join("users", "users.id", "=", "transactions.created_by")
                ->where('transactions.type', 'price_quote')
                ->where('transactions.location_id', $location_id)
                ->where('transactions.business_id', $business_id);

            if (!empty($request->id)) {
                $purchase->where('transactions.invoice_no',"LIKE", "%$request->id%");
            }

            //Add condition for created_by,used in sales representative sales report
            $view_all = null;

            if(!empty($request->view_all)) {
                $view_all = $request->view_all;
            }

            if(!empty($request->header('view-all'))) {
                $view_all = $request->header('view-all');
            }

            if (empty($view_all) || $view_all != "1") {
                $purchase->where('transactions.created_by', $user_id);
            }

            if (!empty($request->contact_name)) {
                $purchase->where('contacts.first_name',"LIKE", "%$request->contact_name%");
            }

            if (!empty($request->employee)) {
                $purchase->where('users.first_name',"LIKE", "%$request->employee%");
            }

            $contact_id = request()->get('contact_id');
            if (!empty($contact_id)) {
                $purchase->where('transactions.contact_id', $contact_id);
            }

            if (!empty(request()->start_date)) {
                $start = Carbon::createFromFormat('d/m/Y', request()->start_date);
                $purchase->where('transactions.transaction_date', '>=', $start);
            }

            if (!empty(request()->end_date)) {
                $end = Carbon::createFromFormat('d/m/Y', request()->end_date);
                $purchase->where('transactions.transaction_date', '<=', $end);
            }

            $status = request()->status;

            if (!empty($status) && $status != "all") {
                $purchase->where('transactions.status', $status);
            }

            $shipping_status = request()->shipping_status;

            if (!empty($shipping_status) && $shipping_status != "all") {
                $purchase->where('transactions.shipping_status', $shipping_status);
            }

            $res_order_status = request()->res_order_status;

            if (!empty($res_order_status) && $res_order_status != "all") {
                $purchase->where('transactions.res_order_status', $res_order_status);
            }

            $purchase->addSelect('transactions.res_order_status');


            $purchase->groupBy('transactions.id');
            $purchase->orderBy('transactions.created_at', "desc");
            $purchase->select("transactions.*");

            $data = $purchase->paginate($request->limit);

//            $summary = DB::table('transactions')
//                ->select(DB::raw('sum(final_total) as final_total, count(*) as total, status'))
//                ->where('business_id', $business_id)
//                ->where('location_id', $location_id)
//                ->where('type', "price_quote")
//                ->groupBy('status');
//
//
//            if (empty($view_all) || $view_all != "1") {
//                $summary->where('transactions.created_by', $user_id);
//            }
//
//            if (!empty(request()->start_date)) {
//                $start = Carbon::createFromFormat('d/m/Y', request()->start_date);
//                $summary->where('transactions.transaction_date', '>=', $start);
//            }
//
//            if (!empty(request()->end_date)) {
//                $end = Carbon::createFromFormat('d/m/Y', request()->end_date);
//                $summary->where('transactions.transaction_date', '<=', $end);
//            }
//
//            if (!empty($res_order_status) && count($res_order_status) > 0) {
//                $summary->whereIn('transactions.res_order_status', $res_order_status);
//            } else if (!empty($res_order_status) && $res_order_status != "all") {
//                $summary->where('transactions.res_order_status', $res_order_status);
//            }

            return $this->respondSuccess($data, null);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function listProduct(Request $request)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $location_id = $request->location_id;
            $stock_id = $request->stock_id;
            $user_id = Auth::guard('api')->user()->id;

            $purchase = TransactionSellLine::leftJoin('transactions', 'transactions.id', '=', 'transaction_sell_lines.transaction_id')
                ->leftJoin('contacts', 'contacts.id', '=', 'transactions.contact_id')
                ->leftJoin("users", "users.id", "=", "transactions.created_by")
                ->leftJoin('products', 'products.id', '=', 'transaction_sell_lines.product_id')
                ->leftJoin('units', 'products.unit_id', '=', 'units.id')
                ->leftJoin('business_locations', 'business_locations.id', '=', 'transactions.location_id')
                ->where('transactions.location_id', $location_id)
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'price_quote')
                ->select(
                    'transaction_sell_lines.*',
                    'transactions.invoice_no',
                    'transactions.stock_id',
                    'transactions.status',
                    'transactions.res_order_status',
                    'transactions.transaction_date as transaction_date',
                    'transactions.staff_note as staff_note',
                    'contacts.name as contact_name',
                    'contacts.tax_number as tax_number',
                    'products.name as product_name',
                    'units.actual_name as unit_name',
                    'products.sku as product_sku',
                    'users.first_name as created_by',
                    'business_locations.name as location_name',
                    \DB::raw('SUM(transaction_sell_lines.unit_price * transaction_sell_lines.quantity) as final_total')
                );

            if (!empty($request->id)) {
                $purchase->where('transactions.invoice_no',"LIKE", "%$request->id%");
            }

            //Add condition for created_by,used in sales representative sales report
            $view_all = null;

            if(!empty($request->view_all)) {
                $view_all = $request->view_all;
            }

            if(!empty($request->header('view-all'))) {
                $view_all = $request->header('view-all');
            }

            if (empty($view_all) || $view_all != "1") {
                $purchase->where('transactions.created_by', $user_id);
                return $this->respondWithError($purchase->toSql(), [], 500);
            }

            $contact_id = request()->get('contact_id');
            if (!empty($contact_id)) {
                $purchase->where('transactions.contact_id', $contact_id);
            }

            $contact_name = request()->get('contact_name');
            if (!empty($contact_name)) {
                $purchase->where('contacts.first_name',"LIKE", "%$contact_name%");
            }

            if (!empty($request->employee)) {
                $purchase->where('users.first_name',"LIKE", "%$request->employee%");
            }

            $status = request()->status;

            if (!empty($status) && $status != "all") {
                $purchase->where('transactions.status', $status);
            }

            $shipping_status = request()->shipping_status;

            if (!empty($shipping_status) && $shipping_status != "all") {
                $purchase->where('transactions.shipping_status', $shipping_status);
            }

            $res_order_status = request()->res_order_status;

            if (!empty($res_order_status) && $res_order_status != "all") {
                $purchase->where('transactions.res_order_status', $res_order_status);
            }

            $receipt_status = request()->receipt_status;

            if (!empty($receipt_status) && $receipt_status != "all") {
                $purchase->where('transactions.receipt_status', $receipt_status);
            }

            if (!empty(request()->start_date)) {
                $start = Carbon::createFromFormat('d/m/Y', request()->start_date);
                $purchase->whereDate('transactions.created_at', '>=', $start);
            }

            if (!empty(request()->end_date)) {
                $end = Carbon::createFromFormat('d/m/Y', request()->end_date);
                $purchase->whereDate('transactions.created_at', '<=', $end);

            }

            $payment_status = request()->payment_status;

            if (!empty($payment_status) && $payment_status != "all") {
                $purchase->where('transactions.payment_status', $payment_status);
            }

            $shipping_status = request()->shipping_status;

            if (!empty($shipping_status) && $shipping_status != "all") {
                $purchase->where('transactions.shipping_status', $shipping_status);
            }

            $purchase->groupBy('transaction_sell_lines.id');
            $purchase->orderBy('transaction_sell_lines.created_at', "asc");

            $data = $purchase->paginate($request->limit);

            return $this->respondSuccess($data, null);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function createInit($id)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $purchase = Transaction::where('business_id', $business_id)
                ->where('id', $id)
                ->with(
                    'contact',
                    'purchase_lines',
                    'purchase_lines.product:id,sku,name,contact_id,unit_id',
                    'purchase_lines.product.contact:id,first_name',
                    'purchase_lines.product.unit:id,actual_name',
                    'purchase_lines.variations',
                    'purchase_lines.variations.product_variation',
                    'purchase_lines.sub_unit',
                    'location:id,name,landmark,mobile',
                    'payment_lines',
                    'tax'
                )
                ->select([
                    'transactions.id',
                    'transactions.type',
                    'transactions.ref_no',
                    'transactions.transaction_date',
                    'transactions.invoice_no',
                    DB::raw('"ordered" as status'),
                    'transactions.stock_id',
                    'transactions.location_id',
                    'transactions.business_id',
                    'transactions.reject_by',
                    'transactions.updated_by',
                    'transactions.complete_by',
                    'transactions.approve_by',
                    'transactions.created_at',
                    'transactions.final_total',
                    'transactions.tax_amount',
                    'transactions.tax_id',
                    'transactions.staff_note',
                    'transactions.discount_amount',
                    'transactions.discount_type'
                ])
                ->firstOrFail();

            foreach ($purchase->purchase_lines as $key => $value) {
                if (!empty($value->sub_unit_id)) {
                    $formated_purchase_line = $this->productUtil->changePurchaseLineUnit($value, $business_id);
                    $purchase->purchase_lines[$key] = $formated_purchase_line;
                }
            }

            return $this->respondSuccess($purchase);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function create(Request $request)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $transaction_data = $request->only([
                'invoice_no',
                'ref_no',
                'status',
                'transaction_date',
                'location_id',
                'stock_id',
                'final_total',
                'staff_note',
                'contact_id',
                'modifier_price',
                'shipping_address',
                'service_custom_field_1',
                'service_custom_field_2',
            ]);

            $request->validate([
                'products',
                'final_total',
                'transaction_date',
                'contact_id'
            ]);

            if (isset($request->products) && count($request->products) === 0) {
                DB::rollBack();
                return $this->respondWithError("Vui lòng thêm ít nhất một đơn hàng", [], 500);
            }

            if (isset($request->final_total) && $request->final_total === 0) {
                DB::rollBack();
                return $this->respondWithError("Vui lòng thêm ít nhất một đơn hàng", [], 500);
            }

            $products = $request->input('products');
            $location_id = $request->location_id;
            $stock_id = $request->stock_Id;

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
            $enable_product_editing = 0;

            //unformat input values

            $transaction_data['business_id'] = $business_id;
            $transaction_data['created_by'] = $user_id;
            $transaction_data['type'] = 'price_quote';
            $transaction_data['payment_status'] = 'payment_pending';
            $transaction_data['status'] = 'ordered';
            if (!empty($request->status)) {
                $transaction_data['status'] = $request->status;
            }

            $transaction_data["transaction_date"] = Carbon::createFromFormat('d/m/Y', $transaction_data["transaction_date"]);

            //Update reference count
            $ref_count = $this->productUtil->setAndGetReferenceCount($transaction_data['type'], $business_id);

            //Generate reference number
            if (empty($transaction_data['invoice_no'])) {
                $transaction_data['invoice_no'] = $this->productUtil->generateReferenceNumber($transaction_data['type'], $ref_count, $business_id, "BG");
            }

            $discount = null;

            if (isset($transaction_data['discount_type']) && $transaction_data['discount_type']) {
                $discount = ['discount_type' => $transaction_data['discount_type'],
                    'discount_amount' => $transaction_data['discount_amount']
                ];

            }

            $tax_rate_id = null;
            if (isset($transaction_data['tax_rate_id']) && $transaction_data['tax_rate_id']) {
                $tax_rate_id = $transaction_data['tax_rate_id'];
            }

            $modifier_price = null;
            if (isset($transaction_data['modifier_price']) && $transaction_data['modifier_price']) {
                $modifier_price = $transaction_data['modifier_price'];
            }

            $invoice_total = $this->productUtil->calculateInvoiceTotal($products, $tax_rate_id, $discount, $modifier_price);

            $transaction = $this->transactionUtil->createSellTransaction($business_id, $transaction_data, $invoice_total, $user_id);

            $this->transactionUtil->createOrUpdateSellLines($transaction, $products, $location_id, $stock_id);

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Tạo đơn bán hàng",
                'subject_id' => $transaction->id
            ];

            $message = "Khởi tạo đơn bán hàng với giá trị là " . number_format($transaction_data["final_total"]) . "đ";
            Activity::history($message, "price_quote", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function detail($id)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $purchase = Transaction::where('business_id', $business_id)
                ->where('id', $id)
                ->with(
                    'contact:id,contact_id,first_name,name,mobile,email,type,address_line_1,created_at',
                    'sell_lines',
                    'sell_lines.product:id,sku,name,contact_id,unit_id',
                    'sell_lines.product.contact:id,first_name',
                    'sell_lines.product.unit:id,actual_name',
                    'sell_lines.variations',
                    'sell_lines.variations.product_variation',
                    'sell_lines.sub_unit',
                    'location:id,name,landmark,mobile',
                    'payment_lines',
                    'tax'
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
                    'transactions.reject_by',
                    'transactions.updated_by',
                    'transactions.complete_by',
                    'transactions.approve_by',
                    'transactions.created_at',
                    'transactions.final_total',
                    'transactions.tax_amount',
                    'transactions.tax_id',
                    'transactions.staff_note',
                    'transactions.discount_amount',
                    'transactions.discount_type',
                    'transactions.service_custom_field_1',
                    'transactions.service_custom_field_2',
                    'transactions.shipping_address',
                ])
                ->firstOrFail();

            foreach ($purchase->purchase_lines as $key => $value) {
                if (!empty($value->sub_unit_id)) {
                    $formated_purchase_line = $this->productUtil->changePurchaseLineUnit($value, $business_id);
                    $purchase->purchase_lines[$key] = $formated_purchase_line;
                }
            }

            return $this->respondSuccess($purchase);
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

            $dataUpdate = [
                'status' => $status
            ];

            $transaction = DB::table('transactions')
                ->whereIn('id', $ids)
                ->update($dataUpdate);

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

            if (isset($transaction->ref_no) && is_numeric($transaction->ref_no)) {
                $order = Transaction::findOrFail($transaction->ref_no);
                if ($order && $order->id) {
                    $order->status = "po_pending";
                    $order->save();
                }
            }

            $transaction->status = "pending";
            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành đang chờ duyệt";

            Activity::history($message, "price_quote", $dataLog);

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

            $transaction->status = "approve";

            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành đã được duyệt";

            Activity::history($message, "price_quote", $dataLog);

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

            $transaction->status = "reject";
            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành từ chối";

            Activity::history($message, "price_quote", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function accountantApprove(Request $request, $id)
    {
        try {
            if (!isset($id) || !$id) {
                $res = [
                    'status' => 'danger',
                    'msg' => "Không tìm thấy đơn hàng"
                ];

                return response()->json($res, 404);
            }

            $request->validate([
                'stock_id',
                'location_id'
            ]);

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $stock_id = $request->stock_id;

            $transaction = Transaction::findOrFail($id);
            if (!$transaction) {
                DB::rollBack();
                $message = "Lỗi khi thêm sản phẩm vào kho";

                return $this->respondWithError($message, [], 500);
            }

            $transaction->receipt_status = "approve";
            $result = $this->changeQuantityProduct($transaction->id, $stock_id, $user_id, $business_id);

            if (!$result) {
                DB::rollBack();
                $message = "Lỗi khi thêm sản phẩm vào kho";

                return $this->respondWithError($message, [], 500);
            }

            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Ké toán xác nhận đơn hàng",
                'subject_id' => $id
            ];

            $message = "Kế toán xác nhận đơn hàng hợp lệ";

            Activity::history($message, "price_quote", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function accountantReject(Request $request, $id)
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

            $transaction->receipt_status = "reject";
            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Ké toán xác nhận đơn hàng",
                'subject_id' => $id
            ];

            $message = "Kế toán xác nhận từ chối đơn hàng";

            Activity::history($message, "price_quote", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $input = $request->only([
                'invoice_no',
                'ref_no',
                'status',
                'transaction_date',
                'location_id',
                'stock_id',
                'final_total',
                'staff_note',
                'contact_id',
                'modifier_price',
                'shipping_address',
                'service_custom_field_1',
                'service_custom_field_2',
            ]);

            $request->validate([
                'products',
                'final_total',
                'transaction_date',
                'contact_id'
            ]);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $location_id = $request->location_id;
            $stock_id = $request->stock_Id;
            $products = $request->products;

            if (empty($products) || count($products) === 0) {
                DB::rollBack();
                $message = "Đơn hàng cần ít nhất 1 sản phẩm!";

                return $this->respondWithError($message, [], 500);
            }

            $transaction_before = Transaction::find($id);

            $status_before = $transaction_before->status;
            $rp_earned_before = $transaction_before->rp_earned;
            $rp_redeemed_before = $transaction_before->rp_redeemed;

            if ($transaction_before->is_direct_sale == 1) {
                $is_direct_sale = true;
            }

            $discount = null;

            if (isset($input['discount_type']) && $input['discount_type']) {
                $discount = ['discount_type' => $input['discount_type'],
                    'discount_amount' => $input['discount_amount']
                ];

            }

            $tax_rate_id = null;
            if (isset($input['tax_rate_id']) && $input['tax_rate_id']) {
                $tax_rate_id = $input['tax_rate_id'] ?? null;
            }

            $modifier_price = null;
            if (isset($input['modifier_price']) && $input['modifier_price']) {
                $modifier_price = $input['modifier_price'];
            }

            $invoice_total = $this->productUtil->calculateInvoiceTotal($products, $tax_rate_id, $discount, $modifier_price);

            $input['tax_id'] = $tax_rate_id;

            if (!empty($request->input('transaction_date'))) {
                $input["transaction_date"] = Carbon::createFromFormat('d/m/Y', $input["transaction_date"]);
            }

            $input['commission_agent'] = !empty($request->input('commission_agent')) ? $request->input('commission_agent') : null;

            if (isset($input['exchange_rate']) && $this->transactionUtil->num_uf($input['exchange_rate']) == 0) {
                $input['exchange_rate'] = 1;
            }

            //Customer group details
            $contact_id = $request->get('contact_id', null);
            $cg = $this->contactUtil->getCustomerGroup($business_id, $contact_id);

            $input['customer_group_id'] = (empty($cg) || empty($cg->id)) ? null : $cg->id;

            //set selling price group id
            $price_group_id = $request->has('price_group') ? $request->input('price_group') : null;

            $input['is_suspend'] = isset($input['is_suspend']) && 1 == $input['is_suspend'] ? 1 : 0;
            if ($input['is_suspend']) {
                $input['sale_note'] = !empty($input['additional_notes']) ? $input['additional_notes'] : null;
            }

            $input['selling_price_group_id'] = $price_group_id;

            $transaction = $this->transactionUtil->updateSellTransaction($id, $business_id, $input, $invoice_total, $user_id);

            //Update Sell lines
            $deleted_lines = $this->transactionUtil->createOrUpdateSellLines($transaction, $products, $location_id, $stock_id);

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật yêu cầu mua hàng mới",
                'subject_id' => $transaction->id
            ];

            $message = "Cập nhật đơn yêu cầu mua hàng thành công";
            Activity::history($message, "purchase_request", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function delete($id)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $transaction = Transaction::where('id', $id)
                ->where('business_id', $business_id)
                ->with(['purchase_lines'])
                ->first();

            $delete_purchase_lines = $transaction->purchase_lines;

            $transaction_status = $transaction->status;
            if ($transaction_status === 'approve') {
                DB::rollBack();
                $message = "không thể xóa được yêu cầu mua hàng đã được duyệt!";

                return $this->respondWithError($message, [], 500);
            }
            //Delete price_quote lines first
            $delete_purchase_line_ids = [];
            foreach ($delete_purchase_lines as $purchase_line) {
                $delete_purchase_line_ids[] = $purchase_line->id;
            }

            PurchaseLine::where('transaction_id', $transaction->id)
                ->whereIn('id', $delete_purchase_line_ids)
                ->delete();

            //Delete Transaction
            $transaction->delete();

            DB::commit();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Xóa đơn yêu cầu mua hàng mới",
                'subject_id' => $transaction->id
            ];

            $message = "Xóa đơn yêu cầu mua hàng thành công";
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

    private function changeQuantityProduct($transaction_id, $stock_id, $user_id, $business_id)
    {
        try {
            $purchase_line = PurchaseLine::where('transaction_id', $transaction_id)->get();

            if ($purchase_line && count($purchase_line) > 0) {
                foreach ($purchase_line as $product) {
                    $stock = StockProduct::where('stock_id', $stock_id)
                        ->where('product_id', $product->product_id)
                        ->increment('quantity', $product->quantity_sold);

                    if (!$stock) {
                        return false;
                    }

                    $dataLog = [
                        'created_by' => $user_id,
                        'business_id' => $business_id,
                        'log_name' => "Điều chỉnh số lượng",
                        'subject_id' => $product->product_id
                    ];

                    $message = "Điều chỉnh số lượng sản phẩm tăng thêm " . number_format($product->quantity_sold);

                    Activity::history($message, "product", $dataLog);
                }

                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

}
