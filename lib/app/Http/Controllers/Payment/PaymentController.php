<?php

namespace App\Http\Controllers\Payment;

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
use Illuminate\Support\Facades\File;
use Yajra\DataTables\Facades\DataTables;
use \Carbon\Carbon;

class PaymentController extends Controller
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

            $payments = Accountant::where('business_id', $business_id)
                ->with([
                    'contact:id,first_name,mobile'
                ]);

            if (isset($request->type) && $request->type) {
                $payments->where('type', $request->type);
            }

            if (isset($request->location_id) && $request->location_id) {
                $payments->where('location_id', $request->location_id);
            }

            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (!empty($created_by)) {
                    $payments->where('created_by', $created_by);
                }
            }

            if (!empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $payments->where('contact_id', $customer_id);
            }

            $start_date = null;
            $end_date = null;

            if (!empty($request->start_date)) {
                $start_date = $request->start_date;
                $payments->whereDate('created_at', '>=', $start_date);
            }

            if (!empty($request->end_date)) {
                $end_date = $request->end_date;
                $payments->whereDate('created_at', '<=', $end_date);
            }

            $status = request()->status;

            if (!empty($status)) {
                $payments->where('status', $status);
            }

            $payments->orderBy('created_at', 'desc');
            $data = $payments->paginate($request->limit);

            $static = Accountant::where('business_id', $business_id)
                ->select(
                    [
                        DB::raw("type, SUM(final_total) as total")
                    ]
                )
                ->groupBy('type');

            if (isset($request->location_id) && $request->location_id) {
                $static->where('location_id', $request->location_id);
            }

            if (isset($request->type) && $request->type) {
                $static->where('type', $request->type);
            }

            if (!empty($request->start_date)) {
                $start_date = $request->start_date;
                $static->whereDate('created_at', '>=', $start_date);
            }

            if (!empty($request->end_date)) {
                $end_date = $request->end_date;
                $static->whereDate('created_at', '<=', $end_date);
            }

            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (!empty($created_by)) {
                    $static->where('created_by', $created_by);
                }
            }

            if (!empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $static->where('contact_id', $customer_id);
            }

            $status = request()->status;

            if (!empty($status)) {
                $static->where('status', $status);
            }

            return $this->respondSuccess($data, null, ["summary" => $static->get()]);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function inventory(Request $request)
    {
        try {

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $payment = Accountant::with([]);

            $payment->where('business_id', $business_id);

            $location_id = null;
            //Add condition for location,used in sales representative expense report
            if (isset($request->location_id) && $request->location_id) {
                $location_id = $request->location_id;
                $payment->where('location_id', $request->location_id);
            }

            $start_date = Carbon::today()->startOfMonth();
            $end_date = Carbon::today()->endOfMonth();

            if (!empty($request->start_date)) {
                $start_date = $request->start_date;
            }

            if (!empty($request->end_date)) {
                $end_date = $request->end_date;
            }

            $payment->whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date);

            $status = request()->status;

            if (!empty($status)) {
                $payment->where('status', $status);
            }

            $filenameSql = __DIR__ . '/sql/inventory.sql';
            $table = [];

            if (File::exists($filenameSql)) {
                $queryString = File::get($filenameSql);

                $queryString = str_replace('$start_date', $start_date, $queryString);
                $queryString = str_replace('$end_date', $end_date, $queryString);

                $queryString = str_replace('$payment_type_receive', "debit", $queryString);
                $queryString = str_replace('$location_id', $location_id, $queryString);

                $queryString = str_replace('$payment_type_pay', "credit", $queryString);

                $result = DB::select($queryString);

                if ($result && is_array($result)) {
                    $table = $result;
                }
            }

            // receive
            $query = "
            	(
                accountants.type = 'pay' 
                OR accountants.type = 'receive'
	            )
            ";

            $payment->whereRaw(DB::raw($query));

            $payment->groupBy('type');
            $summary = $payment->select([
                'type',
                DB::raw("SUM(accountants.final_total) as total")
            ])->get();

//            dd($summary);

            $summaryMoney = Accountant::where('location_id', $request->location_id)
                ->select([
                    DB::raw("SUM(final_total) as money_total")
                ])
                ->first();

            $totalMoney = 0;

            if(isset($summaryMoney->money_total) && $summaryMoney->money_total) {
                $totalMoney = $summaryMoney->money_total;
            }


            $res = [
                'data' => $table,
                'summary' => $summary,
                'total_money' => $totalMoney,
            ];

            return $this->respondSuccess($res);
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
            $business_id = Auth::guard('api')->user()->business_id;
            $contact_id = $request->contact_id;

            $contact = Contact::with([
                'business',
            ])
                ->where('contacts.id', $contact_id)
                ->first();


            $debt_list = PayBill::with([
                'transaction:id,invoice_no,created_by,business_id,tax_amount,total_before_tax,final_total,transaction_date,created_at',
                'transaction.sales_person:id,first_name,contact_number,email',
            ])
                ->where('pay_bills.contact_id', $contact_id)
                ->select([
                    "pay_bills.transaction_id",
                    DB::raw("SUM(pay_bills.amount) as total_debit")
                ])
                ->groupBy('pay_bills.contact_id')
                ->havingRaw("SUM(pay_bills.amount) < 0")
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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        DB::beginTransaction();
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $location_id = $request->location_id;

            $input = $request->only([
                'contact_id',
                'final_total',
                'total_before_tax',
                'credit',
                'debit',
                'payment',
                'payment_lines',
                'type',
                "type_bill",
                "payment_method",
                "location_id"
            ]);


            $request->validate([
                'contact_id' => 'required',
                'final_total' => 'required',
                'credit' => 'required',
                'debit' => 'required',
                'payment' => 'required',
                'payment_lines' => 'required',
                'type' => 'required',
                'type_bill' => 'required',
                'payment_method' => 'required',
            ]);

            $input["tax"] = 0;
            $input["location_id"] = $location_id;

            $result = $this->insertPayment($input, $business_id, $user_id);

            if ($result->status === false) {
                DB::rollBack();
                return $this->respondWithError($result->message, [], 500);
            }

            DB::commit();
            return $this->respondSuccess($result->data, $result->message);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();

            return $this->respondWithError($message, [], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function detail(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $payment = Accountant::with([
                'pay_bills',
                'contact',
                'created_by'
            ])
                ->where('id', $id);

            if (isset($request->type) && $request->type) {
                $payment->where('type', $request->type);
            }

            $data = $payment->first();

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $location_id = $request->location_id;

            $input = $request->only([
                'contact_id',
                'final_total',
                'total_before_tax',
                'credit',
                'debit',
                'payment',
                'payment_lines',
                'type',
                "type_bill",
                "payment_method",
                "location_id"
            ]);


            $request->validate([
                'contact_id' => 'required',
                'final_total' => 'required',
                'credit' => 'required',
                'debit' => 'required',
                'payment' => 'required',
                'payment_lines' => 'required',
                'type' => 'required',
                'type_bill' => 'required',
                'payment_method' => 'required',
            ]);

            $input["tax"] = 0;
            $input["location_id"] = $location_id;


            $result = $this->updatePayment($id, $input, $business_id, $user_id);

            if ($result->status === false) {
                DB::rollBack();
                return $this->respondWithError($result->message, [], 500);
            }

            DB::commit();
            return $this->respondSuccess($result->data, $result->message);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function report(Request $request)
    {
        try {

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $payment_lines = PayBill::with([
                'contact:id,first_name,mobile',
                'accountant:id,accountant_no,fee,tax,final_total,credit,debit,type'
            ])
                ->join(
                    "accountants",
                    "accountants.id",
                    "=",
                    "pay_bills.accountant_id"
                );

            $payment_lines->where('pay_bills.business_id', $business_id);

            if (isset($request->type) && $request->type) {
                $payment_lines->where('accountants.type', $request->type);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $payment_lines->whereIn('pay_bills.location_id', $permitted_locations);
            }

            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (!empty($created_by)) {
                    $payment_lines->where('pay_bills.created_by', $created_by);
                }
            }

            if (!auth()->user()->can('direct_sell.access') && auth()->user()->can('view_own_sell_only')) {
                $payment_lines->where('pay_bills.created_by', $user_id);
            }

            //Add condition for location,used in sales representative expense report
            if (request()->has('location_id')) {
                $location_id = request()->get('location_id');
                if (!empty($location_id)) {
                    $payment_lines->where('pay_bills.location_id', $location_id);
                }
            }

            if (!empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $payment_lines->where('pay_bills.contact_id', $customer_id);
            }

            $start_date = Carbon::now()->startOfMonth();
            $end_date = Carbon::now()->endOfMonth();

            if (!empty(request()->start_date) && !empty(request()->end_date)) {
                $start_date = request()->start_date;
                $end_date = request()->end_date;
            }

            $payment_lines->whereDate('pay_bills.created_at', '>=', $start_date)
                ->whereDate('pay_bills.created_at', '<=', $end_date);

            $status = request()->status;

            if (!empty($status)) {
                $payment_lines->where('pay_bills.status', $status);
            }

            $static = $payment_lines->select([
                DB::raw("SUM(pay_bills.amount) as total")
            ])->first();

            $payment_lines->select(
                'pay_bills.*'
            );

            $payment_lines->orderBy('pay_bills.created_at', 'desc');

            $data = $payment_lines->paginate($request->limit);

            $res = array_merge($data->toArray(), ["summary" => $static->total]);

            return $this->respondSuccess($res);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function insertPayment($input, $business_id, $user_id)
    {
        try {
            $contact_id = $input["contact_id"];
            $location_id = $input["location_id"];

            $total_final = $input["final_total"];
            $total_before_tax = $input["total_before_tax"];
            $tax = $input["tax"];
            $credit = $input["credit"];
            $debit = $input["debit"];
            $type = $input["type"];
            $type_bill = $input["type_bill"];
            $payment_method = $input["payment_method"];
            $payment_lines = $input["payment_lines"];
            $account_id = null;
            $payment = (object)$input["payment"];

            if ($type_bill === "pay") {
                $total_final = $total_final * -1;
            }

            $data_accountant = [
                "business_id" => $business_id,
                "location_id" => $location_id,
                "contact_id" => $contact_id,
                "total_before_tax" => $total_before_tax,
                "final_total" => $total_final,
                "tax" => $tax,
                "credit" => $credit,
                "debit" => $debit,
                "type" => $type_bill,
                "status" => "created",
                "accountant_date" => now(),
                "created_by" => $user_id
            ];

            if (isset($input["transaction_id"]) && $input["transaction_id"]) {
                $data_accountant["transaction_id"] = $input["transaction_id"];
            }

            if (isset($payment->note) && $payment->note) {
                $data_accountant["note"] = $payment->note;
            }

            if (isset($payment->create_date) && $payment->create_date) {
                $data_accountant["created_at"] = Carbon::createFromFormat('d/m/Y', $payment->create_date);
            }

            if (isset($payment->payment_date) && $payment->payment_date) {
                $data_accountant["accountant_date"] = Carbon::createFromFormat('d/m/Y', $payment->payment_date);
            }

            if (isset($payment->payment_no) && $payment->payment_no) {
                $data_accountant['accountant_no'] = $payment->payment_no;
            }

            $result = Accountant::create($data_accountant);

            if (!$result) {
                $message = "Xảy ra lỗi trong quá trình tạo chứng từ";
                throw new \Exception($message);
            }

            if (count($payment_lines) === 0) {
                $message = "Xảy ra lỗi trong quá trình tạo chứng từ";
                throw new \Exception($message);
            }

            $dataPayInsert = [];

            foreach ($payment_lines as $line) {
                $payLines = (object)$line;

                $dataCash = [
                    "accountant_id" => $result->id,
                    "status" => "received",
                    "method" => $payment_method,
                    "type" => $type,
                    "amount" => $payLines->amount,
                    "note" => $payLines->note,
                    "created_at" => now(),
                    "created_by" => $user_id,
                ];

                if (isset($payLines->account_id) && $payLines->account_id) {
                    $dataCash["account_id"] = $payLines->account_id;
                }

                if (isset($payLines->transaction_id) && $payLines->transaction_id) {
                    $dataCash["transaction_id"] = $payLines->transaction_id;
                }

                if (isset($payLines->transaction_id) && $payLines->transaction_id) {
                    $dataCash["transaction_id"] = $payLines->transaction_id;
                }

                array_push($dataPayInsert, $dataCash);
            }

            $result_pay_bill_child = PayBill::insert($dataPayInsert);

            if (!$result_pay_bill_child) {
                $message = "Xảy ra lỗi trong quá trình thanh toán ghi nợ";
                throw new \Exception($message);
            }

            $data = [
                "id" => $result->id,
                "data" => $result
            ];

            $message = "Thêm phiếu thành công";

            return (object)[
                'status' => true,
                'data' => $data,
                'message' => $message
            ];
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return (object)[
                'status' => false,
                'message' => $message
            ];
        }
    }

    public function updatePayment($id, $input, $business_id, $user_id)
    {
        try {
            if (!$id) {
                $message = "Vui lòng nhập một hóa đơn";
                throw new \Exception($message);
            }

            $contact_id = $input["contact_id"];
            $location_id = $input["location_id"];

            $total_final = $input["final_total"];
            $total_before_tax = $input["total_before_tax"];
            $tax = $input["tax"];
            $credit = $input["credit"];
            $debit = $input["debit"];
            $type = $input["type"];
            $type_bill = $input["type_bill"];
            $payment_method = $input["payment_method"];
            $payment_lines = $input["payment_lines"];
            $account_id = null;
            $payment = (object)$input["payment"];


            if ($type_bill === "pay") {
                $total_final = $total_final * -1;
            }

            $data_accountant = [
                "business_id" => $business_id,
                "location_id" => $location_id,
                "contact_id" => $contact_id,
                "total_before_tax" => $total_before_tax,
                "final_total" => $total_final,
                "tax" => $tax,
                "credit" => $credit,
                "debit" => $debit,
                "type" => $type_bill,
                "status" => "created",
                "accountant_date" => now(),
                "created_by" => $user_id
            ];

            if (isset($input["transaction_id"]) && $input["transaction_id"]) {
                $data_accountant["transaction_id"] = $input["transaction_id"];
            }

            if (isset($payment->note) && $payment->note) {
                $data_accountant["note"] = $payment->note;
            }

            if (isset($payment->create_date) && $payment->create_date) {
                $data_accountant["created_at"] = Carbon::createFromFormat('d/m/Y', $payment->create_date);
            }

            if (isset($payment->payment_date) && $payment->payment_date) {
                $data_accountant["accountant_date"] = Carbon::createFromFormat('d/m/Y', $payment->payment_date);
            }

            if (isset($payment->payment_no) && $payment->payment_no) {
                $data_accountant['accountant_no'] = $payment->payment_no;
            }

            $result = Accountant::where("id", $id)
                ->update($data_accountant);

            if (!$result) {
                $message = "Xảy ra lỗi trong quá trình tạo chứng từ";
                throw new \Exception($message);
            }

            if (count($payment_lines) === 0) {
                $message = "Xảy ra lỗi trong quá trình tạo chứng từ";
                throw new \Exception($message);
            }

            foreach ($payment_lines as $line) {
                $payLines = (object)$line;

                $dataCash = [
                    "accountant_id" => $id,
                    "status" => "received",
                    "method" => $payment_method,
                    "type" => $type,
                    "amount" => $payLines->amount,
                    "note" => $payLines->note,
                    "created_at" => now(),
                    "created_by" => $user_id,
                ];

                if (isset($payLines->account_id) && $payLines->account_id) {
                    $dataCash["account_id"] = $payLines->account_id;
                }

                if (isset($payLines->transaction_id) && $payLines->transaction_id) {
                    $dataCash["transaction_id"] = $payLines->transaction_id;
                }

                if (isset($payLines->transaction_id) && $payLines->transaction_id) {
                    $dataCash["transaction_id"] = $payLines->transaction_id;
                }

                $result_pay_bill_child = PayBill::where("id", $payLines->id)->update($dataCash);

                if (!$result_pay_bill_child) {
                    $message = "Xảy ra lỗi trong quá trình thanh toán ghi nợ";
                    throw new \Exception($message);
                }
            }

            $data = [
                "id" => $id,
                "data" => $result
            ];

            $message = "Cập nhật hóa đơn thành công";

            return (object)[
                'status' => true,
                'data' => $data,
                'message' => $message
            ];
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return (object)[
                'status' => false,
                'message' => $message
            ];
        }
    }

    public function delete(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $accountant = Accountant::findOrFail($id);

            $result = $accountant->delete();

            if (!$result) {
                DB::rollBack();
                $message = "Xảy ra lỗi trong quá trình xóa giao dịch";
                return $this->respondWithError($message, [], 500);
            }


            DB::commit();
            $message = "Xóa phiếu thành công";

            return $this->respondSuccess($accountant, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }
}
