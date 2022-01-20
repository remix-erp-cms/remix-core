<?php

namespace App\Http\Controllers\Contact;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\Http\Controllers\Controller;
use App\Notifications\CustomerNotification;
use App\PurchaseLine;
use App\System;
use App\Transaction;
use App\User;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\NotificationUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class AjaxController extends Controller
{
    protected $commonUtil;
    protected $contactUtil;
    protected $transactionUtil;
    protected $moduleUtil;
    protected $notificationUtil;

    /**
     * Constructor
     *
     * @param Util $commonUtil
     * @return void
     */
    public function __construct(
        Util $commonUtil,
        ModuleUtil $moduleUtil,
        TransactionUtil $transactionUtil,
        NotificationUtil $notificationUtil,
        ContactUtil $contactUtil
    )
    {
        $this->commonUtil = $commonUtil;
        $this->contactUtil = $contactUtil;
        $this->moduleUtil = $moduleUtil;
        $this->transactionUtil = $transactionUtil;
        $this->notificationUtil = $notificationUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function list(Request $request)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $type = isset($request->type) && $request->type ? $request->type : null;

            $contact = Contact::with([
                'business',
                'groups',
                'user',
                'user.roles'
            ])
                ->where('contacts.business_id', $business_id)
                ->where('contacts.type', $type)
                ->orderBy('contacts.created_at', "desc")
                ->groupBy('contacts.id');


            if (isset($request->status) && $request->status) {
                $contact->where('contacts.contact_status', $request->status);
            }

            if (isset($request->keyword) && $request->keyword) {
                $contact->where('contacts.name', "like", "%" . $request->keyword . "%");
            }

            $contact->select();
            $contact->orderBy('created_at', "desc");
            $data = $contact->paginate($request->limit);

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    /**
     * Returns the database object for supplier
     *
     * @return \Illuminate\Http\Response
     */
    public function listSupplier(Request $request)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;

            $contact = Contact::with([
                'business',
                'groups'
            ])
                ->where('contacts.business_id', $business_id)
                ->where('contacts.type', "supplier")
                ->groupBy('contacts.id');


            if (isset($request->status) && $request->status) {
                $contact->where('contacts.contact_status', $request->status);
            }

            if (isset($request->keyword) && $request->keyword) {
                $contact->where('contacts.name', "like", "%" . $request->keyword . "%");
            }

            $contact->select();
            $data = $contact->paginate($request->limit);

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    /**
     * Returns the database object for customer
     *
     * @return \Illuminate\Http\Response
     */
    public function listCustomer(Request $request)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;

            $contact = Contact::with([
                'business',
                'groups'
            ])
                ->where('contacts.business_id', $business_id)
                ->where('contacts.type', "customer")
                ->groupBy('contacts.id');

            if (isset($request->status) && $request->status) {
                $contact->where('contacts.contact_status', $request->status);
            }

            if (isset($request->keyword) && $request->keyword) {
                $contact->where('contacts.name', "like", "%" . $request->keyword . "%");
            }

            $contact->select();
            $data = $contact->paginate($request->limit);

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function listEmployer(Request $request)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;

            $contact = User::with([
                'business',
                'groups',
                'user',
            ])
                ->where('users.business_id', $business_id)
                ->groupBy('users.id');

            if (isset($request->status) && $request->status) {
                $contact->where('users.status', $request->status);
            }

            if (isset($request->keyword) && $request->keyword) {
                $contact->where('users.first_name', "like", "%" . $request->keyword . "%");
            }

            $contact->select();
            $data = $contact->paginate($request->limit);

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function getDetailContact(Request $request, $id)
    {
        try {
            $contact = Contact::where('id', $id)
                ->with([
                    'business',
                    'groups',
                    'user',
                ])
                ->first();

            if (!$contact) {
                $output['success'] = false;
                $output['msg'] = "Không tìm thấy người dùng";

                $output['data'] = [];

                return response()->json($output, 404);
            }

            $output['success'] = true;
            $output['msg'] = "Lấy thông tin liên hệ thành công";

            $output['data'] = $contact;

            return response()->json($output, 200);
        } catch (\Exception $e) {
            $output['success'] = false;
            $output['msg'] = $e->getMessage();

            return response()->json($output, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public
    function create(Request $request)
    {
        DB::beginTransaction();
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input = $request->only([
                'type',
                'supplier_business_name',
                'prefix',
                'first_name',
                'last_name',
                'tax_number',
                'pay_term_number',
                'pay_term_type',
                'mobile',
                'landline',
                'alternate_number',
                'city',
                'state',
                'country',
                'address_line_1',
                'address_line_2',
                'customer_group_id',
                'zip_code',
                'contact_id',
                'custom_field1',
                'custom_field2',
                'custom_field3',
                'custom_field4',
                'email',
                'shipping_address',
                'position',
                'dob',
                'user_id',
                'user'
            ]);


            $request->validate([
                'first_name' => 'required',
                'type' => 'required',
            ]);

            $array_number = [];

            if (isset($input['prefix']) && $input['prefix']) {
                array_push($array_number, $input['prefix']);
            }

            if (isset($input['last_name']) && $input['last_name']) {
                array_push($array_number, $input['last_name']);
            }

            if (isset($input['first_name']) && $input['first_name']) {
                array_push($array_number, $input['first_name']);
            }

            $input['name'] = implode(' ', $array_number);


            if (!empty($input['dob'])) {
                $input['dob'] = $this->commonUtil->uf_date($input['dob']);
            }

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            $input['credit_limit'] = $request->input('credit_limit') != '' ? $this->commonUtil->num_uf($request->input('credit_limit')) : null;

            //Check Contact id
            $count = 0;
            if (!empty($input['contact_id'])) {
                $count = Contact::where('business_id', $business_id)
                    ->where('contact_id', $input['contact_id'])
                    ->count();
            }

            if ($count == 0) {
                //Update reference count
                $ref_count = $this->commonUtil->setAndGetReferenceCount('contacts', $business_id);

                if (empty($input['contact_id'])) {
                    //Generate reference number
                    $input['contact_id'] = $this->commonUtil->generateReferenceNumber('contacts', $ref_count);
                }

                if ($input['type'] === "employer" && $input['user']) {
                    $userInput = (object)$input['user'];

                    $bank_detail = [
                        "account_holder_name" => isset($userInput->account_holder_name) ? $userInput->account_holder_name : null,
                        "account_number" => isset($userInput->account_number) ? $userInput->account_number : null,
                        "bank_name" => isset($userInput->bank_name) ? $userInput->bank_name : null,
                        "bank_code" => isset($userInput->bank_code) ? $userInput->bank_code : null,
                        "branch" => isset($userInput->branch) ? $userInput->branch : null,
                        "tax_payer_id" => isset($userInput->tax_payer_id) ? $userInput->tax_payer_id : null,
                    ];

                    $user_details = [
                        'user_type' => "user",
                        'first_name' => $input['first_name'],
                        "last_name" => isset($input['last_name']) ? $input['last_name'] : null,
                        'username' => $userInput->username,
                        "email" => isset($input['email']) ? $input['email'] : null,
                        "contact_number" => isset($input['mobile']) ? $input['mobile'] : null,
                        'password' => $userInput->password,
                        'allow_login' => 0
                    ];

                    $user_details['status'] = !empty($userInput->is_active) ? $userInput->is_active : 'inactive';

                    if (isset($userInput->allow_login) && $userInput->allow_login == 0) {
                        unset($user_details['username']);
                        unset($user_details['password']);
                        $user_details['allow_login'] = 0;
                    } else {
                        $user_details['allow_login'] = 1;
                    }

                    if (!isset($user_details['selected_contacts'])) {
                        $user_details['selected_contacts'] = false;
                    }

                    if (isset($userInput->dob) && $userInput->dob) {
                        $user_details['dob'] = $this->moduleUtil->uf_date($userInput->dob);
                    }

                    $user_details['bank_details'] = json_encode($bank_detail);

                    $user_details['business_id'] = $business_id;
                    $user_details['password'] = $user_details['allow_login'] ? Hash::make($user_details['password']) : null;

                    //Sales commission percentage
                    $user_details['cmmsn_percent'] = isset($userInput->cmmsn_percent) && $userInput->cmmsn_percent ? $userInput->cmmsn_percent : 0;

                    $user_details['max_sales_discount_percent'] = isset($userInput->max_sales_discount_percent) && $userInput->cmmsn_percent ? $userInput->max_sales_discount_percent : null;

//                    return $this->respondWithError("error", $user_details, 500);

                    //Create the user
                    $result_user = User::create($user_details);

                    if (!$result_user) {
                        DB::rollBack();
                        $message = "Xảy ra lỗi trong quá trình tạo liên hệ";
                        return $this->respondWithError($message, [], 500);
                    }

                    if (isset($request->roles) && count($request->roles) > 0) {
                        foreach ($request->roles as $role_id) {
                            $role = Role::findOrFail($role_id);
                            $result_user->assignRole($role->name);
                        }
                    }

                    $location_data = [
                        'access_all_locations' => 'access_all_locations',
                        'location_permissions' => []
                    ];
                    //Grant Location permissions
                    $this->giveLocationPermissions($result_user, $location_data, $business_id);

                    //Assign selected contacts

                    $input["user_id"] = $result_user->id;
                }

                unset($input['user']);

                $contact = Contact::create($input);

                if (!$contact) {
                    DB::rollBack();
                    $message = "Xảy ra lỗi trong quá trình tạo liên hệ";
                    return $this->respondWithError($message, [], 500);
                }

                DB::commit();
                return $this->respondSuccess($contact, "Thêm liên hệ thành công");
            } else {
                DB::rollBack();
                $message = "Liên hệ đã tồn tại";

                return $this->respondWithError($message, [], 500);
            }
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
    public
    function detail($id)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $contact = Contact::where('id', $id)
                ->with([
                    'business',
                    'groups',
                    'user',
                    'user.roles',
                ])
                ->first();

            return $this->respondSuccess($contact);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public
    function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input = $request->only([
                'type',
                'supplier_business_name',
                'prefix',
                'first_name',
                'last_name',
                'tax_number',
                'pay_term_number',
                'pay_term_type',
                'mobile',
                'landline',
                'alternate_number',
                'city',
                'state',
                'country',
                'address_line_1',
                'address_line_2',
                'customer_group_id',
                'zip_code',
                'contact_id',
                'custom_field1',
                'custom_field2',
                'custom_field3',
                'custom_field4',
                'email',
                'shipping_address',
                'position',
                'dob',
                'user_id'
            ]);


            $request->validate([
                'first_name' => 'required',
                'type' => 'required',
            ]);

            $array_number = [];

            if (isset($input['prefix']) && $input['prefix']) {
                array_push($array_number, $input['prefix']);
            }

            if (isset($input['last_name']) && $input['last_name']) {
                array_push($array_number, $input['last_name']);
            }

            if (isset($input['first_name']) && $input['first_name']) {
                array_push($array_number, $input['first_name']);
            }

            $input['name'] = implode(' ', $array_number);

            if (!empty($input['dob'])) {
                $input['dob'] = $this->commonUtil->uf_date($input['dob']);
            }

            $input['credit_limit'] = $request->input('credit_limit') != '' ? $this->commonUtil->num_uf($request->input('credit_limit')) : null;

            $count = 0;

            //Check Contact id
            if (!empty($input['contact_id'])) {
                $count = Contact::where('business_id', $business_id)
                    ->where('contact_id', $input['contact_id'])
                    ->where('id', '!=', $id)
                    ->count();
            }

            if ($count == 0) {
                $contact = Contact::where('business_id', $business_id)->findOrFail($id);
                foreach ($input as $key => $value) {
                    $contact->$key = $value;
                }
                $contact->save();

                //Get opening balance if exists
                $ob_transaction = Transaction::where('contact_id', $id)
                    ->where('type', 'opening_balance')
                    ->first();

                if (!empty($ob_transaction)) {
                    $amount = $this->commonUtil->num_uf($request->input('opening_balance'));
                    $opening_balance_paid = $this->transactionUtil->getTotalAmountPaid($ob_transaction->id);
                    if (!empty($opening_balance_paid)) {
                        $amount += $opening_balance_paid;
                    }

                    $ob_transaction->final_total = $amount;
                    $ob_transaction->save();
                    //Update opening balance payment status
                    $this->transactionUtil->updatePaymentStatus($ob_transaction->id, $ob_transaction->final_total);
                } else {
                    //Add opening balance
                    if (!empty($request->input('opening_balance'))) {
                        $this->transactionUtil->createOpeningBalanceTransaction($business_id, $contact->id, $request->input('opening_balance'));
                    }
                }

                $userInput = $request->user;

                if ($input['type'] === "employer" && !empty($userInput)) {
                    $userInput = (object)$userInput;

                    $bank_detail = [
                        "account_holder_name" => isset($userInput->account_holder_name) ? $userInput->account_holder_name : null,
                        "account_number" => isset($userInput->account_number) ? $userInput->account_number : null,
                        "bank_name" => isset($userInput->bank_name) ? $userInput->bank_name : null,
                        "bank_code" => isset($userInput->bank_code) ? $userInput->bank_code : null,
                        "branch" => isset($userInput->branch) ? $userInput->branch : null,
                        "tax_payer_id" => isset($userInput->tax_payer_id) ? $userInput->tax_payer_id : null,
                    ];

                    $user_details = [
                        'user_type' => "user",
                        'first_name' => $input['first_name'],
                        "last_name" => isset($input['last_name']) ? $input['last_name'] : null,
                        'username' => $userInput->username,
                        "email" => isset($input['email']) ? $input['email'] : null,
                        "contact_number" => isset($input['mobile']) ? $input['mobile'] : null,
                        'allow_login' => 0,
                    ];

                    $user_details['status'] = !empty($userInput->status) ? $userInput->status : 'inactive';

                    if (!empty($userInput->password)) {
                        $user_details['password'] = $userInput->password;
                    }

                    if (isset($userInput->allow_login) && $userInput->allow_login == 0) {
                        unset($user_details['username']);
                        unset($user_details['password']);
                        $user_details['allow_login'] = 0;
                    } else {
                        $user_details['allow_login'] = 1;
                    }

                    if (!isset($user_details['selected_contacts'])) {
                        $user_details['selected_contacts'] = false;
                    }

                    if (isset($userInput->dob) && $userInput->dob) {
                        $user_details['dob'] = $this->moduleUtil->uf_date($userInput->dob);
                    }

                    $user_details['bank_details'] = json_encode($bank_detail);

                    $user_details['business_id'] = $business_id;

                    if (!empty($user_details['password'])) {
                        $user_details['password'] = $user_details['allow_login'] ? Hash::make($user_details['password']) : null;
                    }

                    //Sales commission percentage
                    $user_details['cmmsn_percent'] = isset($userInput->cmmsn_percent) && $userInput->cmmsn_percent ? $userInput->cmmsn_percent : 0;

                    $user_details['max_sales_discount_percent'] = isset($userInput->max_sales_discount_percent) && $userInput->cmmsn_percent ? $userInput->max_sales_discount_percent : null;

//                    return $this->respondWithError("error", $user_details, 500);

                    //Create the user
                    $result_user = User::where('id', $contact->user_id)
                    ->update($user_details);

                    if (!$result_user) {
                        DB::rollBack();
                        $message = "Xảy ra lỗi trong quá trình tạo liên hệ";
                        return $this->respondWithError($message, [], 500);
                    }

                    $user = User::find($contact->user_id);
                    if (isset($request->roles) && count($request->roles) > 0) {
                        $user_role = $user->roles;

                        foreach ($user_role as $roleX) {
                            $role = Role::findOrFail($roleX->id);
                            $user->removeRole($role->name);
                        }

                        foreach ($request->roles as $role_id) {
                            $role = Role::findOrFail($role_id);
                            $user->assignRole($role->name);
                        }
                    }

                    $location_data = [
                        'access_all_locations' => 'access_all_locations',
                        'location_permissions' => []
                    ];
                    //Grant Location permissions
//                    $this->giveLocationPermissions($result_user, $location_data, $business_id);

                    //Assign selected contacts

//                    $input["user_id"] = $user->id;
                }

                 DB::commit();
                return $this->respondSuccess($contact);
            } else {
                throw new \Exception("Error Processing Request", 1);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public
    function delete($id)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;

            //Check if any transaction related to this contact exists
            $count = Transaction::where('business_id', $business_id)
                ->where('contact_id', $id)
                ->count();
            if ($count == 0) {
                $contact = Contact::where('business_id', $business_id)->findOrFail($id);
                if (!$contact->is_default) {
                    $contact->delete();
                }

                $message = "Xóa người dùng thành công";
                DB::commit();

                return $this->respondSuccess($contact, $message);
            } else {
                DB::rollBack();
                return $this->respondWithError("Không thể xóa người dùng này", [], 500);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    /**
     * Retrieves list of customers, if filter is passed then filter it accordingly.
     *
     * @param  string $q
     * @return JSON
     */
    public
    function getCustomers()
    {
        if (request()->ajax()) {
            $term = request()->input('q', '');

            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $contacts = Contact::where('business_id', $business_id)
                ->active();

            $selected_contacts = User::isSelectedContacts($user_id);
            if ($selected_contacts) {
                $contacts->join('user_contact_access AS uca', 'contacts.id', 'uca.contact_id')
                    ->where('uca.user_id', $user_id);
            }

            if (!empty($term)) {
                $contacts->where(function ($query) use ($term) {
                    $query->where('name', 'like', '%' . $term . '%')
                        ->orWhere('supplier_business_name', 'like', '%' . $term . '%')
                        ->orWhere('mobile', 'like', '%' . $term . '%')
                        ->orWhere('contacts.contact_id', 'like', '%' . $term . '%');
                });
            }

            $contacts->select(
                'contacts.id',
                DB::raw("IF(contacts.contact_id IS NULL OR contacts.contact_id='', name, CONCAT(name, ' (', contacts.contact_id, ')')) AS text"),
                'mobile',
                'address_line_1',
                'city',
                'state',
                'pay_term_number',
                'pay_term_type'
            )
                ->onlyCustomers();

            if (request()->session()->get('business.enable_rp') == 1) {
                $contacts->addSelect('total_rp');
            }
            $contacts = $contacts->get();
            return json_encode($contacts);
        }
    }

    /**
     * Checks if the given contact id already exist for the current business.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public
    function checkContactId(Request $request)
    {
        $contact_id = $request->input('contact_id');

        $valid = 'true';
        if (!empty($contact_id)) {
            $business_id = $request->session()->get('user.business_id');
            $hidden_id = $request->input('hidden_id');

            $query = Contact::where('business_id', $business_id)
                ->where('contact_id', $contact_id);
            if (!empty($hidden_id)) {
                $query->where('id', '!=', $hidden_id);
            }
            $count = $query->count();
            if ($count > 0) {
                $valid = 'false';
            }
        }
        echo $valid;
        exit;
    }

    /**
     * Shows import option for contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public
    function getImportContacts()
    {
        if (!auth()->user()->can('supplier.create') && !auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $zip_loaded = extension_loaded('zip') ? true : false;

        //Check if zip extension it loaded or not.
        if ($zip_loaded === false) {
            $output = ['success' => 0,
                'msg' => 'Please install/enable PHP Zip archive for import'
            ];

            return view('contact.import')
                ->with('notification', $output);
        } else {
            return view('contact.import');
        }
    }

    /**
     * Imports contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public
    function postImportContacts(Request $request)
    {
        if (!auth()->user()->can('supplier.create') && !auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->commonUtil->notAllowedInDemo();
            if (!empty($notAllowed)) {
                return $notAllowed;
            }

            //Set maximum php execution time
            ini_set('max_execution_time', 0);

            if ($request->hasFile('contacts_csv')) {
                $file = $request->file('contacts_csv');
                $parsed_array = Excel::toArray([], $file);
                //Remove header row
                $imported_data = array_splice($parsed_array[0], 1);

                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');

                $formated_data = [];

                $is_valid = true;
                $error_msg = '';

                DB::beginTransaction();
                foreach ($imported_data as $key => $value) {
                    //Check if 21 no. of columns exists
                    if (count($value) != 27) {
                        $is_valid = false;
                        $error_msg = "Number of columns mismatch";
                        break;
                    }

                    $row_no = $key + 1;
                    $contact_array = [];

                    //Check contact type
                    $contact_type = '';
                    $contact_types = [
                        1 => 'customer',
                        2 => 'supplier',
                        3 => 'both'
                    ];
                    if (!empty($value[0])) {
                        $contact_type = strtolower(trim($value[0]));
                        if (in_array($contact_type, [1, 2, 3])) {
                            $contact_array['type'] = $contact_types[$contact_type];
                        } else {
                            $is_valid = false;
                            $error_msg = "Invalid contact type in row no. $row_no";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "Contact type is required in row no. $row_no";
                        break;
                    }

                    $contact_array['prefix'] = $value[1];
                    //Check contact name
                    if (!empty($value[2])) {
                        $contact_array['first_name'] = $value[2];
                    } else {
                        $is_valid = false;
                        $error_msg = "First name is required in row no. $row_no";
                        break;
                    }
                    $contact_array['middle_name'] = $value[3];
                    $contact_array['last_name'] = $value[4];
                    $contact_array['name'] = $contact_array['prefix'] . ' ' . $contact_array['middle_name'] . ' ' . $contact_array['last_name'];

                    //Check supplier fields
                    if (in_array($contact_type, ['supplier', 'both'])) {
                        //Check business name
                        if (!empty(trim($value[5]))) {
                            $contact_array['supplier_business_name'] = $value[5];
                        } else {
                            $is_valid = false;
                            $error_msg = "Business name is required in row no. $row_no";
                            break;
                        }

                        //Check pay term
                        if (trim($value[9]) != '') {
                            $contact_array['pay_term_number'] = trim($value[9]);
                        } else {
                            $is_valid = false;
                            $error_msg = "Pay term is required in row no. $row_no";
                            break;
                        }

                        //Check pay period
                        $pay_term_type = strtolower(trim($value[10]));
                        if (in_array($pay_term_type, ['days', 'months'])) {
                            $contact_array['pay_term_type'] = $pay_term_type;
                        } else {
                            $is_valid = false;
                            $error_msg = "Pay term period is required in row no. $row_no";
                            break;
                        }
                    }

                    //Check contact ID
                    if (!empty(trim($value[6]))) {
                        $count = Contact::where('business_id', $business_id)
                            ->where('contact_id', $value[6])
                            ->count();


                        if ($count == 0) {
                            $contact_array['contact_id'] = $value[6];
                        } else {
                            $is_valid = false;
                            $error_msg = "Contact ID already exists in row no. $row_no";
                            break;
                        }
                    }

                    //Tax number
                    if (!empty(trim($value[7]))) {
                        $contact_array['tax_number'] = $value[7];
                    }

                    //Check opening balance
                    if (!empty(trim($value[8])) && $value[8] != 0) {
                        $contact_array['opening_balance'] = trim($value[8]);
                    }

                    //Check credit limit
                    if (trim($value[11]) != '' && in_array($contact_type, ['customer', 'both'])) {
                        $contact_array['credit_limit'] = trim($value[11]);
                    }

                    //Check email
                    if (!empty(trim($value[12]))) {
                        if (filter_var(trim($value[12]), FILTER_VALIDATE_EMAIL)) {
                            $contact_array['email'] = $value[12];
                        } else {
                            $is_valid = false;
                            $error_msg = "Invalid email id in row no. $row_no";
                            break;
                        }
                    }

                    //Mobile number
                    if (!empty(trim($value[13]))) {
                        $contact_array['mobile'] = $value[13];
                    } else {
                        $is_valid = false;
                        $error_msg = "Mobile number is required in row no. $row_no";
                        break;
                    }

                    //Alt contact number
                    $contact_array['alternate_number'] = $value[14];

                    //Landline
                    $contact_array['landline'] = $value[15];

                    //City
                    $contact_array['city'] = $value[16];

                    //State
                    $contact_array['state'] = $value[17];

                    //Country
                    $contact_array['country'] = $value[18];

                    //address_line_1
                    $contact_array['address_line_1'] = $value[19];
                    //address_line_2
                    $contact_array['address_line_2'] = $value[20];
                    $contact_array['zip_code'] = $value[21];
                    $contact_array['dob'] = $value[22];

                    //Cust fields
                    $contact_array['custom_field1'] = $value[23];
                    $contact_array['custom_field2'] = $value[24];
                    $contact_array['custom_field3'] = $value[25];
                    $contact_array['custom_field4'] = $value[26];

                    $formated_data[] = $contact_array;
                }
                if (!$is_valid) {
                    throw new \Exception($error_msg);
                }

                if (!empty($formated_data)) {
                    foreach ($formated_data as $contact_data) {
                        $ref_count = $this->transactionUtil->setAndGetReferenceCount('contacts');
                        //Set contact id if empty
                        if (empty($contact_data['contact_id'])) {
                            $contact_data['contact_id'] = $this->commonUtil->generateReferenceNumber('contacts', $ref_count);
                        }

                        $opening_balance = 0;
                        if (isset($contact_data['opening_balance'])) {
                            $opening_balance = $contact_data['opening_balance'];
                            unset($contact_data['opening_balance']);
                        }

                        $contact_data['business_id'] = $business_id;
                        $contact_data['created_by'] = $user_id;

                        $contact = Contact::create($contact_data);

                        if (!empty($opening_balance)) {
                            $this->transactionUtil->createOpeningBalanceTransaction($business_id, $contact->id, $opening_balance);
                        }
                    }
                }

                $output = ['success' => 1,
                    'msg' => __('product.file_imported_successfully')
                ];

                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage()
            ];
            return redirect()->route('contacts.import')->with('notification', $output);
        }

        return redirect()->action('ContactController@index', ['type' => 'supplier'])->with('status', $output);
    }

    /**
     * Shows ledger for contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public
    function getLedger()
    {
        if (!auth()->user()->can('supplier.view') && !auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $contact_id = request()->input('contact_id');

        $start_date = request()->start_date;
        $end_date = request()->end_date;

        $contact = Contact::find($contact_id);

        $ledger_details = $this->transactionUtil->getLedgerDetails($contact_id, $start_date, $end_date);

        if (request()->input('action') == 'pdf') {
            $for_pdf = true;
            $html = view('contact.ledger')
                ->with(compact('ledger_details', 'contact', 'for_pdf'))->render();
            $mpdf = $this->getMpdf();
            $mpdf->WriteHTML($html);
            $mpdf->Output();
        }

        return view('contact.ledger')
            ->with(compact('ledger_details', 'contact'));
    }

    public
    function postCustomersApi(Request $request)
    {
        try {
            $api_token = $request->header('API-TOKEN');

            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $business = Business::find($api_settings->business_id);

            $data = $request->only(['name', 'email']);

            $customer = Contact::where('business_id', $api_settings->business_id)
                ->where('email', $data['email'])
                ->whereIn('type', ['customer', 'both'])
                ->first();

            if (empty($customer)) {
                $data['type'] = 'customer';
                $data['business_id'] = $api_settings->business_id;
                $data['created_by'] = $business->owner_id;
                $data['mobile'] = 0;

                $ref_count = $this->commonUtil->setAndGetReferenceCount('contacts', $business->id);

                $data['contact_id'] = $this->commonUtil->generateReferenceNumber('contacts', $ref_count, $business->id);

                $customer = Contact::create($data);
            }
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($customer);
    }

    /**
     * Function to send ledger notification
     *
     */
    public
    function sendLedger(Request $request)
    {
        $notAllowed = $this->notificationUtil->notAllowedInDemo();
        if (!empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $data = $request->only(['to_email', 'subject', 'email_body', 'cc', 'bcc']);
            $emails_array = array_map('trim', explode(',', $data['to_email']));

            $contact_id = $request->input('contact_id');
            $business_id = request()->session()->get('business.id');

            $start_date = request()->input('start_date');
            $end_date = request()->input('end_date');

            $contact = Contact::find($contact_id);

            $ledger_details = $this->transactionUtil->getLedgerDetails($contact_id, $start_date, $end_date);

            $orig_data = [
                'email_body' => $data['email_body'],
                'subject' => $data['subject']
            ];

            $tag_replaced_data = $this->notificationUtil->replaceTags($business_id, $orig_data, null, $contact);
            $data['email_body'] = $tag_replaced_data['email_body'];
            $data['subject'] = $tag_replaced_data['subject'];

            //replace balance_due
            $data['email_body'] = str_replace('{balance_due}', $this->notificationUtil->num_f($ledger_details['balance_due']), $data['email_body']);

            $data['email_settings'] = request()->session()->get('business.email_settings');


            $for_pdf = true;
            $html = view('contact.ledger')
                ->with(compact('ledger_details', 'contact', 'for_pdf'))->render();
            $mpdf = $this->getMpdf();
            $mpdf->WriteHTML($html);

            $file = config('constants.mpdf_temp_path') . '/' . time() . '_ledger.pdf';
            $mpdf->Output($file, 'F');

            $data['attachment'] = $file;
            $data['attachment_name'] = 'ledger.pdf';
            \Notification::route('mail', $emails_array)
                ->notify(new CustomerNotification($data));

            if (file_exists($file)) {
                unlink($file);
            }

            $output = ['success' => 1, 'msg' => __('lang_v1.notification_sent_successfully')];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());

            $output = ['success' => 0,
                'msg' => "File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage()
            ];
        }

        return $output;
    }

    /**
     * Function to get product stock details for a supplier
     *
     */
    public
    function getSupplierStockReport($supplier_id)
    {
        $pl_query_string = $this->commonUtil->get_pl_quantity_sum_string();
        $query = PurchaseLine::join('transactions as t', 't.id', '=', 'purchase_lines.transaction_id')
            ->join('products as p', 'p.id', '=', 'purchase_lines.product_id')
            ->join('variations as v', 'v.id', '=', 'purchase_lines.variation_id')
            ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
            ->join('units as u', 'p.unit_id', '=', 'u.id')
            ->where('t.type', 'purchase')
            ->where('t.contact_id', $supplier_id)
            ->select(
                'p.name as product_name',
                'v.name as variation_name',
                'pv.name as product_variation_name',
                'p.type as product_type',
                'u.short_name as product_unit',
                'v.sub_sku',
                DB::raw('SUM(quantity) as purchase_quantity'),
                DB::raw('SUM(quantity_returned) as total_quantity_returned'),
                DB::raw('SUM(quantity_sold) as total_quantity_sold'),
                DB::raw("SUM( COALESCE(quantity - ($pl_query_string), 0) * purchase_price_inc_tax) as stock_price"),
                DB::raw("SUM( COALESCE(quantity - ($pl_query_string), 0)) as current_stock")
            )->groupBy('purchase_lines.variation_id');

        if (!empty(request()->location_id)) {
            $query->where('t.location_id', request()->location_id);
        }

        $product_stocks = Datatables::of($query)
            ->editColumn('product_name', function ($row) {
                $name = $row->product_name;
                if ($row->product_type == 'variable') {
                    $name .= ' - ' . $row->product_variation_name . '-' . $row->variation_name;
                }
                return $name . ' (' . $row->sub_sku . ')';
            })
            ->editColumn('purchase_quantity', function ($row) {
                $purchase_quantity = 0;
                if ($row->purchase_quantity) {
                    $purchase_quantity = (float)$row->purchase_quantity;
                }

                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="' . $purchase_quantity . '" data-unit="' . $row->product_unit . '" >' . $purchase_quantity . '</span> ' . $row->product_unit;
            })
            ->editColumn('total_quantity_sold', function ($row) {
                $total_quantity_sold = 0;
                if ($row->total_quantity_sold) {
                    $total_quantity_sold = (float)$row->total_quantity_sold;
                }

                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="' . $total_quantity_sold . '" data-unit="' . $row->product_unit . '" >' . $total_quantity_sold . '</span> ' . $row->product_unit;
            })
            ->editColumn('stock_price', function ($row) {
                $stock_price = 0;
                if ($row->stock_price) {
                    $stock_price = (float)$row->stock_price;
                }

                return '<span class="display_currency" data-currency_symbol=true >' . $stock_price . '</span> ';
            })
            ->editColumn('current_stock', function ($row) {
                $current_stock = 0;
                if ($row->current_stock) {
                    $current_stock = (float)$row->current_stock;
                }

                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="' . $current_stock . '" data-unit="' . $row->product_unit . '" >' . $current_stock . '</span> ' . $row->product_unit;
            });

        return $product_stocks->rawColumns(['current_stock', 'stock_price', 'total_quantity_sold', 'purchase_quantity'])->make(true);
    }

    public
    function updateStatus($id)
    {
        if (!auth()->user()->can('supplier.update') && !auth()->user()->can('customer.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $contact = Contact::where('business_id', $business_id)->find($id);
            $contact->contact_status = $contact->contact_status == 'active' ? 'inactive' : 'active';
            $contact->save();

            $output = ['success' => true,
                'msg' => __("contact.updated_success")
            ];
            return $output;
        }
    }

    /**
     * Display contact locations on map
     *
     */
    public
    function contactMap()
    {
        if (!auth()->user()->can('supplier.view') && !auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $query = Contact::where('business_id', $business_id)
            ->active()
            ->whereNotNull('position');

        if (!empty(request()->input('contacts'))) {
            $query->whereIn('id', request()->input('contacts'));
        }
        $contacts = $query->get();

        $all_contacts = Contact::where('business_id', $business_id)
            ->active()
            ->get();

        return view('contact.contact_map')
            ->with(compact('contacts', 'all_contacts'));
    }


    private function getUsernameExtension()
    {
        $extension = !empty(System::getProperty('enable_business_based_username')) ? '-' . str_pad(session()->get('business.id'), 2, 0, STR_PAD_LEFT) : null;
        return $extension;
    }

    /**
     * Retrives roles array (Hides admin role from non admin users)
     *
     * @param  int $business_id
     * @return array $roles
     */
    private function getRolesArray($business_id)
    {
        $roles_array = Role::where('business_id', $business_id)->get()->pluck('name', 'id');
        $roles = [];

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        foreach ($roles_array as $key => $value) {
            if (!$is_admin && $value == 'Admin#' . $business_id) {
                continue;
            }
            $roles[$key] = str_replace('#' . $business_id, '', $value);
        }
        return $roles;
    }

    private function giveLocationPermissions($user, $request, $business_id = null)
    {
        $permitted_locations = $user->permitted_locations($business_id);
        $permissions = $request['access_all_locations'];
        $revoked_permissions = [];
        //If not access all location then revoke permission
        if ($permitted_locations == 'all' && $permissions != 'access_all_locations') {
            $user->revokePermissionTo('access_all_locations');
        }

        //Include location permissions
        $location_permissions = $request['location_permissions'];
        if (empty($permissions) &&
            !empty($location_permissions)) {
            $permissions = [];
            foreach ($location_permissions as $location_permission) {
                $permissions[] = $location_permission;
            }

            if (is_array($permitted_locations)) {
                foreach ($permitted_locations as $key => $value) {
                    if (!in_array('location.' . $value, $permissions)) {
                        $revoked_permissions[] = 'location.' . $value;
                    }
                }
            }
        }

        if (!empty($revoked_permissions)) {
            $user->revokePermissionTo($revoked_permissions);
        }

        if (!empty($permissions)) {
            $user->givePermissionTo($permissions);
        } else {
            //if no location permission given revoke previous permissions
            if (!empty($permitted_locations)) {
                $revoked_permissions = [];
                foreach ($permitted_locations as $key => $value) {
                    $revoked_permissions[] = 'location.' . $value;
                }

                $user->revokePermissionTo($revoked_permissions);
            }
        }
    }

    public function importDataSupplier(Request $request)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            //Set maximum php execution time
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            if ($request->hasFile('file')) {
                $file = $request->file('file');

                $parsed_array = Excel::toArray([], $file);

                //Remove header row
                $imported_data = array_splice($parsed_array[0], 1);

                $formated_data = [];
                $prices_data = [];

                $is_valid = true;
                $error_msg = '';
                $is_replace = false;
                $columnReplace = [];

                if(isset($request->column_select) && $request->column_select) {
                    $columnReplace = explode(',', $request->column_select);
                    if (count($columnReplace) > 0) {
                        $is_replace = true;
                    }
                }

                foreach ($imported_data as $key => $value) {
                    //Check if any column is missing
                    if (count($value) < 5 ) {
                        $is_valid =  false;
                        $error_msg = "Thiếu cột trong quá trình tải lên dữ liệu. vui lòng tuần thủ dữ template";
                        break;
                    }

                    $row_no = $key + 1;
                    $contact_array = [];
                    $contact_array['business_id'] = $business_id;
                    $contact_array['created_by'] = $user_id;
                    $contact_array['type'] = "supplier";
                    $contact_array['contact_status'] = "active";


                    //Add SKU
                    $actual_name = trim($value[1]);
                    if (!empty($actual_name)) {
                        $contact_array['supplier_business_name'] = $actual_name;
                        $contact_array['name'] = $actual_name;
                        $contact_array['first_name'] = $actual_name;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu tên liên hệ";
                        break;
                    }

                    //Add product name
                    $contact_id = trim($value[0]);
                    $contact_array['id'] = $contact_id;

                    $address = trim($value[2]);
                    if (!empty($address)) {
                        $contact_array['address_line_1'] = $address;
                    }

                    $phone = trim($value[3]);
                    if (!empty($phone)) {
                        $contact_array['mobile'] = $phone;
                    }

                    $email = trim($value[4]);
                    if (!empty($email)) {
                        $contact_array['email'] = $email;
                    }

                    $formated_data[] = $contact_array;
                }

                if (!$is_valid) {
                    throw new \Exception($error_msg);
                }

                if (!empty($formated_data)) {

                    foreach ($formated_data as $index => $item) {
                        $exitItem = Contact::where('id', $item['id'])
                            ->first();

                        if($exitItem && $is_replace === true) {
                            $dataUpdate = [];

                            foreach ($columnReplace as $value) {
                                if (isset($item[$value])) {
                                    $dataUpdate[$value] = $item[$value];
                                }

                                Contact::where('id', $item['id'])
                                    ->update($dataUpdate);
                            }
                        } else {
                            Contact::create($item);
                        }
                    }
                }
            }

            DB::commit();
            $message = "Nhập liệu liên hệ thành công";

            return $this->respondSuccess($message, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function importDataCustomer(Request $request)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            //Set maximum php execution time
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            if ($request->hasFile('file')) {
                $file = $request->file('file');

                $parsed_array = Excel::toArray([], $file);

                //Remove header row
                $imported_data = array_splice($parsed_array[0], 1);

                $formated_data = [];
                $prices_data = [];

                $is_valid = true;
                $error_msg = '';
                $is_replace = false;
                $columnReplace = [];

                if(isset($request->column_select) && $request->column_select) {
                    $columnReplace = explode(',', $request->column_select);
                    if (count($columnReplace) > 0) {
                        $is_replace = true;
                    }
                }

                foreach ($imported_data as $key => $value) {
                    //Check if any column is missing
                    if (count($value) < 5 ) {
                        $is_valid =  false;
                        $error_msg = "Thiếu cột trong quá trình tải lên dữ liệu. vui lòng tuần thủ dữ template";
                        break;
                    }

                    $row_no = $key + 1;
                    $contact_array = [];
                    $contact_array['business_id'] = $business_id;
                    $contact_array['created_by'] = $user_id;
                    $contact_array['type'] = "customer";
                    $contact_array['contact_status'] = "active";


                    //Add SKU
                    $actual_name = trim($value[1]);
                    if (!empty($actual_name)) {
                        $contact_array['supplier_business_name'] = $actual_name;
                        $contact_array['name'] = $actual_name;
                        $contact_array['first_name'] = $actual_name;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu tên liên hệ";
                        break;
                    }

                    //Add product name
                    $contact_id = trim($value[0]);
                    $contact_array['id'] = $contact_id;

                    $address = trim($value[2]);
                    if (!empty($address)) {
                        $contact_array['address_line_1'] = $address;
                    }

                    $phone = trim($value[3]);
                    if (!empty($phone)) {
                        $contact_array['mobile'] = $phone;
                    }

                    $email = trim($value[4]);
                    if (!empty($email)) {
                        $contact_array['email'] = $email;
                    }

                    $formated_data[] = $contact_array;
                }

                if (!$is_valid) {
                    throw new \Exception($error_msg);
                }

                if (!empty($formated_data)) {

                    foreach ($formated_data as $index => $item) {
                        $exitItem = Contact::where('id', $item['id'])
                            ->first();

                        if($exitItem && $is_replace === true) {
                            $dataUpdate = [];

                            foreach ($columnReplace as $value) {
                                if (isset($item[$value])) {
                                    $dataUpdate[$value] = $item[$value];
                                }

                                Contact::where('id', $item['id'])
                                    ->update($dataUpdate);
                            }
                        } else {
                            Contact::create($item);
                        }
                    }
                }
            }

            DB::commit();
            $message = "Nhập liệu liên hệ thành công";

            return $this->respondSuccess($message, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }
}
