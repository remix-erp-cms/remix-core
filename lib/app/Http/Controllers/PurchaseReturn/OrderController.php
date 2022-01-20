<?php

namespace App\Http\Controllers\PurchaseReturn;

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

class OrderController extends Controller
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
            $user_id = Auth::guard('api')->user()->id;

            $purchase = Transaction::with([
                'contact:id,first_name,mobile,email,tax_number,type',
                'location:id,location_id,name,landmark',
                'business:id,name',
                'tax:id,name,amount',
                'sales_person:id,first_name,user_type',
                'pending_by:id,first_name,user_type',
                'approve_by:id,first_name,user_type',
                'reject_by:id,first_name,user_type',
                'complete_by:id,first_name,user_type',
                'accountants',
            ])
                ->leftJoin("contacts", "contacts.id", "=", "transactions.contact_id")
                ->leftJoin("users", "users.id", "=", "transactions.created_by")
                ->where('transactions.type', 'purchase_return')
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

            $receipt_status = request()->receipt_status;

            if (!empty($receipt_status) && $receipt_status != "all") {
                $purchase->where('transactions.receipt_status', $receipt_status);
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

            if (!empty($res_order_status) && count($res_order_status) > 0) {
                $purchase->whereIn('transactions.res_order_status', $res_order_status);
            } else if (!empty($res_order_status) && $res_order_status != "all") {
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
//                ->where('type', 'purchase_return')
//                ->groupBy('status');
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
            $transaction_data['type'] = 'purchase_return';
            $transaction_data['payment_status'] = 'payment_pending';
            $transaction_data['status'] = 'ordered';
            if (!empty($request->status)) {
                $transaction_data['status'] = $request->status;

                if ($request->status === "approve") {
                    $transaction_data['approve_by'] = $user_id;
                    $transaction_data['res_order_status'] = "request";
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
                $transaction_data['invoice_no'] = $this->productUtil->generateReferenceNumber($transaction_data['type'], $ref_count, $business_id, "POR");
            }

            $discount = null;

            if (isset($transaction_data['discount_type']) && $transaction_data['discount_type']) {
                $discount = ['discount_type' => $transaction_data['discount_type'],
                    'discount_amount' => $transaction_data['discount_amount']
                ];

            }

            $tax_rate_id = null;
            if (isset($transaction_data['tax_rate_id']) && $transaction_data['tax_rate_id']) {
                $tax_rate_id = $transaction_data['tax_rate_id'] ?? null;
            }

            $invoice_total = $this->productUtil->calculatePurchaseReturnTotal($products, $tax_rate_id, $discount);

            $transaction = $this->transactionUtil->createSellTransaction($business_id, $transaction_data, $invoice_total, $user_id);

            $this->transactionUtil->createOrUpdateSellLines($transaction, $products, $location_id, $stock_id);

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Tạo đơn trả hàng mua",
                'subject_id' => $transaction->id
            ];

            $message = "Khởi tạo đơn trả hàng mua với giá trị là " . number_format($transaction_data["final_total"]) . "đ";
            Activity::history($message, "sell", $dataLog);

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
                    'approve_by:id,first_name,user_type',
                    'reject_by:id,first_name,user_type',
                    'complete_by:id,first_name,user_type',
                    'delivery_company:id,name,tracking'
                )
                ->select([
                    'transactions.id',
                    'transactions.type',
                    'transactions.sub_type',
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
                    'transactions.payment_method',
                    'transactions.service_custom_field_1',
                    'transactions.service_custom_field_2',
                    'transactions.service_custom_field_3',
                    'transactions.delivery_company_id',
                    'transactions.shipping_address',
                    'transactions.shipping_type',
                    'transactions.shipping_charges',
                ])
                ->firstOrFail();

            foreach ($purchase->sell_lines as $key => $value) {
                if (!empty($value->sub_unit_id)) {
                    $formated_purchase_line = $this->productUtil->changePurchaseLineUnit($value, $business_id);
                    $purchase->sell_lines[$key] = $formated_purchase_line;
                }
            }
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

            $transaction = Transaction::findOrFail($id);

            $transaction->status = "pending";
            $transaction->pendign_by = $user_id;

            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành đang chờ duyệt";

            Activity::history($message, 'purchase_return', $dataLog);

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
                $message = "Không tìm thấy đơn hàng\"";

                return $this->respondWithError($message, [], 500);
            }

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $transaction = Transaction::findOrFail($id);

            $transaction->status = "approve";
            $transaction->res_order_status = "request";
            $transaction->approve_by = $user_id;

            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành đã được duyệt";

            Activity::history($message, 'purchase_return', $dataLog);

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
                $message = "Không tìm thấy đơn hàng\"";

                return $this->respondWithError($message, [], 500);
            }

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $transaction = Transaction::findOrFail($id);

            if (isset($transaction->ref_no) && is_numeric($transaction->ref_no)) {
                $order = Transaction::findOrFail($transaction->ref_no);
                if ($order && $order->id) {
                    $order->status = "po_reject";
                    $order->save();
                }
            }

            $transaction->status = "reject";
            $transaction->reject_by = $user_id;
            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành từ chối";

            Activity::history($message, 'purchase_return', $dataLog);

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

            $transaction = [];

            if ($status === "approve") {
                foreach ($ids as $id) {
                    $transaction = Transaction::findOrFail($id);

                    $transaction->approve_by = $user_id;
                    $transaction->status = "approve";
                    $transaction->res_order_status = "request";
                    $transaction->save();
                }
            } else {
                $dataUpdate = [
                    'status' => $status
                ];

                if ($status === "pending") {
                    $dataUpdate["pending_by"] = $user_id;
                }

                if ($status === "reject") {
                    $dataUpdate["reject_by"] = $user_id;
                }

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
                $message = "Không tim thấy đơn hàng";

                return $this->respondWithError($message, [], 500);
            }

            $transaction->receipt_status = "approve";
            $transaction->shipping_status = "created";
            $transaction->complete_by = $user_id;
            $result = $this->changeQuantityProduct($transaction->id, $stock_id, $user_id, $business_id);

            if(!$result) {
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

            Activity::history($message, 'purchase_return', $dataLog);

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
            $transaction->complete_by = $user_id;

            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Ké toán xác nhận đơn hàng",
                'subject_id' => $id
            ];

            $message = "Kế toán xác nhận từ chối đơn hàng";

            Activity::history($message, 'purchase_return', $dataLog);

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

            $invoice_total = $this->productUtil->calculatePurchaseReturnTotal($products, $tax_rate_id, $discount);

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

            if (!empty($request->status)) {
                $input['status'] = $request->status;
                if ($request->status === "approve") {
                    $input['res_order_status'] = "request";
                    $input['approve_by'] = $user_id;
                }

                if ($request->status === "pending") {
                    $input['pending_by'] = $user_id;
                }

                if ($request->status === "reject") {
                    $input['reject_by'] = $user_id;
                }
            }

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
                $message = "không thể xóa được yêu cầu trả hàng đã được duyệt!";

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
                'log_name' => "Xóa đơn yêu cầu trả hàng mới",
                'subject_id' => $transaction->id
            ];

            $message = "Xóa đơn yêu cầu trả hàng thành công";
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
            $purchase_line = TransactionSellLine::where('transaction_id', $transaction_id)->get();

            if ($purchase_line && count($purchase_line) > 0) {
                foreach ($purchase_line as $product) {
                    $stock = StockProduct::where('stock_id', $stock_id)
                        ->where('product_id', $product->product_id)
                        ->increment('quantity', $product->quantity_sold * -1);

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
