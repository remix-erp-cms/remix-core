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
            $stock_id = $request->stock_id;
            $user_id = Auth::guard('api')->user()->id;


            $purchases = $this->transactionUtil->getListPurchasesRequest($business_id);
            $purchases->where('transactions.stock_id', $stock_id);

            if (!empty(request()->supplier_id)) {
                $purchases->where('contacts.id', request()->supplier_id);
            }

            if (!empty(request()->location_id)) {
                $purchases->where('transactions.location_id', request()->location_id);
            }

            if (!empty(request()->status) && request()->status !== "all") {
                $purchases->where('transactions.status', request()->status);
            }

            $contact_id = request()->get('contact_id');
            if (!empty($contact_id)) {
                $purchases->where('transactions.contact_id', $contact_id);
            }

            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();

            if (!empty(request()->start_date)) {
                $start = Carbon::createFromFormat('d/m/Y', request()->start_date) ;
            }

            if (!empty(request()->end_date)) {
                $end = Carbon::createFromFormat('d/m/Y', request()->end_date) ;
            }


            $purchases->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end);

            $status = request()->status;

            if (!empty($status)) {
                $purchases->where('transactions.status', $status);
            }

            $shipping_status = request()->shipping_status;

            if (!empty($shipping_status) && $shipping_status != "all") {
                $purchases->where('transactions.shipping_status', $shipping_status);
            }

            $res_order_status = request()->res_order_status;

            if (!empty($res_order_status) && $res_order_status != "all") {
                $purchases->where('transactions.res_order_status', $res_order_status);
            }


            $purchases->orderBy('transactions.transaction_date', "desc");

            $data = $purchases->paginate($request->limit);

            $summary = DB::table('transactions')
                ->select(DB::raw('count(*) as total, status'))
                ->where('business_id', $business_id)
                ->where('stock_id', $stock_id)
                ->where('type', "purchase_request")
                ->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end)
                ->groupBy('status')
                ->get();

            return $this->respondSuccess($data, null, ["summary" => $summary]);
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

            if(isset($request->products) && count($request->products) ===0) {
                DB::rollBack();
                return $this->respondWithError("Vui lòng thêm ít nhất một đơn hàng", [], 500);
            }

            if(isset($request->final_total) && $request->final_total === 0) {
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
            $transaction_data["transaction_date"] = Carbon::createFromFormat('d/m/Y', $transaction_data["transaction_date"]);

            //Update reference count
            $ref_count = $this->productUtil->setAndGetReferenceCount($transaction_data['type'], $business_id);

            //Generate reference number
            if (empty($transaction_data['invoice_no'])) {
                $transaction_data['invoice_no'] = $this->productUtil->generateReferenceNumber($transaction_data['type'], $ref_count);
            }

            $transaction = Transaction::create($transaction_data);

            $purchases = $request->input('products');

            $this->productUtil->createOrUpdatePurchaseLines($transaction, $purchases, $currency_details, $enable_product_editing);

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Tạo yêu cầu mua hàng mới",
                'subject_id' => $transaction->id
            ];

            $message = "Khởi tạo đơn yêu cầu mua hàng với giá trị đơn là" . number_format($transaction_data["final_total"]) . "đ";
            Activity::history($message , "purchase_request", $dataLog);

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
                'purchase_lines',
                'purchase_lines.product:id,sku,name,contact_id,unit_id',
                'purchase_lines.product.contact:id,name',
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
                'transactions.status',
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

         return $this->respondSuccess($purchase);
     } catch (\Exception $e) {
         DB::rollBack();
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
                    'contact:id,contact_id,first_name,name,mobile,email,type,address_line_1,created_at',
                    'sell_lines',
                    'stock:id,stock_name,stock_type,location_id',
                    'stock.location:id,name,landmark',
                    'sell_lines.product:id,sku,name,contact_id,unit_id',
                    'sell_lines.product.contact:id,name',
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
                ])
                ->firstOrFail();

            $purchase->purchase_lines = $purchase->sell_lines;
            $purchase->ref_no = $id;
            $purchase->invoice_no = null;

            foreach ($purchase->sell_lines as $key => $value) {
                $purchase->purchase_lines[$key]["quantity"] = $value->quantity_adjusted;
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

            $status = $request->status;

            $dataUpdate = [
                'status' => "pending"
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
                'status' => "approve"
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

            Activity::history($message , "purchase_request", $dataLog);

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
                'status' => "reject"
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

            Activity::history($message , "purchase_request", $dataLog);

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

            //update transaction
            $transaction->update($update_data);

            //Update transaction payment status
            $this->transactionUtil->updatePaymentStatus($transaction->id);

            $purchases = $request->input('products');

            $delete_purchase_lines = $this->productUtil->createOrUpdatePurchaseLines($transaction, $purchases, $currency_details, $enable_product_editing, $before_status);

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
            Activity::history($message , "purchase_request", $dataLog);

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
}
