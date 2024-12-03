<?php

namespace App\Http\Controllers\PurchaseRequest;

use App\AccountTransaction;
use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Contact;
use App\CustomerGroup;
use App\Helpers\Activity;
use App\Http\Controllers\Controller;
use App\Product;
use App\PurchaseLine;
use App\SellingPriceGroup;
use App\Stock;
use App\TaxRate;
use App\Transaction;
use App\User;
use App\Utils\BusinessUtil;

use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;

use App\Variation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * All Utils instance.
     *
     */
    protected $productUtil;
    protected $transactionUtil;
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param ProductUtils $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil, BusinessUtil $businessUtil, ModuleUtil $moduleUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;

        $this->dummyPaymentLine = ['method' => 'cash', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => ''];
    }

    public function list(Request $request)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $location_id = $request->location_id;
            $stock_id = $request->stock_id;
            $user_id = Auth::guard('api')->user()->id;


            $purchase = Transaction::with([
                'contact:id,first_name,mobile,email,tax_number,type',
                'sales_person:id,first_name,user_type',
                'pending_by:id,first_name,user_type',
                'sales_person:id,first_name,user_type',
                'approve_by:id,first_name,user_type',
                'reject_by:id,first_name,user_type',
                'complete_by:id,first_name,user_type',
                'children:id,ref_no,final_total,invoice_no,type,status',
                'parent:id,ref_no,final_total,invoice_no,type,status'
            ])
                ->leftJoin("contacts", "contacts.id", "=", "transactions.contact_id")
                ->leftJoin("users", "users.id", "=", "transactions.created_by")
                ->where('transactions.type', 'purchase_request')
                ->where('transactions.location_id', $location_id)
                ->where('transactions.business_id', $business_id);

            if (!empty($request->id)) {
                $purchase->where('transactions.invoice_no',"LIKE", "%$request->id%");
            }

            if (!empty(request()->supplier_id)) {
                $purchase->where('contacts.id', request()->supplier_id);
            }

            if (!empty(request()->location_id)) {
                $purchase->where('transactions.location_id', request()->location_id);
            }

            if (!empty(request()->status) && request()->status !== "all") {
                $purchase->where('transactions.status', request()->status);
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
                $start = Carbon::createFromFormat('d/m/Y', request()->start_date)->toDateString();
                $purchase->where('transactions.transaction_date', '>=', $start);
            }

            if (!empty(request()->end_date)) {
                $end = Carbon::createFromFormat('d/m/Y', request()->end_date);
                $purchase->where('transactions.transaction_date', '<=', $end);
            }

            $shipping_status = request()->shipping_status;

            if (!empty($shipping_status) && $shipping_status != "all") {
                $purchase->where('transactions.shipping_status', $shipping_status);
            }

            $res_order_status = request()->res_order_status;

            if (!empty($res_order_status) && $res_order_status != "all") {
                $purchase->where('transactions.res_order_status', $res_order_status);
            }

            $purchase->select("transactions.*");

            $purchase->orderBy('transactions.transaction_date', "desc");

            $data = $purchase->paginate($request->limit);

//            $summary = DB::table('transactions')
//                ->select(DB::raw('count(*) as total, status'))
//                ->where('business_id', $business_id)
//                ->where('stock_id', $stock_id)
//                ->where('type', "purchase_request")
//                ->whereDate('transactions.transaction_date', '>=', $start)
//                ->whereDate('transactions.transaction_date', '<=', $end)
//                ->groupBy('status')
//                ->get();

            return $this->respondSuccess($data, null);
        } catch (\Exception $e) {
            DB::rollBack();
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

            $purchase = PurchaseLine::leftJoin('transactions', 'transactions.id', '=', 'purchase_lines.transaction_id')
                ->leftJoin("users", "users.id", "=", "transactions.created_by")
                ->leftJoin('products', 'products.id', '=', 'purchase_lines.product_id')
                ->leftJoin('contacts', 'contacts.id', '=', 'products.contact_id')
                ->leftJoin('units', 'products.unit_id', '=', 'units.id')
                ->where('transactions.type', 'purchase_request')
                ->where('transactions.location_id', $location_id)
                ->where('transactions.business_id', $business_id)
                ->select(
                    'purchase_lines.*',
                    'transactions.invoice_no',
                    'transactions.stock_id',
                    'transactions.status',
                    'transactions.res_order_status',
                    'transactions.transaction_date as transaction_date',
                    'transactions.staff_note as staff_note',
                    'contacts.id as contact_id',
                    'contacts.name as contact_name',
                    'contacts.tax_number as tax_number',
                    'products.name as product_name',
                    'units.actual_name as unit_name',
                    \DB::raw('SUM(purchase_lines.purchase_price * purchase_lines.quantity) as final_total')
                );

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

            if (!empty($request->id)) {
                $purchase->where('transactions.invoice_no',"LIKE", "%$request->id%");
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

            $purchase->groupBy('purchase_lines.id');
            $purchase->orderBy('purchase_lines.created_at', "desc");

            $res = $purchase->paginate($request->limit);

            return $this->respondSuccess($res, null);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function createInit(Request $request, $id)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $order_id = $id;

            if (!empty($request->order_id)) {
                $order_id = $request->order_id;
            }

            $purchase = Transaction::where('business_id', $business_id)
                ->with(
                    'contact:id,contact_id,first_name,name,mobile,email,type,address_line_1,created_at',
                    'sell_lines',
                    'stock:id,stock_name,stock_type,location_id',
                    'stock.location:id,name,landmark',
                    'sell_lines.product:id,sku,barcode,name,contact_id,unit_id',
                    'sell_lines.product.contact:id,first_name',
                    'sell_lines.product.unit:id,actual_name',
                    'sell_lines.product.stock_products',
                    'sell_lines.variations',
                    'sell_lines.variations.product_variation',
                    'sell_lines.sub_unit',
                    'location:id,name,landmark,mobile',
                    'payment_lines',
                    'tax:id,name,amount',
                    'sales_person:id,username,first_name,last_name,contact_number,email',
                    'delivery_company:id,name,tracking'
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
                ])
                ->where('transactions.id', $order_id)
                ->firstOrFail();

            $purchase_lines = [];
            $purchase->ref_no = $id;

              foreach ($purchase->sell_lines as $key => $value) {
                if ($value->quantity_adjusted > 0) {
                    $temp = $value;
                    $temp["quantity"] = $value->quantity_adjusted;
                    array_push($purchase_lines, $temp);
                }
            }

            $purchase->purchase_lines = $purchase_lines;

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
                'staff_note'
            ]);

            $request->validate([
                'products',
                'final_total',
                'transaction_date'
            ]);

            if (isset($request->products) && count($request->products) === 0) {
                DB::rollBack();
                return $this->respondWithError("Vui lòng thêm ít nhất một đơn hàng", [], 500);
            }

            if (isset($request->final_total) && $request->final_total === 0) {
                DB::rollBack();
                return $this->respondWithError("Vui lòng thêm ít nhất một đơn hàng", [], 500);
            }

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
            $enable_product_editing = 0;

            //unformat input values

            $transaction_data['business_id'] = $business_id;
            $transaction_data['created_by'] = $user_id;
            $transaction_data['type'] = 'purchase_request';
            $transaction_data['payment_status'] = 'payment_pending';
            $transaction_data['status'] = 'ordered';
            if (!empty($request->status)) {
                $transaction_data['status'] = $request->status;
                if ($request->status === "approve") {
                    $transaction_data['approve_by'] = $user_id;
                }

                if ($request->status === "pending") {
                    $transaction_data['pending_by'] = $user_id;
                }

                if ($request->status === "reject") {
                    $transaction_data['reject_by'] = $user_id;
                }
            }

            $transaction_data["transaction_date"] = Carbon::createFromFormat('d/m/Y', $transaction_data["transaction_date"]);

            //Update reference count
            $ref_count = $this->productUtil->setAndGetReferenceCount($transaction_data['type'], $business_id);

            //Generate reference number
            if (empty($transaction_data['invoice_no'])) {
                $transaction_data['invoice_no'] = $this->productUtil->generateReferenceNumber($transaction_data['type'], $ref_count, $business_id, "YCMH");
            }

            $transaction = Transaction::create($transaction_data);

            $purchase = $request->input('products');

            $this->productUtil->createOrUpdatePurchaseLines($transaction, $purchase, $currency_details, $enable_product_editing);

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Tạo yêu cầu mua hàng mới",
                'subject_id' => $transaction->id
            ];

            $message = "Khởi tạo đơn yêu cầu mua hàng với giá trị đơn là" . number_format($transaction_data["final_total"]) . "đ";
            Activity::history($message, "purchase_request", $dataLog);

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
                    'contact',
                    'stock:id,stock_name,stock_type,location_id',
                    'stock.location:id,name,landmark',
                    'purchase_lines',
                    'purchase_lines.product:id,sku,barcode,name,contact_id,unit_id,enable_sr_no',
                    'purchase_lines.product.contact:id,first_name',
                    'purchase_lines.product.unit:id,actual_name',
                    'purchase_lines.product.stock_products:id,product_id,stock_id,purchase_price,unit_price,quantity,status',
                    'purchase_lines.variations',
                    'purchase_lines.variations.product_variation',
                    'purchase_lines.sub_unit',
                    'location:id,name,landmark,mobile',
                    'payment_lines',
                    'tax',
                    'sales_person:id,username,first_name,last_name,contact_number,email',
                    'pending_by:id,first_name,user_type',
                    'approve_by:id,first_name,user_type',
                    'reject_by:id,first_name,user_type',
                    'complete_by:id,first_name,user_type',
                    'children:id,ref_no,final_total,invoice_no,type,status',
                    'parent:id,ref_no,final_total,invoice_no,type,status'
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
                    'transactions.pending_by',
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
                ])
                ->firstOrFail();

            return $this->respondSuccess($purchase);
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

            $status = $request->status;

            $dataUpdate = [
                'status' => "pending",
                "pending_by" => $user_id
            ];

            $transaction = DB::table('transactions')
                ->where('id', $id)
                ->update($dataUpdate);

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành " . $status;

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

            $status = $request->status;

            $dataUpdate = [
                'status' => "approve",
                "approve_by" => $user_id
            ];

            $transaction = DB::table('transactions')
                ->where('id', $id)
                ->update($dataUpdate);

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành " . $status;

            Activity::history($message, "purchase_request", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function unapprove(Request $request, $id)
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

            $transaction->status = "ordered";
            $transaction->res_order_status = "";
            $transaction->shipping_status = "";
            $transaction->receipt_status = "";
            $transaction->payment_status = "payment_pending";
            $transaction->approve_by = $user_id;

            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành đã được hủy duyệt";

            Activity::history($message, "sell", $dataLog);

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

            $status = $request->status;

            $dataUpdate = [
                'status' => "reject",
                "reject_by" => $user_id
            ];

            $transaction = DB::table('transactions')
                ->where('id', $id)
                ->update($dataUpdate);

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành " . $status;

            Activity::history($message, "purchase_request", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction);
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

            if ($status === "approve") {
                $dataUpdate["approve_by"] = $user_id;
            }

            if ($status === "reject") {
                $dataUpdate["reject_by"] = $user_id;
            }

            if ($status === "pending_by") {
                $dataUpdate["pending_by"] = $user_id;
            }

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

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            //Validate document size
            $request->validate([
                'products',
                'final_total',
                'transaction_date'
            ]);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $transaction = Transaction::findOrFail($id);
            $before_status = $transaction->status;
            $enable_product_editing = 0;

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);

            $update_data = $request->only([
                'invoice_no',
                'ref_no',
                'status',
                'transaction_date',
                'location_id',
                'stock_id',
                'final_total',
                'staff_note'
            ]);

            $update_data["updated_by"] = $user_id;

            $update_data["transaction_date"] = Carbon::createFromFormat('d/m/Y', $update_data["transaction_date"]);

            if (!empty($request->status)) {
                $update_data['status'] = $request->status;
                if ($request->status === "approve") {
                    $update_data['approve_by'] = $user_id;
                }

                if ($request->status === "pending") {
                    $update_data['pending_by'] = $user_id;
                }

                if ($request->status === "reject") {
                    $update_data['reject_by'] = $user_id;
                }
            }

            //update transaction
            $transaction->update($update_data);

            //Update transaction payment status
            $this->transactionUtil->updatePaymentStatus($transaction->id);

            $purchase = $request->input('products');

            $delete_purchase_lines = $this->productUtil->createOrUpdatePurchaseLines($transaction, $purchase, $currency_details, $enable_product_editing, $before_status);

            //Update mapping of purchase & Sell.
            $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase($before_status, $transaction, $delete_purchase_lines);

            //Adjust stock over selling if found
            $this->productUtil->adjustStockOverSelling($transaction);

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
            //Delete purchase lines first
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
}
