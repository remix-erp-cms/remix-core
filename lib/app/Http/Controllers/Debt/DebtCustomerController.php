<?php

namespace App\Http\Controllers\Debt;

use App\Account;
use App\Accountant;
use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Contact;
use App\CustomerGroup;
use App\Helpers\Activity;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Payment\PaymentController;
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

class DebtCustomerController extends Controller
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
    public function __construct(PaymentController $paymentUtil, ContactUtil $contactUtil, BusinessUtil $businessUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, ProductUtil $productUtil)
    {
        $this->contactUtil = $contactUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->productUtil = $productUtil;
        $this->paymentUtil = $paymentUtil;

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

            $purchase = Transaction::with([
                'contact:id,first_name,mobile,email,tax_number,type',
                'location:id,location_id,name,landmark',
                'business:id,name',
                'tax:id,name,amount',
                'sales_person:id,first_name,user_type',
                'accountants',
            ])
                ->leftJoin('contacts', 'contacts.id', '=', 'transactions.contact_id')
                ->where('transactions.type', 'sell')
                ->where('transactions.location_id', $location_id)
                ->where('transactions.business_id', $business_id);

            if (!empty($request->id)) {
                $purchase->where('transactions.invoice_no',"LIKE", "%$request->id%");
            }

            if (!empty($request->contact_name)) {
                $purchase->where('contacts.first_name',"LIKE", "%$request->contact_name%");
            }

            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (!empty($created_by)) {
                    $purchase->where('transactions.created_by', $created_by);
                }
            }

            $start = null;
            $end = null;

            if (!empty(request()->start_date) && !empty(request()->end_date)) {
                $start = Carbon::createFromFormat('d/m/Y', request()->start_date);
                $end = Carbon::createFromFormat('d/m/Y', request()->end_date);

                $purchase->whereDate('transactions.created_at', '>=', $start)
                    ->whereDate('transactions.created_at', '<=', $end);
            }

            $status = request()->status;

            if (!empty($status) && $status != "all") {
                $purchase->where('transactions.status', $status);
            }

            $payment_status = request()->payment_status;

            if (!empty($payment_status) && $payment_status != "all") {
                $purchase->where('transactions.payment_status', $payment_status);
            }

            $shipping_status = request()->shipping_status;

            if (!empty($shipping_status) && $shipping_status != "all") {
                $purchase->where('transactions.shipping_status', $shipping_status);
            }

            $receipt_status = request()->receipt_status;

            if (!empty($receipt_status) && $receipt_status != "all") {
                $purchase->where('transactions.receipt_status', $receipt_status);
            }

            $res_order_status = request()->res_order_status;

            if (!empty($res_order_status) && count($res_order_status) > 0) {
                $purchase->whereIn('transactions.res_order_status', $res_order_status);
            } else if (!empty($res_order_status) && $res_order_status != "all") {
                $purchase->where('transactions.res_order_status', $res_order_status);
            }

            $purchase->addSelect('transactions.res_order_status');

            $purchase->groupBy('transactions.contact_id', 'transactions.payment_status');
            $purchase->orderBy('transactions.created_at', "desc");
            $purchase->select(DB::raw('sum(final_total) as final_total, transactions.contact_id, payment_status'));


            $data = $purchase->paginate($request->limit);

            $summary = DB::table('transactions')
                ->select(DB::raw('sum(final_total) as final_total, count(*) as total, payment_status as status'))
                ->where('business_id', $business_id)
                ->where('location_id', $location_id)
                ->where('type', "sell")
                ->groupBy('payment_status');

            $contact_id = request()->get('contact_id');
            if (!empty($contact_id)) {
                $summary->where('contact_id', $contact_id);
            }

            if (!empty($receipt_status)) {
                $summary->where('transactions.receipt_status', $receipt_status);
            }

            if (!empty($start) && !empty($end)) {
                $start = Carbon::createFromFormat('d/m/Y', request()->start_date);
                $end = Carbon::createFromFormat('d/m/Y', request()->end_date);

                $summary->whereDate('transactions.transaction_date', '>=', $start)
                    ->whereDate('transactions.transaction_date', '<=', $end);
            }

            return $this->respondSuccess($data, null, ["summary" => $summary->get()]);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function listOrder(Request $request)
    {
        try {

            $business_id = Auth::guard('api')->user()->business_id;
            $location_id = $request->location_id;
            $stock_id = $request->stock_id;

            $purchase = Transaction::with([
                'contact:id,first_name,mobile,email,tax_number,type',
                'location:id,location_id,name,landmark',
                'business:id,name',
                'tax:id,name,amount',
                'sales_person:id,first_name,user_type',
                'accountants',
            ])
                ->where('transactions.type', 'sell')
                ->where('transactions.location_id', $location_id)
                ->where('transactions.business_id', $business_id);

            if (!empty($request->id)) {
                $purchase->where('transactions.invoice_no',"LIKE", "%$request->id%");
            }


            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (!empty($created_by)) {
                    $purchase->where('transactions.created_by', $created_by);
                }
            }

            $contact_id = request()->get('contact_id');
            if (!empty($contact_id)) {
                $purchase->where('transactions.contact_id', $contact_id);
            }

            $start = null;
            $end = null;

            if (!empty(request()->start_date)) {
                $start = Carbon::createFromFormat('d/m/Y', request()->start_date);

                $purchase->whereDate('transactions.created_at', '>=', $start);
            }

            if (!empty(request()->end_date)) {
                $end = Carbon::createFromFormat('d/m/Y', request()->end_date);

                $purchase->whereDate('transactions.created_at', '<=', $end);
            }

            $status = request()->status;

            if (!empty($status) && $status != "all") {
                $purchase->where('transactions.status', $status);
            }

            $payment_status = request()->payment_status;

            if (!empty($payment_status) && $payment_status != "all") {
                $purchase->where('transactions.payment_status', $payment_status);
            }

            $shipping_status = request()->shipping_status;

            if (!empty($shipping_status) && $shipping_status != "all") {
                $purchase->where('transactions.shipping_status', $shipping_status);
            }

            $receipt_status = request()->receipt_status;

            if (!empty($receipt_status) && $receipt_status != "all") {
                $purchase->where('transactions.receipt_status', $receipt_status);
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
            $purchase->select();


            $data = $purchase->paginate($request->limit);

            $summary = DB::table('transactions')
                ->select(DB::raw('sum(final_total) as final_total, count(*) as total, payment_status as status'))
                ->where('business_id', $business_id)
                ->where('location_id', $location_id)
                ->where('type', "sell")
                ->groupBy('payment_status');

            $contact_id = request()->get('contact_id');
            if (!empty($contact_id)) {
                $summary->where('contact_id', $contact_id);
            }

            if (!empty($receipt_status)) {
                $summary->where('transactions.receipt_status', $receipt_status);
            }

            if (!empty($start) && !empty($end)) {
                $start = Carbon::createFromFormat('d/m/Y', request()->start_date);
                $end = Carbon::createFromFormat('d/m/Y', request()->end_date);

                $summary->whereDate('transactions.transaction_date', '>=', $start)
                    ->whereDate('transactions.transaction_date', '<=', $end);
            }

            return $this->respondSuccess($data, null, ["summary" => $summary->get()]);
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
                ->where('transactions.status', 'approve')
                ->where('transactions.receipt_status', 'approve')
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

    public function createInit(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'contact_id' => 'required',
                'location_id' => 'required',
                'type' => 'required',
            ]);

            $business_id = Auth::guard('api')->user()->business_id;
            $contact_id = $request->contact_id;
            $location_id = $request->location_id;
            $type = $request->type;

            $contact = Contact::with([
                'business',
            ])
                ->where('contacts.id', $contact_id)
                ->first();


            $debt_list = Accountant::with([
                'transaction:id,invoice_no,created_by,business_id,tax_amount,total_before_tax,final_total,transaction_date,created_at',
            ])
                ->where('accountants.contact_id', $contact_id)
                ->where('accountants.business_id', $business_id)
                ->where('accountants.location_id', $location_id)
                ->where('accountants.type', $type)
                ->select([
                    "accountants.transaction_id",
                    "accountants.id",
                    "accountants.final_total",
                    "accountants.credit",
                    "accountants.debit",
                    "accountants.payment_expire",
                ])
                ->groupBy('accountants.transaction_id', 'accountants.id')
                ->havingRaw("SUM(accountants.debit) > 0")
                ->orderBy("accountants.payment_expire", "asc")
                ->get();

            $data = [
                "contact" => $contact,
                "debt_list" => $debt_list
            ];

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
//            dd($e);
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

            $input = $request->only([
                'contact_id',
                'total_pay',
                'payment_method'
            ]);

            $request->validate([
                'contact_id' => 'required',
                'payment_method' => 'required',
                'ids' => 'required',
                'final_total'=> 'required'
            ]);

            $contactId = $request->contact_id;
            $transactionIds = $request->ids;

            if (count($transactionIds) == 0) {
                DB::rollBack();
                $message = "Không tìm thấy đơn hàng nào cần được thanh toán";
                return $this->respondWithError($message, [], 500);
            }

            $result = Transaction::where('contact_id', $contactId)
                ->with([
                    'contact:id,first_name,mobile,email,tax_number,type',
                ])
                ->where('receipt_status', "approve")
                ->whereIn('id', $transactionIds)
                ->whereIn('payment_status', ['payment_pending'])
                ->get();


            if (!$result) {
                DB::rollBack();
                $message = "Xảy ra lỗi trong quá trình tạo chứng từ";
                return $this->respondWithError($message, [], 500);
            }

            foreach ($result as $transaction) {
                $note = "Thanh toán công nợ cho khách hàng " . $transaction->contact->name;

                $dataPaybill = [
                    'accountant_date' => now(),
                    'contact_id' => $contactId,
                    'credit' => $transaction->final_total,
                    'transaction_id' => $transaction->id,
                    'debit' => 0,
                    'fee' => 0,
                    'final_total' => $transaction->final_total,
                    'location_id' => $request->location_id,
                    'stock_id' => $request->stock_id,
                    'tax' => $transaction->tax_amount,
                    'total_before_tax' => $transaction->total_before_tax,
                    'note' => $note,
                    'payment_method' => $request->payment_method,
                    'type' => "credit",
                    "type_bill" => "receive",
                    'payment' => (object)[
                        "payment_no" => $transaction->invoice_no,
                        "note" => $note,
                    ],
                    'payment_lines' => [
                        (object)[
                            "amount" => $transaction->final_total,
                            "note" => $note,
                        ]
                    ],
                ];

                $resultPay = $this->paymentUtil->insertPayment($dataPaybill, $business_id, $user_id);

                if ($resultPay->status === false) {
                    DB::rollBack();
                    $message = $resultPay->msg ?? "Xảy ra lỗi trong quá trình tạo chứng từ";
                    return $this->respondWithError($message, [], 500);
                }

                $dataTransaction = [
                    'payment_status' => "payment_paid",
                    'payment_method' => $request->payment_method
                ];

                $result_pay_bill = Transaction::where('id', $transaction->id)
                    ->update($dataTransaction);

                if ($result_pay_bill === false) {
                    DB::rollBack();
                    $message = $resultPay->msg ?? "Xảy ra lỗi trong quá trình tạo chứng từ";
                    return $this->respondWithError($message, [], 500);
                }
            };

            DB::commit();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Thanh toán công nợ",
                'subject_id' => $contactId
            ];


            $message = "Thanh toán cho khách hàng có ID " . $contactId . "đ";
            Activity::history($message, "accountant_customer", $dataLog);


            return $this->respondSuccess($result);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function paymentAll(Request $request)
    {
        DB::beginTransaction();
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $request->validate([
                'contact_id' => 'required',
                'payment_method' => 'required',
            ]);

            $contactId = $request->contact_id;

            $result = Transaction::where('contact_id', $contactId)
                ->with([
                    'contact:id,first_name,mobile,email,tax_number,type',
                ])
                ->where('receipt_status', "approve")
                ->whereIn('payment_status', ['payment_pending'])
                ->get();

            if (!$result) {
                DB::rollBack();
                $message = "Xảy ra lỗi trong quá trình tạo chứng từ";
                return $this->respondWithError($message, [], 500);
            }

            foreach ($result as $transaction) {
                $note = "Thanh toán toàn bộ công nợ cho khách hàng " . $transaction->contact->name;
                $input = [
                    'contact_id' => $contactId,
                    'credit' => $transaction->final_total,
                    'debit' => 0,
                    'fee' => 0,
                    'final_total' => $transaction->final_total,
                    'location_id' => $request->location_id,
                    'stock_id' => $request->stock_id,
                    'tax' => $transaction->tax_amount,
                    'total_before_tax' => $transaction->total_before_tax,
                    'transaction_id' => $transaction->id,
                    'type' => "debit",
                    "type_bill" => "pay",
                    "payment_method" => $request->payment_method,
                    'payment' => (object)[
                        "payment_no" => $result->invoice_no,
                        "note" => $note,
                    ],
                    'payment_lines' => [
                        (object)[
                            "amount" => $transaction->final_total,
                            "note" => $note,
                        ]
                    ],
                ];

                $dataPaybill = [
                    'accountant_date' => now(),
                    'contact_id' => $contactId,
                    'credit' => $transaction->final_total,
                    'transaction_id' => $transaction->id,
                    'debit' => 0,
                    'fee' => 0,
                    'final_total' => $transaction->final_total,
                    'location_id' => $request->location_id,
                    'stock_id' => $request->stock_id,
                    'tax' => $transaction->tax_amount,
                    'total_before_tax' => $transaction->total_before_tax,
                    'note' => "Thanh toán công nợ cho khách hàng " . $transaction->contact->name,
                    'payment_method' => $request->payment_method,
                    'type' => "credit",
                    "type_bill" => "receive",
                ];

                $resultPay = $this->paymentUtil->insertPayment($dataPaybill, $business_id, $user_id);

                if ($resultPay->status === false) {
                    DB::rollBack();
                    $message = $resultPay->msg ?? "Xảy ra lỗi trong quá trình tạo chứng từ";
                    return $this->respondWithError($message, [], 500);
                }
            };

            DB::commit();

            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Thanh toán công nợ",
                'subject_id' => $contactId
            ];


            $message = "Thanh toán cho khách hàng có ID " . $contactId . "đ";
            Activity::history($message, "accountant_customer", $dataLog);


            return $this->respondSuccess($result);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }


    private function payDebtTransaction($input, $business_id, $user_id)
    {
        try {
            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;
            $input['status'] = "create";
            $input["accountant_date"] = now();

            $result = Accountant::create($input);

            if (!$result) {
                return (object)[
                    'status' => false,
                    'msg' => 'Không thể lưu chứng từ'
                ];
            }

            $ref_count = $this->productUtil->setAndGetReferenceCount("purchase_payment", $business_id);

            $location_id = $input["location_id"];
            $contact_id = $input["contact_id"];
            $transaction_id = $input["transaction_id"];
            $note = $input["note"];
            $payment_method = $input["payment_method"];
            $credit = $input["credit"];

            $dataPay = [
                "accountant_id" => $result->id,
                "created_by" => $user_id,
                "location_id" => $location_id,
                "contact_id" => $contact_id,
                "business_id" => $business_id,
                "transaction_id" => $transaction_id,
                "note" => $note,
                "method" => $payment_method,
                "status" => "received",
                "type" => "debit",
                "amount" => $credit,
                "ref_no" => "CT" . $ref_count,
                "created_at" => now(),
            ];

            $result_pay_bill = PayBill::create($dataPay);

            if (!$result_pay_bill) {
                return (object)[
                    'status' => false,
                    'msg' => "Không thể lưu phiếu thu"
                ];
            }

            // update payment
            $dataTransaction = [
                'payment_status' => "payment_paid",
                'payment_method' => $payment_method
            ];

            $result_pay_bill = Transaction::where('id', $transaction_id)
                ->update($dataTransaction);

            if (!$result_pay_bill) {
                return (object)[
                    'status' => false,
                    'msg' => 'Lỗi cập nhật trạng thái thanh toán'
                ];
            }

            return (object)[
                'status' => true,
                'msg' => $result
            ];
        } catch (\Exception $e) {
            return (object)[
                'status' => false,
                'msg' => $e->getMessage()
            ];
        }
    }

    public function detail($id)
    {
        DB::beginTransaction();
        try {
            $sells = Transaction::with([
                'contact',
                'payment_lines',
                'location',
                'business',
                'tax',
                'sales_person'
            ])
                ->where('id', $id)
                ->where('type', "sell")
                ->first();


            $sell_lines = TransactionSellLine::with([
                'product',
                'product.contact',
                'product.unit',
            ])
                ->where('transaction_id', $id)
                ->get();

            $data = [
                "order" => $sells,
                "sell_lines" => $sell_lines
            ];

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user()->can('sell.update')) {
            abort(403, 'Unauthorized action.');
        }

        //Check if the transaction can be edited or not.
        $edit_days = request()->session()->get('business.transaction_edit_days');
        if (!$this->transactionUtil->canBeEdited($id, $edit_days)) {
            return back()
                ->with('status', ['success' => 0,
                    'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days])]);
        }

        //Check if return exist then not allowed
        if ($this->transactionUtil->isReturnExist($id)) {
            return back()->with('status', ['success' => 0,
                'msg' => __('lang_v1.return_exist')]);
        }

        $business_id = request()->session()->get('user.business_id');

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $transaction = Transaction::where('business_id', $business_id)
            ->with(['price_group', 'types_of_service'])
            ->where('type', 'sell')
            ->findorfail($id);

        $location_id = $transaction->location_id;

        $location_printer_type = BusinessLocation::find($location_id)->receipt_printer_type;

        $sell_details = TransactionSellLine::
        join(
            'products AS p',
            'transaction_sell_lines.product_id',
            '=',
            'p.id'
        )
            ->join(
                'variations AS variations',
                'transaction_sell_lines.variation_id',
                '=',
                'variations.id'
            )
            ->join(
                'product_variations AS pv',
                'variations.product_variation_id',
                '=',
                'pv.id'
            )
            ->leftjoin('variation_location_details AS vld', function ($join) use ($location_id) {
                $join->on('variations.id', '=', 'vld.variation_id')
                    ->where('vld.location_id', '=', $location_id);
            })
            ->leftjoin('units', 'units.id', '=', 'p.unit_id')
            ->leftJoin('brands', 'p.brand_id', '=', 'brands.id')
            ->leftJoin('categories as c1', 'p.category_id', '=', 'c1.id')
            ->where('transaction_sell_lines.transaction_id', $id)
            ->with(['warranties'])
            ->select(
                DB::raw("IF(pv.is_dummy = 0, CONCAT(p.name, ' (', pv.name, ':',variations.name, ')'), p.name) AS product_name"),
                'p.id as product_id',
                'p.enable_stock',
                'p.name as product_actual_name',
                'p.sku as product_sku',
                'p.image as product_image',
                'pv.name as product_variation_name',
                'pv.is_dummy as is_dummy',
                'variations.name as variation_name',
                'variations.sub_sku',
                'p.barcode_type',
                'p.enable_sr_no',
                'c1.name as category',
                'brands.name as brand',
                'variations.id as variation_id',
                'units.short_name as unit',
                'units.allow_decimal as unit_allow_decimal',
                'transaction_sell_lines.tax_id as tax_id',
                'transaction_sell_lines.item_tax as item_tax',
                'transaction_sell_lines.purchase_price as purchase_price',
                'transaction_sell_lines.unit_price as default_sell_price',
                'transaction_sell_lines.unit_price_inc_tax as sell_price_inc_tax',
                'transaction_sell_lines.unit_price_before_discount as unit_price_before_discount',
                'transaction_sell_lines.id as transaction_sell_lines_id',
                'transaction_sell_lines.id',
                'transaction_sell_lines.quantity as quantity_ordered',
                'transaction_sell_lines.sell_line_note as sell_line_note',
                'transaction_sell_lines.lot_no_line_id',
                'transaction_sell_lines.line_discount_type',
                'transaction_sell_lines.line_discount_amount',
                'transaction_sell_lines.res_service_staff_id',
                'units.id as unit_id',
                'transaction_sell_lines.sub_unit_id',
                'stock_products.quantity as qty_stock',
                'stock_products.unit_price as unit_price',
                DB::raw('vld.qty_available + transaction_sell_lines.quantity AS qty_available')
            )
            ->get();
        if (!empty($sell_details)) {
            foreach ($sell_details as $key => $value) {
                if ($transaction->status != 'final') {
                    $actual_qty_avlbl = $value->qty_available - $value->quantity_ordered;
                    $sell_details[$key]->qty_available = $actual_qty_avlbl;
                    $value->qty_available = $actual_qty_avlbl;
                }

                $sell_details[$key]->formatted_qty_available = $this->transactionUtil->num_f($value->qty_available);
                $lot_numbers = [];
                if (request()->session()->get('business.enable_lot_number') == 1) {
                    $lot_number_obj = $this->transactionUtil->getLotNumbersFromVariation($value->variation_id, $business_id, $location_id);
                    foreach ($lot_number_obj as $lot_number) {
                        //If lot number is selected added ordered quantity to lot quantity available
                        if ($value->lot_no_line_id == $lot_number->purchase_line_id) {
                            $lot_number->qty_available += $value->quantity_ordered;
                        }

                        $lot_number->qty_formated = $this->transactionUtil->num_f($lot_number->qty_available);
                        $lot_numbers[] = $lot_number;
                    }
                }
                $sell_details[$key]->lot_numbers = $lot_numbers;

                if (!empty($value->sub_unit_id)) {
                    $value = $this->productUtil->changeSellLineUnit($business_id, $value);
                    $sell_details[$key] = $value;
                }

                $sell_details[$key]->formatted_qty_available = $this->transactionUtil->num_f($value->qty_available);
            }
        }

        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
        }

        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }
        $customer_groups = CustomerGroup::forDropdown($business_id);

        $transaction->transaction_date = $this->transactionUtil->format_date($transaction->transaction_date, true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $waiters = null;
        if ($this->productUtil->isModuleEnabled('service_staff') && !empty($pos_settings['inline_service_staff'])) {
            $waiters = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $invoice_schemes = [];
        $default_invoice_schemes = null;

        if ($transaction->status == 'draft') {
            $invoice_schemes = InvoiceScheme::forDropdown($business_id);
            $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        }

        $redeem_details = [];
        if (request()->session()->get('business.enable_rp') == 1) {
            $redeem_details = $this->transactionUtil->getRewardRedeemDetails($business_id, $transaction->contact_id);

            $redeem_details['points'] += $transaction->rp_redeemed;
            $redeem_details['points'] -= $transaction->rp_earned;
        }

        $edit_discount = auth()->user()->can('edit_product_discount_from_sale_screen');
        $edit_price = auth()->user()->can('edit_product_price_from_sale_screen');

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $common_settings = session()->get('business.common_settings');
        $is_warranty_enabled = !empty($common_settings['enable_product_warranty']) ? true : false;
        $warranties = $is_warranty_enabled ? Warranty::forDropdown($business_id) : [];

        // stock
        $lst_product = [];

        foreach ($sell_details as $product) {
            array_push($lst_product, $product->product_id);
        }

        $location_id = $transaction->location_id;

        $stocks = [];
        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);

        $locations = BusinessLocation::forDropdownParent(null, true);

        if (isset($location_id) && $location_id) {
            $stocks = Stock::forDropdownParent($location_id);

            if (count($stocks) === 1) {
                foreach ($stocks as $key => $stock) {
                    $stock_id = $key;
                }
            }

            if (count($stocks) === 0) {
                $business_location = BusinessLocation::where("id", $location_id)
                    ->first();

                if (
                    $business_location
                    && isset($business_location->parent_id)
                    && $business_location->parent_id
                ) {
                    $stocks = Stock::forDropdownParent($business_location->parent_id);

                    if (count($stocks) === 1) {
                        foreach ($stocks as $key => $stock) {
                            $stock_id = $key;
                        }
                    }
                }
            }
        }

        // create by
        $user_create = null;

        if (isset($transaction->user_created) && isset($transaction->user_created->first_name)) {
            $user_create = $transaction->user_created->first_name;
        }

        $user_edit = $request->session()->get('user.first_name');

        $stock = $stocks;

//        dd($sell_details);
        return view('sell.edit')
            ->with(compact(
                'business_details',
                'taxes',
                'sell_details',
                'transaction',
                'commission_agent',
                'types',
                'customer_groups',
                'pos_settings',
                'waiters',
                'invoice_schemes',
                'default_invoice_schemes',
                'redeem_details',
                'edit_discount',
                'edit_price',
                'accounts',
                'shipping_statuses',
                'warranties',
                'stock',
                'categories',
                'brands',
                'locations',
                'lst_product',
                'stock_id',
                'location_id',
                'user_create',
                'user_edit'
            ));
    }
}
