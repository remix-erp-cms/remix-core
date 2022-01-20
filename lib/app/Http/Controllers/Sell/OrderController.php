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
use Excel;

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
            $user_id = Auth::guard('api')->user()->id;

            $location_id = $request->location_id;
            $stock_id = $request->stock_id;

            $transaction = Transaction::with([
                'contact:id,first_name,mobile,email,tax_number,type',
                'location:id,location_id,name,landmark',
                'business:id,name',
                'tax:id,name,amount',
                'sales_person:id,first_name,user_type',
                'approve_by:id,first_name,user_type',
                'reject_by:id,first_name,user_type',
                'pending_by:id,first_name,user_type',
                'complete_by:id,first_name,user_type',
                'accountants',
                'children:id,ref_no,final_total,invoice_no,type,status'
            ])
                ->leftJoin("contacts", "contacts.id", "=", "transactions.contact_id")
                ->leftJoin("users", "users.id", "=", "transactions.created_by")
                ->where('transactions.type', 'sell')
                ->where('transactions.location_id', $location_id)
                ->where('transactions.business_id', $business_id);


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

            if (!empty($request->id)) {
                $transaction->where('transactions.invoice_no',"LIKE", "%$request->id%");
            }

            if (!empty($request->contact_name)) {
                $transaction->where('contacts.first_name',"LIKE", "%$request->contact_name%");
            }

            if (!empty($request->employee)) {
                $transaction->where('users.first_name',"LIKE", "%$request->employee%");
            }

            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (!empty($created_by)) {
                    $transaction->where('transactions.created_by', $created_by);
                }
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
                $transaction->where('transactions.status', $status);
            }

            $shipping_status = request()->shipping_status;

            if (!empty($shipping_status) && $shipping_status != "all") {
                $transaction->where('transactions.shipping_status', $shipping_status);
            }

            $res_order_status = request()->res_order_status;

            if (!empty($res_order_status) && is_array($res_order_status) && count($res_order_status) > 0) {
                $transaction->whereIn('transactions.res_order_status', $res_order_status);
            } else if (!empty($res_order_status) && $res_order_status != "all") {
                $transaction->where('transactions.res_order_status', $res_order_status);
            }

            $receipt_status = request()->receipt_status;

            if (!empty($receipt_status) && $receipt_status != "all") {
                $transaction->where('transactions.receipt_status', $receipt_status);
            }

            $transaction->addSelect('transactions.res_order_status');

            $transaction->groupBy('transactions.id');
            $transaction->orderBy('transactions.created_at', "desc");
            $transaction->select("transactions.*");

            $data = $transaction->paginate($request->limit);
//
//            $summary = DB::table('transactions')
//                ->select(DB::raw('sum(final_total) as final_total, count(*) as total, status'))
//                ->where('business_id', $business_id)
//                ->where('location_id', $location_id)
//                ->where('type', "sell")
//                ->whereDate('transactions.transaction_date', '>=', $start)
//                ->whereDate('transactions.transaction_date', '<=', $end)
//                ->groupBy('status');
//
//            if (empty($view_all) || $view_all != "1") {
//                $summary->where('transactions.created_by', $user_id);
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
                ->where('transactions.type', 'sell')
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

            $transaction = Transaction::where('business_id', $business_id)
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

            return $this->respondSuccess($transaction);
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

            if ($request->created_by) {
                $user_id = $request->created_by;
            }

            $transaction_data = $request->only([
                'sub_type',
                'invoice_no',
                'ref_no',
                'status',
                'transaction_date',
                'location_id',
                'stock_id',
                'final_total',
                'staff_note',
                'contact_id',
                'tax_rate_id',
                'delivery_company_id',
                'shipping_type',
                'shipping_address',
                'shipping_charges',
                'discount_type',
                'discount_amount',
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
            $stock_id = $request->stock_id;
            $isDirect = false;

            if (isset($request->status)) {
                $isDirect = $request->isDirect;
            }

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
            $enable_product_editing = 0;

            //unformat input values

            $transaction_data['business_id'] = $business_id;
            $transaction_data['created_by'] = $user_id;
            $transaction_data['type'] = 'sell';
            $transaction_data['payment_status'] = 'payment_pending';
            $transaction_data['status'] = 'ordered';
            if (!empty($request->status)) {
                $transaction_data['status'] = $request->status;

                if ($request->status === "approve") {
                    $transaction_data['approve_by'] = $user_id;
                    if ($isDirect === true) {
                        $transaction_data['shipping_status'] = "created";
                        $transaction_data['payment_status'] = "payment_paid";
                    } else {
                        $transaction_data['res_order_status'] = "request";
                    }
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
                $transaction_data['invoice_no'] = $this->productUtil->generateReferenceNumber($transaction_data['type'], $ref_count, $business_id, "SO");
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

            $shippingCharge = 0;
            if (isset($transaction_data["shipping_charges"]) && $transaction_data["shipping_charges"]) {
                $shippingCharge = $transaction_data["shipping_charges"];
            }

            $invoice_total = $this->productUtil->calculateInvoiceTotal($products, $tax_rate_id, $discount, null, $shippingCharge);

            $transaction = $this->transactionUtil->createSellTransaction($business_id, $transaction_data, $invoice_total, $user_id);

            $this->transactionUtil->createOrUpdateSellLines($transaction, $products, $location_id, $stock_id, null, [], true, $isDirect);

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Tạo đơn bán hàng",
                'subject_id' => $transaction->id
            ];

            $message = "Khởi tạo đơn bán hàng với giá trị là " . number_format($transaction_data["final_total"]) . "đ";
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

            $transaction = Transaction::where('business_id', $business_id)
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
                    'delivery_company:id,name,tracking',
                    'children:id,ref_no,invoice_no,type'
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
                    'transactions.final_profit',
                    'transactions.service_custom_field_1',
                    'transactions.service_custom_field_2',
                    'transactions.service_custom_field_3',
                    'transactions.delivery_company_id',
                    'transactions.shipping_address',
                    'transactions.shipping_type',
                    'transactions.shipping_charges',
                ])
                ->firstOrFail();

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
            $stock_id = $request->stock_id;

            $status = $request->status;
            $receipt_status = $request->receipt_status;

            $transaction = [];

            if (!empty($status)) {
                if ($status === "approve") {
                    foreach ($ids as $id) {
                        $transaction = Transaction::findOrFail($id);

                        if (isset($transaction->ref_no) && is_numeric($transaction->ref_no)) {
                            $order = Transaction::findOrFail($transaction->ref_no);
                            if ($order && $order->id) {
                                $order->status = "complete";
                                $order->complete_by = $user_id;
                                $order->save();
                            }
                        }

                        $transaction->approve_by = $user_id;
                        $transaction->status = "approve";
                        $transaction->res_order_status = "request";
                        $transaction->save();
                    }
                } else {
                    $dataUpdate = [
                        'status' => $status,
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
            }

            if (!empty($receipt_status)) {
                if ($receipt_status === "approve") {
                    foreach ($ids as $id) {
                        $transaction = Transaction::findOrFail($id);

                        $transaction->receipt_status = "approve";
                        $transaction->complete_by = $user_id;

                        $result = $this->changeQuantityProduct($transaction->id, $stock_id, $user_id, $business_id);

                        if (!$result) {
                            DB::rollBack();
                            $message = "Lỗi khi thêm sản phẩm vào kho";

                            return $this->respondWithError($message, [], 500);
                        }
                        $transaction->save();
                    }
                } else {
                    $dataUpdate = [
                        'receipt_status' => $receipt_status
                    ];
                    $transaction = DB::table('transactions')
                        ->whereIn('id', $ids)
                        ->update($dataUpdate);
                }
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

            if (isset($transaction->ref_no) && is_numeric($transaction->ref_no)) {
                $order = Transaction::findOrFail($transaction->ref_no);
                if ($order && $order->id) {
                    $order->status = "po_pending";
                    $order->pending_by = "po_pending";
                    $order->save();
                }
            }

            $transaction->status = "pending";
            $transaction->pending_by = $user_id;
            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành đang chờ duyệt";

            Activity::history($message, "sell", $dataLog);

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

            if (isset($transaction->ref_no) && is_numeric($transaction->ref_no)) {
                $order = Transaction::findOrFail($transaction->ref_no);
                if ($order && $order->id) {
                    $order->status = "complete";
                    $order->complete_by = $user_id;
                    $order->save();
                }
            }

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

            Activity::history($message, "sell", $dataLog);

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

            if (isset($transaction->ref_no) && is_numeric($transaction->ref_no)) {
                $order = Transaction::findOrFail($transaction->ref_no);
                if ($order && $order->id) {
                    $order->status = "po_pending";
                    $order->pending_by = $user_id;
                    $order->save();
                }
            }

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

            $transaction = Transaction::findOrFail($id);

            if (isset($transaction->ref_no) && is_numeric($transaction->ref_no)) {
                $order = Transaction::findOrFail($transaction->ref_no);
                if ($order && $order->id) {
                    $order->status = "po_reject";
                    $order->reject_by = $user_id;
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

            Activity::history($message, "sell", $dataLog);

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
                $message = "Không tìm thấy đơn hàng";

                return $this->respondWithError($message, [], 500);
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
            $transaction->complete_by = $user_id;

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

            Activity::history($message, "sell", $dataLog);

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
                $message = "Không tìm thấy đơn hàng\"";

                return $this->respondWithError($message, [], 500);
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

            Activity::history($message, "sell", $dataLog);

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
                'sub_type',
                'invoice_no',
                'ref_no',
                'status',
                'transaction_date',
                'location_id',
                'stock_id',
                'final_total',
                'staff_note',
                'contact_id',
                'tax_rate_id',
                'delivery_company_id',
                'shipping_type',
                'shipping_address',
                'shipping_charges',
                'discount_type',
                'discount_amount',
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
            $stock_id = $request->stock_id;
            $products = $request->products;
            $payment_method = $request->payment_method;

            if ($request->created_by) {
                $user_id = $request->created_by;
            }

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

            $shippingCharge = 0;
            if (isset($input["shipping_charges"]) && $input["shipping_charges"]) {
                $shippingCharge = $input["shipping_charges"];
            }

            $invoice_total = $this->productUtil->calculateInvoiceTotal($products, $tax_rate_id, $discount, null, $shippingCharge);

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
                    $input['approve_by'] = $user_id;
                    $input['res_order_status'] = "request";
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
                $message = "không thể xóa được yêu cầu mua hàng đã được duyệt!";

                return $this->respondWithError($message, [], 500);
            }
            //Delete sell lines first
            $delete_purchase_line_ids = [];
            foreach ($delete_purchase_lines as $transaction_line) {
                $delete_purchase_line_ids[] = $transaction_line->id;
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

            $transaction = Transaction::where('business_id', $business_id)
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

            return $this->respondSuccess($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    private function changeQuantityProduct($transaction_id, $stock_id, $user_id, $business_id)
    {
        try {
            $transaction_line = TransactionSellLine::where('transaction_id', $transaction_id)->get();

            if ($transaction_line && count($transaction_line) > 0) {
                foreach ($transaction_line as $product) {
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

    public function importData(Request $request)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $stock_id = $request->stock_id;

            if (!$stock_id) {
                DB::rollBack();
                $message = "Vui lòng chọn cửa hàng hoặc kho hàng";

                return $this->respondWithError($message, [], 500);
            }

            //Set maximum php execution time
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            if ($request->hasFile('file')) {
                $file = $request->file('file');

                $parsed_array = Excel::toArray([], $file);

                //Remove header row
                $imported_data = array_splice($parsed_array[0], 1);

                $transactionData = [];
                $sellLineData = [];

                $is_valid = true;
                $error_msg = '';

                $total_rows = count($imported_data);

                foreach ($imported_data as $key => $value) {
                    //Check if any column is missing
                    if (count($value) < 15 ) {
                        $is_valid =  false;
                        $error_msg = "Thiếu cột trong quá trình tải lên dữ liệu. vui lòng tuần thủ dữ template";
                        break;
                    }

                    $row_no = $key + 1;
                    $order_array = [];
                    $sell_lines = [];
                    $order_array['business_id'] = $business_id;
                    $order_array['created_by'] = $user_id;
                    $sell_lines['business_id'] = $business_id;
                    $sell_lines['created_by'] = $user_id;
                    $order_array['stock_id'] = $stock_id;
                    $order_array['status'] = "approve";
                    $order_array['receipt_status'] = "approve";
                    $order_array['res_order_status'] = "enough";
                    $order_array['shipping_status'] = "complete";
                    $order_array['payment_status'] = "payment_pending";
                    $order_array['type'] = "sell";


                    //Add ref no
                    $invoice_no = trim($value[0]);

                    if (!empty($invoice_no)) {
                        $order_array['invoice_no'] = $invoice_no;
                        //Check if product with same SKU already exist
                        $is_exist = Transaction::where('invoice_no', $order_array['invoice_no'])
                            ->where('business_id', $business_id)
                            ->exists();
                        if ($is_exist) {
                            $is_valid = false;
                            $error_msg = "Mã đơn hàng : $invoice_no đã tồn tại ở dòng thứ. $row_no";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã khách hàng";
                        break;
                    }

                    //Add customer
                    $contact_id = trim($value[4]);

                    if (!empty($contact_id)) {
                        //Check if product with same SKU already exist
                        $contact = Contact::where('contact_id', $contact_id)
                            ->where('business_id', $business_id)
                            ->first();

                        if (!$contact) {
                            $is_valid = false;
                            $error_msg = "Không tìm thấy mã khách hàng : $contact_id ở dòng thứ. $row_no";
                            break;
                        }

                        $order_array['contact_id'] = $contact->id;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã khách hàng";
                        break;
                    }

                    //Add customer
                    $location_id = trim($value[5]);

                    if (!empty($location_id)) {
                        //Check if product with same SKU already exist
                        $locations = BusinessLocation::where('location_id', $location_id)
                            ->where('business_id', $business_id)
                            ->first();
                        if (!$locations) {
                            $is_valid = false;
                            $error_msg = "Không tìm thấy mã cửa hàng : $location_id ở dòng thứ. $row_no";
                            break;
                        }

                        $order_array['location_id'] = $locations->id;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã cửa hàng";
                        break;
                    }


                    //name product
                    $productSku = trim($value[1]);

                    if (!empty($productSku)) {
                        //Check if product with same SKU already exist
                        $product = Product::where('sku', $productSku)
                            ->where('business_id', $business_id)
                            ->first();
                        if (!$product) {
                            $is_valid = false;
                            $error_msg = "Không tìm thấy mã sản phẩm : $productSku ở dòng thứ. $row_no";
                            break;
                        }

                        $stock_product = StockProduct::where('product_id', $product->id)
                            ->where('stock_id', $stock_id)
                            ->first();

                        if (!$stock_product) {
                            $is_valid = false;
                            $error_msg = "Không tìm thấy mã sản phẩm : $invoice_no ở dòng thứ. $row_no";
                            break;
                        }

                        $sell_lines['product_id'] = $product->id;
                        $sell_lines['stock_product_id'] = $stock_product->id;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã sản phẩm";
                        break;
                    }

                    $quantity = trim($value[7]) ? trim($value[7]) : 0;

                    if (!empty($quantity)) {
                        $sell_lines['quantity'] = (float)$quantity;
                        $sell_lines['quantity_sold'] = (float)$quantity;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã số lượng";
                        break;
                    }

                    $purchase_price = trim($value[8]) ? trim($value[8]) : 0;

                    if (!empty($purchase_price)) {
                        $sell_lines['purchase_price'] = (float)$purchase_price;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã giá mua";
                        break;
                    }

                    $discount_amount = trim($value[9]) ? trim($value[9]) : 0;

                    if (!empty($discount_amount)) {
                        $sell_lines['line_discount_amount'] = (float)$discount_amount;
                    }

                    $totalDiscount = trim($value[10]);

                    if (!empty($discount_amount)) {
                        $sell_lines['total_discount'] = $totalDiscount;
                    }

                    $unit_price = trim($value[11]) ?trim($value[11]) : 0;
                    $sell_lines['unit_price_before_discount']  = 0;
                    $sell_lines['unit_price_inc_tax']  = 0;

                    if (!empty($unit_price)) {
                        $sell_lines['unit_price'] = (float)$unit_price;
                        $sell_lines['unit_price_inc_tax'] = (float)$unit_price + (float)$totalDiscount;
                        $sell_lines['unit_price_before_discount'] = (float)$unit_price + (float)$totalDiscount;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu giá bán";
                        break;
                    }

                    $total_discount = trim($value[12]);

                    if (!empty($total_discount)) {
                        $sell_lines['total_discount'] = (float)$total_discount;
                        $order_array['discount_type'] = "fixed";
                        $order_array['discount_amount'] = (float)$total_discount;
                    }

                    $final_total = trim($value[13]);

                    if (!empty($final_total)) {
                        $sell_lines['total'] = (float)$final_total;
                        $order_array['final_total'] = (float)$final_total;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu tổng tiền";
                        break;
                    }

                    if ($unit_price && $purchase_price && $quantity) {
                        $profit = ((float)$unit_price - (float)$purchase_price) * (float) $quantity;
                        $total_before_tax = (float)$sell_lines['unit_price_before_discount'] * (float) $quantity;
                        $sell_lines['profit'] = $profit;
                        $order_array['final_profit'] = (float)$profit;
                        $order_array['total_before_tax'] = (float)$total_before_tax;
                    }

                    $transactionData[] = [
                        'transaction' => $order_array,
                        'sell_lines' => $sell_lines
                    ];
                }




                if (!$is_valid) {
                    throw new \Exception($error_msg);
                }

                if (!empty($transactionData)) {
                    foreach ($transactionData as $index => $order_data) {
                        //Create new product
                        $invoice_no = $order_data["transaction"]["invoice_no"];

                        $transaction = Transaction::where('invoice_no', $invoice_no)->first();

                        if (!$transaction) {
                            $transaction = Transaction::create($order_data["transaction"]);
                            if (!$transaction) {
                                DB::rollBack();
                                $message = "Lỗi trong quá trình nhập liệu";

                                return $this->respondWithError($message, [], 500);
                            }
                        } else {
                            $total_before_tax = $order_data["transaction"]["total_before_tax"];
                            $final_total = $order_data["transaction"]["final_total"];
                            $final_profit = $order_data["transaction"]["final_profit"];
                            $discount_amount = $order_data["transaction"]["discount_amount"];

                            $dataUpdate = [
                                'total_before_tax' => DB::raw('total_before_tax + ' . $total_before_tax ),
                                'final_total' => DB::raw('final_total + ' . $final_total ),
                                'final_profit' => DB::raw('final_profit + ' . $final_profit ),
                                'discount_amount' => DB::raw('discount_amount + ' . $discount_amount ),
                            ];

                            $result = Transaction::where('invoice_no', $invoice_no)
                                ->update($dataUpdate);

                            if (!$result) {
                                DB::rollBack();
                                $message = "Lỗi trong quá trình nhập liệu";

                                return $this->respondWithError($message, [], 500);
                            }
                        }

                        $order_data["sell_lines"]["transaction_id"] = $transaction->id;

                        TransactionSellLine::create($order_data["sell_lines"]);
//                        return $this->respondWithError(true, $order_data["transaction"]["ref_no"], 500);
                    }
                }
            }

            DB::commit();
            $message = "Nhập liệu đơn hàng thành công";

            return $this->respondSuccess($message, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

}
