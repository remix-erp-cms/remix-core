<?php

namespace App\Http\Controllers\Stock;

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
use App\ProductSerial;
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

class OrderImportController extends Controller
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
            $location_id =  $request->location_id;
            $stock_id = $request->stock_id;
            $payment_types = $this->transactionUtil->payment_types();

            $with = [];
            $shipping_statuses = $this->transactionUtil->shipping_statuses();
//            $purchase = $this->transactionUtil->getListSells($business_id);
            $purchase = Transaction::with([
                'contact:id,first_name,mobile,email,tax_number,type',
                'location:id,location_id,name,landmark',
                'business:id,name',
                'tax:id,name,amount',
                'sales_person:id,first_name,user_type',
                'accountants',
                'children:id,ref_no,final_total,invoice_no,type,status'
            ])
                ->leftJoin("contacts", "contacts.id", "=", "transactions.contact_id")
                ->leftJoin("users", "users.id", "=", "transactions.created_by")
                ->whereIn('transactions.type', ['purchase', 'sell_return'])
                ->where('transactions.status', 'approve')
                ->where('transactions.location_id', $location_id)
                ->where('transactions.business_id', $business_id);

            if (!empty($request->id)) {
                $purchase->where('transactions.invoice_no',"LIKE", "%$request->id%");
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

            $receipt_status = request()->receipt_status;

            if (!empty($receipt_status) && $receipt_status != "all") {
                $purchase->where('transactions.receipt_status', $receipt_status);
            }

            $purchase->addSelect('transactions.res_order_status');


            $purchase->groupBy('transactions.id');
            $purchase->orderBy('transactions.created_at', "desc");
            $purchase->select("transactions.*");

            $data = $purchase->paginate($request->limit);
//
//            $summary = DB::table('transactions')
//                ->select(DB::raw('sum(final_total) as final_total, count(*) as total, res_order_status as status'))
//                ->where('business_id', $business_id)
//                ->where('location_id', $location_id)
//                ->whereIn('type', ['purchase', 'sell_return'])
//                ->where('transactions.status', 'approve')
//                ->groupBy('res_order_status');
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
                ->leftJoin("users", "users.id", "=", "transactions.created_by")
                ->leftJoin('products', 'products.id', '=', 'purchase_lines.product_id')
                ->leftJoin('contacts', 'contacts.id', '=', 'products.contact_id')
                ->leftJoin('units', 'products.unit_id', '=', 'units.id')
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
                    'contacts.tax_number as tax_number',
                    'products.name as product_name',
                    'units.actual_name as unit_name',
                    \DB::raw('SUM(purchase_lines.purchase_price * purchase_lines.quantity) as final_total')
                );

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
                    'purchase_lines.product.product_serial:id,purchase_line_id,product_id,serial,is_sell',
                    'purchase_lines.sub_unit',
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

            Activity::history($message , "purchase_request", $dataLog);

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
            $request->validate([
                'products',
            ]);

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

            if (!$transaction) {
                DB::rollBack();
                $message = "Không tìm thấy đơn hàng";

                return $this->respondWithError($message, [], 500);
            }

            $products = $request->products;
            $serial_scans = $request->serial_scans;
            $quantity_confirm = 0;
            $totalQty = 0;

            foreach ($products as $product) {
                $data = [
                    'quantity_sold' => $product["quantity_sold"],
                    'quantity_adjusted' => $product["quantity_adjusted"]
                ];

                $quantity_confirm += $product["quantity_sold"];
                $totalQty += $product["quantity"];

                $result = PurchaseLine::where('id', $product["purchase_id"])
                    ->update($data);

                if (!$result) {
                    DB::rollBack();
                    $message = "Lỗi trong quá trình cập nhật số lượng sản phẩm";

                    return $this->respondWithError($message, [], 500);
                }

                $isRedirect = $request->isRedirect;
                $stock_id = $request->stock_id;

                if (isset($isRedirect) && $isRedirect === true && !empty($stock_id)) {
                    $stock = StockProduct::where('stock_id', $stock_id)
                        ->where('product_id', $product["id"])
                        ->increment('quantity', $product["quantity_sold"]);

                    if (!$stock) {
                        DB::rollBack();
                        $message = "Lỗi trong quá trình cập nhật số lượng sản phẩm";

                        return $this->respondWithError($message, [], 500);
                    }
                }
            }

            if ($quantity_confirm === $totalQty) {
                $transaction->res_order_status = "enough";
            } else {
                $transaction->res_order_status = "deficient";
            }

            $discount = ['discount_type' => $transaction->discount_type,
                'discount_amount' => $transaction->discount_amount
            ];

            $tax_rate_id = $transaction->tax_id;

            $invoice_total = $this->productUtil->calculateTotalPurchaseSold($products, $tax_rate_id, $discount);

            $transaction->total_before_tax = $invoice_total['total_before_tax'];
//            $transaction->final_total = $invoice_total['final_total'];
//            $transaction->tax_amount = $invoice_total['tax'];

            $transaction->receipt_status = "request";

            if (isset($serial_scans) && count($serial_scans) > 0) {
                $data_insert = [];
                foreach ($serial_scans as $item) {
                    $product_id = isset($item['product_id']) ? $item['product_id'] : null;
                    $purchase_id = isset($item['purchase_id']) ? $item['purchase_id'] : null;
                    $scans = isset($item['items']) ? $item['items'] : [];
                    foreach ($scans as $scan) {
                        if (!empty($scan['is_new']) && $scan['is_new'] === true) {
                            $dataInsert = [
                                "product_id" => $product_id,
                                "purchase_line_id" => $purchase_id,
                                "serial" => isset($scan['serial']) ? $scan['serial'] : [],
                                'created_at' => now(),
                            ];

                            array_push($data_insert, $dataInsert);
                        }

                        if (!empty($scan['is_remove']) && $scan['is_remove'] === true) {
                            $variationResult = ProductSerial::where("id", $scan["id"])
                                ->delete();

                            if (!$variationResult) {
                                $message = "Không thể lưu dữ liệu";
                                return $this->respondWithError($message, [], 503);
                            }
                        }
                    }
                }

                $result = ProductSerial::insert($data_insert);

                if (!$result) {
                    DB::rollBack();
                    $message = "Lỗi trong quá trình cập nhật số lượng sản phẩm";

                    return $this->respondWithError($message, [], 500);
                }
            }

            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Kho xác nhận đơn hàng!",
                'subject_id' => $id
            ];

            $message = "Xác nhận đơn hàng đã đủ";

            Activity::history($message , "purchase", $dataLog);

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

            $transaction->res_order_status = "reject";
            $transaction->receipt_status = "request";
            $transaction->save();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Cập nhật trạng thái cầu mua hàng",
                'subject_id' => $id
            ];

            $message = "Chuyển trạng thái mua hàng thành từ chối";

            Activity::history($message , "purchase_request", $dataLog);

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

    private function changeQuantityProduct($transaction_id, $stock_id, $user_id, $business_id) {
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

                    Activity::history($message , "product", $dataLog);
                }

                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
