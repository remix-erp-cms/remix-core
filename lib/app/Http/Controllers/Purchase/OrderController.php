<?php

namespace App\Http\Controllers\Purchase;

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
            $location_id = $request->location_id;
            $stock_id = $request->stock_id;
            $user_id = Auth::guard('api')->user()->id;

            $purchase = Transaction::with([
                'contact:id,first_name,mobile,email,tax_number,type',
                'sales_person:id,first_name,user_type',
                'pending_by:id,first_name,user_type',
                'approve_by:id,first_name,user_type',
                'reject_by:id,first_name,user_type',
                'complete_by:id,first_name,user_type',
                'children:id,ref_no,final_total,invoice_no,type,status',
                'parent:id,ref_no,final_total,invoice_no,type,status'
            ])
                ->leftJoin("contacts", "contacts.id", "=", "transactions.contact_id")
                ->leftJoin("users", "users.id", "=", "transactions.created_by")
                ->where('transactions.type', 'purchase')
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

            $stock_id = request()->get('stock_id');
            if (!empty($stock_id)) {
                $purchase->where('transactions.stock_id', $stock_id);
            }

            $location_id = request()->get('location_id');
            if (!empty($location_id)) {
                $purchase->where('transactions.location_id', $location_id);
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


            $receipt_status = request()->receipt_status;

            if (!empty($receipt_status) && $receipt_status != "all") {
                $purchase->where('transactions.receipt_status', $receipt_status);
            }

            $purchase->addSelect('transactions.res_order_status');


            $purchase->groupBy('transactions.id');
            $purchase->orderBy('transactions.created_at', "desc");
            $purchase->select("transactions.*");

            $data = $purchase->paginate($request->limit);

//            $type_summary = "order";
//            if (!empty($request->type_summary)) {
//                $type_summary = $request->type_summary;
//            }
//            $summary = [];
//
//            if ($type_summary === "order") {
//                $summary = DB::table('transactions')
//                    ->select(DB::raw('sum(final_total) as final_total, count(*) as total, status'))
//                    ->where('business_id', $business_id)
//                    ->where('location_id', $location_id)
//                    ->where('type', "purchase")
//                    ->whereDate('transactions.transaction_date', '>=', $start)
//                    ->whereDate('transactions.transaction_date', '<=', $end)
//                    ->groupBy('status');
//            }
//
//            if ($type_summary === "res_order_status") {
//                $summary = DB::table('transactions')
//                    ->select(DB::raw('sum(final_total) as final_total, count(*) as total, res_order_status as status'))
//                    ->where('business_id', $business_id)
//                    ->where('location_id', $location_id)
//                    ->where('type', "purchase")
//                    ->whereDate('transactions.transaction_date', '>=', $start)
//                    ->whereDate('transactions.transaction_date', '<=', $end)
//                    ->groupBy('res_order_status');
//            }
//
//            if ($summary) {
//                if (!empty($res_order_status) && count($res_order_status) > 0) {
//                    $summary->whereIn('transactions.res_order_status', $res_order_status);
//                } else if (!empty($res_order_status) && $res_order_status != "all") {
//                    $summary->where('transactions.res_order_status', $res_order_status);
//                }
//
//                if (empty($view_all) || $view_all != "1") {
//                    $summary->where('transactions.created_by', $user_id);
//                }
//
//                $summary = $summary->get();
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

            $purchase = PurchaseLine::leftJoin('transactions', 'transactions.id', '=', 'purchase_lines.transaction_id')
                ->leftJoin("transactions as tl1", "tl1.id", "=", "transactions.ref_no")
                ->leftJoin("users", "users.id", "=", "transactions.created_by")
                ->leftJoin('products', 'products.id', '=', 'purchase_lines.product_id')
                ->leftJoin('contacts', 'contacts.id', '=', 'products.contact_id')
                ->leftJoin('units', 'products.unit_id', '=', 'units.id')
                ->leftJoin('business_locations', 'business_locations.id', '=', 'transactions.location_id')
                ->where('transactions.type', 'purchase')
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
				  	'contacts.address_line_1 as contact_address',
                    'contacts.tax_number as tax_number',
                    'products.name as product_name',
                    'tl1.invoice_no as ref_no',
                    'units.actual_name as unit_name',
                    'products.sku as product_sku',
                    'users.first_name as created_by',
                    'business_locations.name as location_name',
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

    public function createInit(Request $request)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $ids = $request->ids;

            $listId = explode(',', $ids);

            $purchase = Transaction::where('business_id', $business_id)
                ->whereIn('transactions.id', $listId)
                ->with(
                    'contact',
                    'purchase_lines:product_id,purchase_price,purchase_price_inc_tax,quantity,total,transaction_id',
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
                ->get();
//
//            foreach ($purchase->purchase_lines as $key => $value) {
//                if (!empty($value->sub_unit_id)) {
//                    $formated_purchase_line = $this->productUtil->changePurchaseLineUnit($value, $business_id);
//                    $purchase->purchase_lines[$key] = $formated_purchase_line;
//                }
//            }

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
            $status = $request->status;
            $location_id = $request->location_id;
            $stock_id = $request->stock_id;

            $transaction_data = $request->only([
                'sub_type',
                'invoice_no',
                'ref_no',
                'status',
                'transaction_date',
                'discount_amount',
                'discount_type',
                'tax_rate_id',
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
                'shipping_details',
                'discount_type',
                'discount_amount',
                'service_custom_field_1',
                'service_custom_field_2',
            ]);

            $request->validate([
                'products',
                'final_total',
                'transaction_date'
            ]);

            $isDirect = false;

            if (isset($request->status)) {
                $isDirect = $request->isDirect;
            }

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
            $transaction_data['type'] = 'purchase';
            $transaction_data['payment_status'] = 'payment_pending';
            $transaction_data['status'] = 'ordered';
            if (!empty($status)) {
                $transaction_data['status'] = $status;
                if ($status === "approve") {
                    $transaction_data['approve_by'] = $user_id;
                    if ($isDirect === true) {
                        $transaction_data['res_order_status'] = "request";
                        $transaction_data['shipping_status'] = "created";
                        $transaction_data['payment_status'] = "payment_paid";
                    } else {
                        $transaction_data['res_order_status'] = "request";
                    }
                }

                if ($status === "pending") {
                    $transaction_data['pending_by'] = $user_id;
                }

                if ($status === "reject") {
                    $transaction_data['reject_by'] = $user_id;
                }
            }
            $transaction_data["transaction_date"] = Carbon::createFromFormat('d/m/Y', $transaction_data["transaction_date"]);

            //Update reference count
            $ref_count = $this->productUtil->setAndGetReferenceCount($transaction_data['type'], $business_id);

            //Generate reference number
            if (empty($transaction_data['invoice_no'])) {
                $transaction_data['invoice_no'] = $this->productUtil->generateReferenceNumber($transaction_data['type'], $ref_count, $business_id, "PO");
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

            $products = $request->input('products');

            if (isset($request->group_contact) && $request->group_contact === true) {
                $invoice_total = $this->productUtil->calculatePurchaseReturnTotal($products, $tax_rate_id, $discount, null, $shippingCharge);

                $transaction_data['total_before_tax'] = isset($invoice_total["total_before_tax"]) ? $invoice_total["total_before_tax"] : 0;
                $transaction_data['discount_amount'] = isset($invoice_total["discount"]) ? $invoice_total["discount"] : 0;
                $transaction_data['tax_amount'] = isset($invoice_total["tax"]) ? $invoice_total["tax"] : 0;
                $transaction_data['final_total'] = isset($invoice_total["final_total"]) ? $invoice_total["final_total"] : 0;


                $transaction = Transaction::create($transaction_data);

                if (!$transaction) {
                    DB::rollBack();
                    return $this->respondWithError("Lỗi khi thêm đơn hàng", [], 500);
                }

                $result = $this->productUtil->createOrUpdatePurchaseLines(
                    $transaction,
                    $products,
                    $currency_details,
                    $enable_product_editing,
                    null,
                    $stock_id,
                    $isDirect,
                    true
                );
            } else {

                $purchase = [];

                foreach ($products as $product) {
                    if (isset($product["contact_id"])) {
                        if (isset($purchase[$product["contact_id"]]) && $purchase[$product["contact_id"]]) {
                            array_push($purchase[$product["contact_id"]], $product);
                        } else {
                            $purchase[$product["contact_id"]] = [$product];
                        }
                    }
                }


                foreach ($purchase as $key => $item) {
                    $invoice_total = $this->productUtil->calculatePurchaseReturnTotal($item, $tax_rate_id, $discount, null, $shippingCharge);

                    $dataChildTransaction = $transaction_data;

                    $dataChildTransaction['contact_id'] = $key;
                    $dataChildTransaction['total_before_tax'] = isset($invoice_total["total_before_tax"]) ? $invoice_total["total_before_tax"] : 0;
                    $dataChildTransaction['discount_amount'] = isset($invoice_total["discount"]) ? $invoice_total["discount"] : 0;
                    $dataChildTransaction['tax_amount'] = isset($invoice_total["tax"]) ? $invoice_total["tax"] : 0;
                    $dataChildTransaction['final_total'] = isset($invoice_total["final_total"]) ? $invoice_total["final_total"] : 0;

                    $transaction = Transaction::create($dataChildTransaction);

                    $result = $this->productUtil->createOrUpdatePurchaseLines(
                        $transaction,
                        $item,
                        $currency_details,
                        $enable_product_editing,
                        null,
                        $stock_id,
                        $isDirect,
                        true
                    );
                }
            }


//            $dataLog = [
//                'created_by' => $user_id,
//                'business_id' => $business_id,
//                'log_name' => "Tạo yêu cầu mua hàng mới",
//                'subject_id' => $transaction->id
//            ];
//
//            $message = "Khởi tạo đơn yêu cầu mua hàng với giá trị đơn là" . number_format($transaction_data["final_total"]) . "đ";
//            Activity::history($message, "purchase_request", $dataLog);

            DB::commit();

            return $this->respondSuccess($transaction_data);
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
                    'transactions.pending_by',
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
            $stock_id = $request->stock_id;

            $status = $request->status;
            $receipt_status = $request->receipt_status;

            $transaction = [];

            if (!empty($status)) {
                if ($status === "approve") {
                    foreach ($ids as $id) {
                        $transaction = Transaction::findOrFail($id);
                        if (isset($transaction->ref_no)) {
                            $listId = explode(',', $transaction->ref_no);

                            Transaction::whereIn('id', $listId)->update([
                                'status' => 'complete',
                                'complete_by' => $user_id
                            ]);
                        }

                        $transaction->status = "approve";
                        $transaction->approve_by = $user_id;
                        $transaction->shipping_status = "created";

                        $transaction->save();
                    }
                }
                if ($status === "pending") {
                    foreach ($ids as $id) {
                        $transaction = Transaction::findOrFail($id);
                        if (isset($transaction->ref_no)) {
                            $listId = explode(',', $transaction->ref_no);

                            Transaction::whereIn('id', $listId)->update([
                                'status' => 'po_pending',
                            ]);
                        }

                        $transaction->status = "pending";
                        $transaction->pending_by = $user_id;
                        $transaction->save();
                    }
                }
                if ($status === "reject") {
                    foreach ($ids as $id) {
                        $transaction = Transaction::findOrFail($id);
                        if (isset($transaction->ref_no)) {
                            $listId = explode(',', $transaction->ref_no);

                            Transaction::whereIn('id', $listId)->update([
                                'status' => 'po_reject',
                                'complete_by' => $user_id
                            ]);
                        }

                        $transaction->status = "reject";
                        $transaction->reject_by = $user_id;
                        $transaction->save();
                    }
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
                        'receipt_status' => $receipt_status,
                        'complete_by' => $user_id
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

            if (isset($transaction->ref_no)) {
                $listId = explode(',', $transaction->ref_no);

                Transaction::whereIn('id', $listId)->update([
                    'status' => 'po_pending',
                ]);
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

            Activity::history($message, "purchase", $dataLog);

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

            if (isset($transaction->ref_no)) {
                $listId = explode(',', $transaction->ref_no);

                Transaction::whereIn('id', $listId)->update([
                    'status' => 'complete',
                    'complete_by' => $user_id
                ]);
            }

            $transaction->status = "approve";
            $transaction->approve_by = $user_id;
            $transaction->shipping_status = "created";

            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành đã được duyệt";

            Activity::history($message, "purchase", $dataLog);

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
                    $order->complete_by = $user_id;
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

            Activity::history($message, "purchase", $dataLog);

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

            Activity::history($message, "purchase", $dataLog);

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

            Activity::history($message, "purchase", $dataLog);

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
                'contact_id',
                'final_total',
                'staff_note',
                'tax_rate_id',
                'delivery_company_id',
                'shipping_type',
                'shipping_address',
                'shipping_charges',
                'shipping_details',
                'discount_type',
                'discount_amount',
                'service_custom_field_1',
                'service_custom_field_2',
            ]);

            $update_data["updated_by"] = $user_id;

            $update_data["transaction_date"] = Carbon::createFromFormat('d/m/Y', $update_data["transaction_date"]);

            if (!empty($request->status)) {
                $update_data['status'] = $request->status;

                if ($request->status === "approve") {
                    $update_data['approve_by'] = $user_id;
                    $update_data['res_order_status'] = "request";
                }

                if ($request->status === "pending") {
                    $update_data['pending_by'] = $user_id;
                }

                if ($request->status === "reject") {
                    $update_data['reject_by'] = $user_id;
                }
            }

            $discount = null;

            if (isset($update_data['discount_type']) && $update_data['discount_type']) {
                $discount = ['discount_type' => $update_data['discount_type'],
                    'discount_amount' => $update_data['discount_amount']
                ];

            }

            $tax_rate_id = null;
            if (isset($update_data['tax_rate_id']) && $update_data['tax_rate_id']) {
                $tax_rate_id = $update_data['tax_rate_id'] ?? null;
                $update_data['tax_id'] = $tax_rate_id;
            }

            $shippingCharge = 0;
            if (isset($update_data["shipping_charges"]) && $update_data["shipping_charges"]) {
                $shippingCharge = $update_data["shipping_charges"];
            }

            $purchase = $request->input('products');

            $invoice_total = $this->productUtil->calculatePurchaseReturnTotal($purchase, $tax_rate_id, $discount, null, $shippingCharge);

            $update_data['total_before_tax'] = isset($invoice_total["total_before_tax"]) ? $invoice_total["total_before_tax"] : 0;
            $update_data['discount_amount'] = isset($invoice_total["discount"]) ? $invoice_total["discount"] : 0;
            $update_data['tax_amount'] = isset($invoice_total["tax"]) ? $invoice_total["tax"] : 0;
            $update_data['final_total'] = isset($invoice_total["final_total"]) ? $invoice_total["final_total"] : 0;
            $update_data['final_total'] = isset($invoice_total["final_total"]) ? $invoice_total["final_total"] : 0;

            //update transaction
            $transaction->update($update_data);

            //Update transaction payment status
            $this->transactionUtil->updatePaymentStatus($transaction->id);


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

    public function importData(Request $request)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $stock_id = $request->stock_id;
            $location_id = $request->location_id;

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
                    if (count($value) < 9 ) {
                        $is_valid =  false;
                        $error_msg = "Thiếu cột trong quá trình tải lên dữ liệu. vui lòng tuần thủ dữ template";
                        break;
                    }

                    $row_no = $key + 1;
                    $order_array = [];
                    $purchase_lines = [];
                    $order_array['business_id'] = $business_id;
                    $order_array['created_by'] = $user_id;
                    $purchase_lines['business_id'] = $business_id;
                    $purchase_lines['created_by'] = $user_id;
                    $order_array['stock_id'] = $stock_id;
                    $order_array['location_id'] = $location_id;
                    $order_array['status'] = "approve";
                    $order_array['receipt_status'] = "approve";
                    $order_array['res_order_status'] = "enough";
                    $order_array['shipping_status'] = "complete";
                    $order_array['payment_status'] = "payment_pending";
                    $order_array['type'] = "purchase";


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
                    $contact_name = trim($value[4]);

                    if (!empty($contact_name)) {
                        //Check if product with same SKU already exist
                        $contact = Contact::where('name',"LIKE", "%$contact_name%")
                            ->where('business_id', $business_id)
                            ->first();

                        if (!$contact) {
                            $is_valid = false;
                            $error_msg = "Không tìm thấy mã nhà cung cấp : $contact_name ở dòng thứ. $row_no";
                            break;
                        }

                        $order_array['contact_id'] = $contact->id;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã nhà cung cấp";
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

                        $purchase_lines['product_id'] = $product->id;
                        $purchase_lines['stock_product_id'] = $stock_product->id;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã sản phẩm";
                        break;
                    }

                    $quantity = trim($value[6]) ? trim($value[6]) : 0;

                    if (!empty($quantity)) {
                        $purchase_lines['quantity'] = (float)$quantity;
                        $purchase_lines['quantity_sold'] = (float)$quantity;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã số lượng";
                        break;
                    }

                    $purchase_price = trim($value[7]) ? trim($value[7]) : 0;

                    if (!empty($purchase_price)) {
                        $purchase_lines['purchase_price'] = (float)$purchase_price;
                        $purchase_lines['purchase_price_inc_tax'] = (float)$purchase_price;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã giá mua";
                        break;
                    }

                    $final_total = trim($value[8]);

                    if (!empty($final_total)) {
                        $purchase_lines['total'] = (float)$final_total;
                        $order_array['final_total'] = (float)$final_total;
                        $order_array['total_before_tax'] = (float)$final_total;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu mã tổng tiền";
                        break;
                    }

                    $transactionData[] = [
                        'transaction' => $order_array,
                        'purchase_lines' => $purchase_lines
                    ];

                }




                if (!$is_valid) {
                    throw new \Exception($error_msg);
                }

                if (!empty($transactionData)) {
                    foreach ($transactionData as $index => $order_data) {
                        //Create new product
                        $invoice_no = $order_data["transaction"]["invoice_no"];
                        $contact_id = $order_data["transaction"]["contact_id"];

                        $transaction = Transaction::where('invoice_no', $invoice_no)
                            ->where('contact_id', $contact_id)
                            ->first();

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

                            $dataUpdate = [
                                'total_before_tax' => DB::raw('total_before_tax + ' . $total_before_tax ),
                                'final_total' => DB::raw('final_total + ' . $final_total )
                            ];

                            $result = Transaction::where('invoice_no', $invoice_no)
                                ->update($dataUpdate);

                            if (!$result) {
                                DB::rollBack();
                                $message = "Lỗi trong quá trình nhập liệu";

                                return $this->respondWithError($message, [], 500);
                            }
                        }

                        $order_data["purchase_lines"]["transaction_id"] = $transaction->id;

                        PurchaseLine::create($order_data["purchase_lines"]);
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
