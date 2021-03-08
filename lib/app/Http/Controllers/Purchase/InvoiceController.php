<?php

namespace App\Http\Controllers\Purchase;

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
use App\PurchaseLine;
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

class InvoiceController extends Controller
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

            $invoices = Accountant::with([
                'contact',
                'transaction',
                'location',
                'business',
//                'employer_by',
//                'created_by'
            ]);

            $invoices->where('business_id', $business_id);

            $invoices->where('type', 'purchase');

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $invoices->whereIn('accountants.location_id', $permitted_locations);
            }

            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (!empty($created_by)) {
                    $invoices->where('accountants.created_by', $created_by);
                }
            }

            if (!auth()->user()->can('direct_sell.access') && auth()->user()->can('view_own_sell_only')) {
                $invoices->where('accountants.created_by', $user_id);
            }

            //Add condition for location,used in sales representative expense report
            if (request()->has('location_id')) {
                $location_id = request()->get('location_id');
                if (!empty($location_id)) {
                    $invoices->where('accountants.location_id', $location_id);
                }
            }

            if (!empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $invoices->where('accountants.contact_id', $customer_id);
            }
            if (!empty(request()->start_date) && !empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $invoices->whereDate('accountants.created_at', '>=', $start)
                    ->whereDate('accountants.created_at', '<=', $end);
            }

            $status = request()->status;

            if (!empty($status)) {
                $invoices->where('accountants.status', $status);
            }

            $static = $invoices->select([
                DB::raw("SUM(accountants.final_total) as total"),
                DB::raw("SUM(accountants.credit) as total_credit"),
                DB::raw("SUM(accountants.debit) as total_debit"),
                DB::raw("SUM( IF ( accountants.invoice_status = 'new', 1, 0 ) ) AS 'invoice_new'"),
                DB::raw("SUM( IF ( accountants.invoice_status = 'created', 1, 0 ) ) AS 'invoice_created'")
            ])->first();

            $invoices->orderBy('accountants.created_at', "desc");
            $invoices->groupBy('accountants.id');

            $invoices->select(
                'accountants.*'
            );

            $data = $invoices->paginate($request->limit);

            $res = array_merge($data->toArray(), ["summary" => $static]);

            return $this->respondSuccess($res);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function createDraft(Request $request)
    {
        DB::beginTransaction();
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $order_id = $request->order_id;
            $id = $request->id;

            $contact_id = $request->contact_id;
            $accountant = null;

            if($order_id) {
                $accountant = Accountant::where('contact_id', $contact_id)
                    ->where('transaction_id', $order_id)
                    ->orderBY("created_at", "desc")
                    ->first();
            }


            if($id) {
                $accountant = Accountant::where('id', $id)
                    ->first();
            }

            $transaction_id = $order_id;

            if($accountant && $accountant->transaction_id) {
                $transaction_id = $accountant->transaction_id;
            }


            $purchase_lines = PurchaseLine::with([
                'product:id,contact_id',
                'product.contact:id,name',
            ])
                ->where('purchase_lines.transaction_id', $transaction_id)
                ->select([
                    'purchase_lines.id',
                    'purchase_lines.product_id'
                ])->get();

            $data = [
                "purchase_lines" => $purchase_lines,
            ];

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function listProductForContact(Request $request)
    {
        DB::beginTransaction();
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $order_id = $request->order_id;
            $id = $request->id;

            $contact_id = $request->contact_id;

            if($order_id) {
                $accountant = Accountant::where('contact_id', $contact_id)
                    ->where('transaction_id', $order_id)
                    ->orderBY("created_at", "desc")
                    ->first();
            }


            if($id) {
                $accountant = Accountant::where('id', $id)
                    ->first();
            }

            $transaction_id = $order_id;

            if($accountant && $accountant->transaction_id) {
                $transaction_id = $accountant->transaction_id;
            }

            $purchase = Transaction::with([
                'contact',
                'payment_lines',
                'location',
                'business',
                'tax',
                'sales_person'
            ])
                ->where('id', $transaction_id)
                ->where('type', "purchase")
                ->first();

            $purchase_lines = PurchaseLine::with([
                'product:id,sku,name,image,contact_id,unit_id,type,tax,barcode_type',
                'product.contact:id,contact_id,name,supplier_business_name,mobile,email,address_line_1',
                'product.unit:id,actual_name,short_name',
            ])
                ->where('transaction_id', $transaction_id)
                ->whereHas('product.contact', function ($query) use ($contact_id) {
                    $query->where('id', $contact_id);
                });

            $result = $purchase_lines->get();


            $contact = Contact::where('id', $contact_id)
                ->select([
                    'id', 'contact_id', 'name', 'supplier_business_name', 'mobile', 'email', 'address_line_1'
                ])
                ->first();

            $invoice = InvoiceBill::where('contact_id', $contact_id)
                ->where('transaction_id', $transaction_id)
                ->first();

            $stock = StockBill::where('contact_id', $contact_id)
                ->where('transaction_id', $transaction_id)
                ->first();


            $data = [
                "accountant" => $accountant,
                "invoice" => $invoice,
                "stock" => $stock,
                "contact" => $contact,
                "order" => $purchase,
                "purchase_lines" => $result
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
                'employer_id',
                'contact_id',
                'invoice',
                'location_id',
                'payment',
                'stock',
                'tax',
                'fee',
                'final_total',
                'total',
                'credit',
                'debit',
                'transaction_id',
                'type',
                'payment_expire'
            ]);


            $request->validate([
                'contact_id' => 'required',
                'final_total' => 'required',
                'credit' => 'required',
                'debit' => 'required',
                'location_id' => 'required',
                'transaction_id' => 'required',
                'type' => 'required',
            ]);

            $total_final = $input["final_total"];
            $tax = $input["tax"];
            $total = $input["total"];
            $fee = $input["fee"];
            $credit = $input["credit"];
            $debit = $input["debit"];

            $transaction_id = $input["transaction_id"];
            $contact_id = $input["contact_id"];

            $checkId = Accountant::where("transaction_id", $transaction_id)
                ->where("contact_id", $contact_id)
                ->first();

            // invpice
            $invoice_status = "new";
            $invoice = null;

            if (isset($input["invoice"]) && $input["invoice"]) {
                $invoice = (object)$input["invoice"];
                if (isset($invoice->invoice_status) && $invoice->invoice_status) {
                    $invoice_status = $invoice->invoice_status;
                }

                if (isset($invoice->invoice_no) && $invoice->invoice_no) {
                    $checkUnit = InvoiceBill::where("ref_no", $invoice->invoice_no)
                        ->first();

                    if ($checkUnit && isset($checkUnit->id)) {
                        DB::rollBack();
                        $message = "Hóa đơn này đã tồn tại trong hệ thống!";
                        return $this->respondWithError($message, [], 404);
                    }
                } else {
                    DB::rollBack();
                    $message = "Vui lòng điền số hóa đơn";
                    return $this->respondWithError($message, [], 404);
                }
            }


            // payment
            $payment_status = "new";
            $payment = null;

            if (isset($input["payment"]) && $input["payment"]) {
                $payment = (object)$input["payment"];
                if (isset($payment->payment_status) && $payment->payment_status) {
                    $payment_status = $payment->payment_status;
                }
            }

            // delivery
            $delivery_status = "new";
            $delivery = null;

            if (isset($input["stock"]) && $input["stock"]) {
                $delivery = (object)$input["stock"];
                if (isset($delivery->delivery_status) && $delivery->delivery_status) {
                    $delivery_status = $delivery->delivery_status;
                }
            }

            $data_accountant = [
                "business_id" => $business_id,
                "location_id" => $input["location_id"],
                "contact_id" => $input["contact_id"],
                "transaction_id" => $input["transaction_id"],
                "total_before_tax" => $total,
                "final_total" => $total_final,
                "tax" => $tax,
                "fee" => $fee,
                "credit" => $credit,
                "debit" => $debit,
                "type" => $input["type"],
                "status" => "created",
                "invoice_status" => $invoice_status,
                "payment_status" => $payment_status,
                "delivery_status" => $delivery_status,
                "accountant_date" => now(),
                "employer_by" => $input["employer_id"],
                "created_by" => $user_id
            ];

            if (isset($input["payment_expire"]) && $input["payment_expire"]) {
                $data_accountant["payment_expire"] = Carbon::createFromFormat('d/m/Y', $input["payment_expire"]);
            }


            $accountant = null;

            if($checkId && isset($checkId->id) && $checkId->id) {
                $data_update = [
                    "fee" => $fee,
                    "credit" => DB::raw('credit + ' . $credit),
                    "debit" => $debit,
                    "invoice_status" => $invoice_status,
                    "payment_status" =>$payment_status,
                    "delivery_status" => $delivery_status,
                ];

                Accountant::where('id', $checkId->id)->update($data_update);
                $accountant = $checkId;
            } else {
                $result = Accountant::create($data_accountant);
                $accountant = $result;
            }

            if (!$accountant) {
                DB::rollBack();
                $message = "Xảy ra lỗi trong quá trình tạo chứng từ";
                return $this->respondWithError($message, [], 500);
            }

            // create payment
            if (isset($payment->payment_status)) {
                $dataPayInsert = [];

                $dataPay = [
                    "created_by" => $user_id,
                    "accountant_id" => $accountant->id,
                    "transaction_id" => $transaction_id,
                    "location_id" => $input["location_id"],
                    "contact_id" => $input["contact_id"],
                    "note" => $payment->note,
                    "business_id" => $business_id,
                    "status" => "received",
                    "created_at" => now(),
                ];

                if ($payment->create_date) {
                    $dataDebt["created_at"] = Carbon::createFromFormat('d/m/Y', $payment->create_date);
                }

                if ($payment->payment_date) {
                    $dataDebt["bill_date"] = Carbon::createFromFormat('d/m/Y', $payment->payment_date);
                }

                $prefix_type = 'purchase_payment';
                if (in_array($input["type"], ['purchase', 'purchase_return'])) {
                    $prefix_type = 'sell_payment';
                } elseif ($input["type"] == 'expense') {
                    $prefix_type = 'expense_payment';
                }

                $ref_count = $this->transactionUtil->setAndGetReferenceCount($prefix_type, $business_id);

                if (isset($payment->payment_no) && $payment->payment_no) {
                    $dataPay['ref_no'] = $payment->payment_no;
                } else {
                    $dataPay['ref_no'] = $this->transactionUtil->generateReferenceNumber($prefix_type, $ref_count);
                }

                if ($payment->payment_status === "debt") {
                    if (isset($payment->prepaid) && $payment->prepaid > 0 && $payment->prepaid <= $total_final) {
                        $dataCredit = $dataPay;
                        $dataCredit["amount"] = $payment->prepaid * -1;
                        $dataCredit["expire_date"] = "";
                        $dataCredit["number_expire"] = "";
                        $dataCredit["type"] = "debit";
                        $dataCredit["method"] = "cash";

                        array_push($dataPayInsert, $dataCredit);
                    }

                    if (isset($payment->amount) && $payment->amount > 0 && $payment->amount <= $total_final) {
                        $dataDebt = $dataPay;
                        $dataDebt["amount"] = $payment->amount;
                        $dataDebt["expire_date"] = Carbon::createFromFormat('d/m/Y', $payment->date_expire);
                        $dataDebt["number_expire"] = $payment->day_debt;
                        $dataDebt["type"] = "credit";
                        $dataDebt["method"] = "debt";


                        array_push($dataPayInsert, $dataDebt);
                    }

                    $result_pay_bill = PayBill::insert($dataPayInsert);

                    if (!$result_pay_bill) {
                        DB::rollBack();
                        $message = "Xảy ra lỗi trong quá trình thanh toán ghi nợ";
                        return $this->respondWithError($message, [], 500);
                    }
                }

                if (
                    $payment->payment_status === "cash"
                ) {
                    $dataPay["type"] = "debit";
                    $dataPay["method"] = "cash";
                    $dataPay["amount"] = $total_final * -1;

                    $result_pay_bill = PayBill::create($dataPay);

                    if (!$result_pay_bill) {
                        DB::rollBack();
                        $message = "Xảy ra lỗi trong quá trình thanh toán tiền mặt";
                        return $this->respondWithError($message, [], 500);
                    }
                }

                if ($payment->payment_status === "bank_transfer") {
                    $dataPay["type"] = "debit";
                    $dataPay["method"] = "bank_transfer";
                    $dataPay["amount"] = $total_final * -1;
                    $dataPay["account_id"] = null;

                    $result_pay_bill = PayBill::create($dataPay);

                    if (!$result_pay_bill) {
                        DB::rollBack();
                        $message = "Xảy ra lỗi trong quá trình thanh toán chuyển khoản";
                        return $this->respondWithError($message, [], 500);
                    }
                }
            }

            // create stock bill
            if (isset($delivery->delivery_status) && $delivery->delivery_status) {
                $latest = StockBill::latest()->first();
                $delivery_no = $delivery->delivery_no . "/000000";
                if ($latest) {
                    $no_id = "0000" . $latest->id;
                    $delivery_no = $delivery->delivery_no . "/" . substr($no_id, -5);
                }


                $dataStock = [
                    "created_by" => $user_id,
                    "accountant_id" => $accountant->id,
                    "transaction_id" => $transaction_id,
                    "contact_id" => $input["contact_id"],
                    "location_id" => $input["location_id"],
                    "note" => $delivery->note,
                    "business_id" => $business_id,
                    "status" => "pending",
                    "created_at" => now(),
                    "ref_no" => $delivery_no,
                    "type" => "export"
                ];

                if ($delivery->create_date) {
                    $dataStock["bill_date"] = Carbon::createFromFormat('d/m/Y', $delivery->create_date);
                }

                if ($delivery->delivery_date) {
                    $dataStock["delivery_date"] = Carbon::createFromFormat('d/m/Y', $delivery->delivery_date);
                }

                if (isset($delivery->stocker_id) && $delivery->stocker_id) {
                    $dataStock["stocker_by"] = $delivery->stocker_id;
                }

                if (isset($delivery->deliver_id) && $delivery->deliver_id) {
                    $dataStock["delivery_by"] = $delivery->deliver_id;
                }

                $stock_result = StockBill::create($dataStock);

                if (!$stock_result) {
                    DB::rollBack();
                    $message = "Xảy ra lỗi trong quá trình tạo phiếu xuất";
                    return $this->respondWithError($message, [], 500);
                }
            }

            // invoice
            if (isset($invoice->invoice_status) && $invoice->invoice_status) {
                $dataInvoice = [
                    "created_by" => $user_id,
                    "accountant_id" => $accountant->id,
                    "transaction_id" => $transaction_id,
                    "contact_id" => $input["contact_id"],
                    "location_id" => $input["location_id"],
                    "business_id" => $business_id,
                    "status" => $invoice->invoice_status,
                    "created_at" => now(),
                    "ref_no" => $invoice->invoice_no,
                    "type" => "VAT",
                    "total_final" => $total_final,
                    "total_before_tax" => $total_final,
                    "tax" => $input["tax"],
                ];

                if ($invoice->invoice_date) {
                    $dataInvoice["invoice_date"] = Carbon::createFromFormat('d/m/Y', $invoice->invoice_date);
                }

                $invoice_result = InvoiceBill::create($dataInvoice);

                if (!$invoice_result) {
                    DB::rollBack();
                    $message = "Xảy ra lỗi trong quá trình tạo hóa đơn";
                    return $this->respondWithError($message, [], 500);
                }
            }

            DB::commit();

            $data = [
                "id" => $accountant->transaction_id,
                "data" => $accountant
            ];

            $message = "Thêm chứng từ thành công";

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
    public function detail($id)
    {
        DB::beginTransaction();
        try {
            $purchase = Transaction::with([
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


            $purchase_lines = TransactionSellLine::with([
                'product',
                'product.contact',
                'product.unit',
            ])
                ->where('transaction_id', $id)
                ->get();

            $data = [
                "order" => $purchase,
                "purchase_lines" => $purchase_lines
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
            ->where('type', 'purchase')
            ->findorfail($id);

        $location_id = $transaction->location_id;
        $stock_id = $transaction->stock_id;

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
            ->leftJoin('stock_products', function ($join) use ($stock_id) {
                $join->on('stock_products.product_id', '=', 'p.id')
                    ->where('stock_products.stock_id', '=', $stock_id);
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
        $stock_id = $transaction->stock_id;

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
