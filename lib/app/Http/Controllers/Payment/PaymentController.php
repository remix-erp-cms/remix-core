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
            $user_id = Auth::guard('api')->user()->id;

            $payment_lines = PayBill::with([
                'contact:id,name,mobile',
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
                $query = "1 = 1";
                if ($request->type == "receive") {
                    $query = "
                    (
                    accountants.type = 'receive' 
                    OR ( accountants.type = 'sell' AND pay_bills.type = 'credit' ) 
                    )";
                }

                if ($request->type == "pay") {
                    $query = "
                        (
                            accountants.type = 'pay'
                            OR ( accountants.type = 'purchase' AND pay_bills.type = 'debit' )
                        ) ";
                }

                if ($request->type == "bank_receive") {
                    $query = "
                    (
                    accountants.type = 'bank_receive' 
                    OR ( accountants.type = 'sell' AND pay_bills.type = 'credit' ) 
                    )";
                }

                if ($request->type == "bank_pay") {
                    $query = "
                        (
                            accountants.type = 'bank_pay'
                            OR ( accountants.type = 'purchase' AND pay_bills.type = 'debit' )
                        ) ";
                }


                $payment_lines->whereRaw(DB::raw($query));
            }

            if (isset($request->payment_method) && $request->payment_method) {
                $payment_lines->where('pay_bills.method', $request->payment_method);
            }

            $location_id = $request->location_id;
            if ($location_id != 'all') {
                $payment_lines->where('pay_bills.location_id', $location_id);
            }

            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (!empty($created_by)) {
                    $payment_lines->where('pay_bills.created_by', $created_by);
                }
            }

            if (!empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $payment_lines->where('pay_bills.contact_id', $customer_id);
            }

            $start_date = Carbon::now()->startOfMonth();
            $end_date = Carbon::now()->endOfMonth();

            if (!empty($request->start_date)) {
                $start_date = $request->start_date;
            }

            if (!empty($request->end_date)) {
                $end_date = $request->end_date;
            }

            $payment_lines->whereDate('pay_bills.created_at', '>=', $start_date)
                ->whereDate('pay_bills.created_at', '<=', $end_date);

            $status = request()->status;

            if (!empty($status)) {
                $payment_lines->where('pay_bills.status', $status);
            }

            $payment_lines->select(
                'pay_bills.*'
            );

            $payment_lines->orderBy('pay_bills.created_at', 'desc');

//            dd($payment_lines->toSql());
            $data = $payment_lines->paginate($request->limit);

            $static = PayBill::where('pay_bills.business_id', $business_id)
                ->with([
                    'contact:id,name,mobile',
                    'accountant:id,accountant_no,fee,tax,final_total,credit,debit,type'
                ])
                ->join(
                    "accountants",
                    "accountants.id",
                    "=",
                    "pay_bills.accountant_id"
                )
                ->select(
                    [
                        DB::raw("pay_bills.type, SUM(pay_bills.amount) as total")
                    ]
                )
                ->groupBy('pay_bills.type');

            if (isset($request->type) && $request->type) {
                $query = "1 = 1";
                if ($request->type == "receive") {
                    $query = "
                    (
                    accountants.type = 'receive' 
                    OR ( accountants.type = 'sell' AND pay_bills.type = 'credit' ) 
                    )";
                }

                if ($request->type == "pay") {
                    $query = "
                        (
                            accountants.type = 'pay'
                            OR ( accountants.type = 'purchase' AND pay_bills.type = 'debit' )
                        ) ";
                }

                if ($request->type == "bank_receive") {
                    $query = "
                    (
                    accountants.type = 'bank_receive' 
                    OR ( accountants.type = 'sell' AND pay_bills.type = 'credit' ) 
                    )";
                }

                if ($request->type == "bank_pay") {
                    $query = "
                        (
                            accountants.type = 'bank_pay'
                            OR ( accountants.type = 'purchase' AND pay_bills.type = 'debit' )
                        ) ";
                }


                $static->whereRaw(DB::raw($query));
            }

            if (isset($request->payment_method) && $request->payment_method) {
                $static->where('pay_bills.method', $request->payment_method);
            }

            $location_id = $request->location_id;
            if ($location_id != 'all') {
                $static->where('pay_bills.location_id', $location_id);
            }

            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (!empty($created_by)) {
                    $static->where('pay_bills.created_by', $created_by);
                }
            }

            if (!empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $static->where('pay_bills.contact_id', $customer_id);
            }

            $start_date = Carbon::now()->startOfMonth();
            $end_date = Carbon::now()->endOfMonth();

            if (!empty($request->start_date)) {
                $start_date = $request->start_date;
            }

            if (!empty($request->end_date)) {
                $end_date = $request->end_date;
            }

            $static->whereDate('pay_bills.created_at', '>=', $start_date)
                ->whereDate('pay_bills.created_at', '<=', $end_date);

            $status = request()->status;

            if (!empty($status)) {
                $static->where('pay_bills.status', $status);
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

            $payment = PayBill::with([])
                ->join(
                    "accountants",
                    "accountants.id",
                    "=",
                    "pay_bills.accountant_id"
                );

            $payment->where('pay_bills.business_id', $business_id);

            $location_id = null;
            //Add condition for location,used in sales representative expense report
            if (isset($request->location_id) && $request->location_id) {
                $location_id = $request->location_id;
            }

            $payment->where('accountants.location_id', $location_id);

            $start_date = Carbon::now()->startOfMonth();
            $end_date = Carbon::now()->endOfMonth();

            if (!empty(request()->start_date) && !empty(request()->end_date)) {
                $start_date = request()->start_date;
                $end_date = request()->end_date;
            }

            $payment->whereDate('pay_bills.created_at', '>=', $start_date)
                ->whereDate('pay_bills.created_at', '<=', $end_date);

            $status = request()->status;

            if (!empty($status)) {
                $payment->where('pay_bills.status', $status);
            }

            $payment_method = null;

            if (isset($request->payment_method) && $request->payment_method) {
                $payment_method = $request->payment_method;
                $payment->where('pay_bills.method', $payment_method);
            }

            $filenameSql = __DIR__ . '/sql/inventory.sql';
            $table = [];

            if (File::exists($filenameSql)) {
                $queryString = File::get($filenameSql);
                $queryString = str_replace('$location_id', $location_id, $queryString);

                $queryString = str_replace('$start_date', $start_date, $queryString);
                $queryString = str_replace('$end_date', $end_date, $queryString);
                $queryString = str_replace('$payment_method', $payment_method, $queryString);

                $queryString = str_replace('$accountant_type_receive', "sell", $queryString);
                $queryString = str_replace('$payment_type_receive', "debit", $queryString);

                $queryString = str_replace('$accountant_type_pay', "purchase", $queryString);
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
                OR ( accountants.type = 'purchase' AND pay_bills.type = 'debit' ) 
                OR ( accountants.type = 'sell' AND pay_bills.type = 'credit' ) 
	            )
            ";

            $payment->whereRaw(DB::raw($query));

            $payment->groupBy('accountants.type');
            $summary = $payment->select([
                'accountants.type',
                DB::raw("SUM(pay_bills.amount) as total")
            ])->get();

//            dd($summary);

            $res = [
                'data' => $table,
                'summary' => $summary,
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

            $input = $request->only([
                'contact_id',
                'location_id',
                'final_total',
                'credit',
                'debit',
                'payment',
                'payment_lines',
                'type',
                "type_bill",
                "payment_method"
            ]);


            $request->validate([
                'contact_id' => 'required',
                'location_id' => 'required',
                'final_total' => 'required',
                'credit' => 'required',
                'debit' => 'required',
                'payment' => 'required',
                'payment_lines' => 'required',
                'type' => 'required',
                'type_bill' => 'required',
                'payment_method' => 'required',
            ]);

            $contact_id = $input["contact_id"];
            $location_id = $input["location_id"];

            $total_final = $input["final_total"];
            $credit = $input["credit"];
            $debit = $input["debit"];
            $type = $input["type"];
            $type_bill = $input["type_bill"];
            $payment_method = $input["payment_method"];
            $payment_lines = $input["payment_lines"];
            $account_id = null;
            $payment = (object)$input["payment"];

            $data_accountant = [
                "business_id" => $business_id,
                "location_id" => $location_id,
                "contact_id" => $contact_id,
                "total_before_tax" => $total_final,
                "final_total" => $total_final,
                "credit" => $credit,
                "debit" => $debit,
                "type" => $type_bill,
                "status" => "created",
                "accountant_date" => now(),
                "created_by" => $user_id
            ];

            $result = Accountant::create($data_accountant);

            if (!$result) {
                DB::rollBack();
                $message = "Xảy ra lỗi trong quá trình tạo chứng từ";
                return $this->respondWithError($message, [], 500);
            }

            if (count($payment_lines) === 0) {
                DB::rollBack();
                $message = "Xảy ra lỗi trong quá trình tạo chứng từ";
                return $this->respondWithError($message, [], 500);
            }

            $dataPayInsert = [];

            if ($input["type_bill"] === "pay") {
                $total_final = $total_final * -1;
            }

            $dataPay = [
                "accountant_id" => $result->id,
                "created_by" => $user_id,
                "location_id" => $location_id,
                "contact_id" => $contact_id,
                "business_id" => $business_id,
                "status" => "received",
                "method" => $payment_method,
                "type" => $type,
                "account_id" => $account_id,
                "amount" => $total_final,
            ];

            if (isset($payment->note) && $payment->note) {
                $dataPay["note"] = $payment->note;
            }

            if ($payment->create_date) {
                $dataPay["created_at"] = Carbon::createFromFormat('d/m/Y', $payment->create_date);
            }

            if ($payment->payment_date) {
                $dataPay["bill_date"] = Carbon::createFromFormat('d/m/Y', $payment->payment_date);
            }

            $prefix_type = $type_bill;

            $ref_count = $this->transactionUtil->setAndGetReferenceCount($prefix_type, $business_id);

            if (isset($payment->payment_no) && $payment->payment_no) {
                $dataPay['ref_no'] = $payment->payment_no;
            } else {
                $dataPay['ref_no'] = $this->transactionUtil->generateReferenceNumber($prefix_type, $ref_count);
            }


            $result_pay_bill = PayBill::create($dataPay);

            if (!$result_pay_bill) {
                DB::rollBack();
                $message = "Xảy ra lỗi trong quá trình thanh toán";
                return $this->respondWithError($message, [], 500);
            }

            foreach ($payment_lines as $line) {
                $payLines = (object)$line;

                $amount = $payLines->amount;
                $type_payment = "debit";

                if ($amount >= 0) {
                    $type_payment = "credit";
                }

                $dataCash = [
                    "parent_id" => $result_pay_bill->id,
                    "status" => "received",
                    "method" => $payment_method,
                    "type" => $type_payment,
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
                DB::rollBack();
                $message = "Xảy ra lỗi trong quá trình thanh toán ghi nợ";
                return $this->respondWithError($message, [], 500);
            }

            DB::commit();

            $data = [
                "id" => $result_pay_bill->id,
                "data" => $result_pay_bill
            ];

            $message = "Thêm phiếu thành công";

            return $this->respondSuccess($data, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

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
            $payment = PayBill::with([
                'accountant',
                'account',
                'transaction',
                'contact',
                'created_by',
                'parent',
                'child'
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

    public function report(Request $request)
    {
        try {

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $payment_lines = PayBill::with([
                'contact:id,name,mobile',
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
}
