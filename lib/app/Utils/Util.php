<?php

namespace App\Utils;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Product;
use App\ReferenceCount;
use App\Transaction;
use App\TransactionSellLine;
use App\Unit;
use App\User;
use App\VariationLocationDetails;
use DB;

use GuzzleHttp\Client;

use Spatie\Permission\Models\Role;

class Util
{
    /**
     * This function unformats a number and returns them in plain eng format
     *
     * @param int $input_number
     *
     * @return float
     */
    public function num_uf($input_number, $currency_details = null)
    {
        $thousand_separator  = '';
        $decimal_separator  = '';

        if (!empty($currency_details)) {
            $thousand_separator = $currency_details->thousand_separator;
            $decimal_separator = $currency_details->decimal_separator;
        } else {
            $thousand_separator = session()->has('currency') ? session('currency')['thousand_separator'] : '';
            $decimal_separator = session()->has('currency') ? session('currency')['decimal_separator'] : '';
        }

        $num = str_replace($thousand_separator, '', $input_number);
        $num = str_replace($decimal_separator, '.', $num);

        return (float)$num;
    }

    /**
     * This function formats a number and returns them in specified format
     *
     * @param int $input_number
     * @param boolean $add_symbol = false
     * @param array $business_details = null
     * @param boolean $is_quantity = false; If number represents quantity
     *
     * @return string
     */
    public function num_f($input_number, $add_symbol = false, $business_details = null, $is_quantity = false)
    {
        $thousand_separator = !empty($business_details) ? $business_details->thousand_separator : session('currency')['thousand_separator'];
        $decimal_separator = !empty($business_details) ? $business_details->decimal_separator : session('currency')['decimal_separator'];

//        $currency_precision = config('constants.currency_precision', 0);
//
//        if ($is_quantity) {
//            $currency_precision = config('constants.quantity_precision', 0);
//        }

        $currency_precision = 0;

        $formatted = number_format($input_number, $currency_precision, $decimal_separator, $thousand_separator);

        if ($add_symbol) {
            $currency_symbol_placement = !empty($business_details) ? $business_details->currency_symbol_placement : session('business.currency_symbol_placement');
            $symbol = !empty($business_details) ? $business_details->currency_symbol : session('currency')['symbol'];

            if ($currency_symbol_placement == 'after') {
                $formatted = $formatted . ' ' . $symbol;
            } else {
                $formatted = $symbol . ' ' . $formatted;
            }
        }

        return $formatted;
    }

    /**
    * Calculates percentage for a given number
    *
    * @param int $number
    * @param int $percent
    * @param int $addition default = 0
    *
    * @return float
    */
    public function calc_percentage($number, $percent, $addition = 0)
    {
        return ($addition + ($number * ($percent / 100)));
    }

    /**
     * Calculates base value on which percentage is calculated
     *
     * @param int $number
     * @param int $percent
     *
     * @return float
     */
    public function calc_percentage_base($number, $percent)
    {
        return ($number * 100) / (100 + $percent);
    }

    /**
     * Calculates percentage
     *
     * @param int $base
     * @param int $number
     *
     * @return float
     */
    public function get_percent($base, $number)
    {
        if ($base == 0) {
            return 0;
        }

        $diff = $number - $base;
        return ($diff / $base) * 100;
    }

    //Returns all avilable purchase statuses
    public function orderStatuses()
    {
        return [ 'received' => __('lang_v1.received'), 'pending' => __('lang_v1.pending'), 'ordered' => __('lang_v1.ordered')];
    }

    /**
     * Defines available Payment Types
     *
     * @return array
     */
    public function payment_types($location = null)
    {
        $payment_types = ['cash' => __('lang_v1.cash'), 'card' => __('lang_v1.card'), 'cheque' => __('lang_v1.cheque'), 'bank_transfer' => __('lang_v1.bank_transfer'), 'other' => __('lang_v1.other')];

        $custom_labels = !empty(session('business.custom_labels')) ? json_decode(session('business.custom_labels'), true) : [];

        $payment_types['custom_pay_1'] = !empty($custom_labels['payments']['custom_pay_1']) ? $custom_labels['payments']['custom_pay_1'] : __('lang_v1.custom_payment_1');
        $payment_types['custom_pay_2'] = !empty($custom_labels['payments']['custom_pay_2']) ? $custom_labels['payments']['custom_pay_2'] : __('lang_v1.custom_payment_2');
        $payment_types['custom_pay_3'] = !empty($custom_labels['payments']['custom_pay_3']) ? $custom_labels['payments']['custom_pay_3'] : __('lang_v1.custom_payment_3');

        //Unset payment types if not enabled in business location
        if (!empty($location)) {
            $location = is_object($location) ? $location : BusinessLocation::find($location);
            $location_account_settings = !empty($location->default_payment_accounts) ? json_decode($location->default_payment_accounts, true) : [];
            $enabled_accounts = [];
            foreach ($location_account_settings as $key => $value) {
                if (!empty($value['is_enabled'])) {
                    $enabled_accounts[] = $key;
                }
            }
            foreach ($payment_types as $key => $value) {
                if (!in_array($key, $enabled_accounts)) {
                    unset($payment_types[$key]);
                }
            }
        }

        return $payment_types;
    }

    /**
     * Returns the list of modules enabled
     *
     * @return array
     */
    public function allModulesEnabled()
    {
        $enabled_modules = session()->has('business') ? session('business')['enabled_modules'] : null;
        $enabled_modules = (!empty($enabled_modules) && $enabled_modules != 'null') ? $enabled_modules : [];

        return $enabled_modules;
        //Module::has('Restaurant');
    }

    /**
     * Returns the list of modules enabled
     *
     * @return array
     */
    public function isModuleEnabled($module)
    {
        $enabled_modules = $this->allModulesEnabled();

        if (in_array($module, $enabled_modules)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Converts date in business format to mysql format
     *
     * @param string $date
     * @param bool $time (default = false)
     * @return strin
     */
    public function uf_date($date, $time = false)
    {
        $date_format = session('business.date_format');
        $mysql_format = 'Y-m-d';
        if ($time) {
            if (session('business.time_format') == 12) {
                $date_format = $date_format . ' h:i A';
            } else {
                $date_format = $date_format . ' H:i';
            }
            $mysql_format = 'Y-m-d H:i:s';
        }

        return !empty($date_format) ? \Carbon::createFromFormat($date_format, $date)->format($mysql_format) : null;
    }

    /**
     * Converts time in business format to mysql format
     *
     * @param string $time
     * @return strin
     */
    public function uf_time($time)
    {
        $time_format = 'H:i';
        if (session('business.time_format') == 12) {
            $time_format = 'h:i A';
        }
        return !empty($time_format) ? \Carbon::createFromFormat($time_format, $time)->format('H:i') : null;
    }

    /**
     * Converts time in business format to mysql format
     *
     * @param string $time
     * @return strin
     */
    public function format_time($time)
    {
        $time_format = 'H:i';
        if (session('business.time_format') == 12) {
            $time_format = 'h:i A';
        }
        return !empty($time) ? \Carbon::createFromFormat('H:i:s', $time)->format($time_format) : null;
    }

    /**
     * Converts date in mysql format to business format
     *
     * @param string $date
     * @param bool $time (default = false)
     * @return strin
     */
    public function format_date($date, $show_time = false, $business_details = null)
    {
        $format = !empty($business_details) ? $business_details->date_format : session('business.date_format');
        if (!empty($show_time)) {
            $time_format = !empty($business_details) ? $business_details->time_format : session('business.time_format');
            if ($time_format == 12) {
                $format .= ' h:i A';
            } else {
                $format .= ' H:i';
            }
        }
        
        return !empty($date) ? \Carbon::createFromTimestamp(strtotime($date))->format($format) : null;
    }

    /**
     * Increments reference count for a given type and given business
     * and gives the updated reference count
     *
     * @param string $type
     * @param int $business_id
     *
     * @return int
     */
    public function setAndGetReferenceCount($type, $business_id = null)
    {
        if (empty($business_id)) {
            $business_id = request()->session()->get('user.business_id');
        }

        $ref = ReferenceCount::where('ref_type', $type)
                          ->where('business_id', $business_id)
                          ->first();
        if (!empty($ref)) {
            $ref->ref_count += 1;
            $ref->save();
            return $ref->ref_count;
        } else {
            $new_ref = ReferenceCount::create([
                'ref_type' => $type,
                'business_id' => $business_id,
                'ref_count' => 1
            ]);
            return $new_ref->ref_count;
        }
    }

    /**
     * Generates reference number
     *
     * @param string $type
     * @param int $business_id
     *
     * @return int
     */
    public function generateReferenceNumber($type, $ref_count, $business_id = null, $default_prefix = null)
    {
        $prefix = '';

//        if (session()->has('business') && !empty(request()->session()->get('business.ref_no_prefixes')[$type])) {
//            $prefix = request()->session()->get('business.ref_no_prefixes')[$type];
//        }
//        if (!empty($business_id)) {
//            $business = Business::find($business_id);
//            $prefixes = $business->ref_no_prefixes;
//            $prefix = $prefixes[$type];
//        }

        if (!empty($default_prefix)) {
            $prefix = $default_prefix;
        }

        $ref_digits =  str_pad($ref_count, 4, 0, STR_PAD_LEFT);

        $ref_number = $prefix . "/" . $ref_digits;

        return $ref_number;
    }

    /**
    * Checks if the given user is admin
    *
    * @param obj $user
    * @param int $business_id
    *
    * @return bool
    */
    public function is_admin($user, $business_id)
    {
        return $user->hasRole('Admin#' . $business_id) ? true : false;
    }

    /**
    * Checks if the feature is allowed in demo
    *
    * @return mixed
    */
    public function notAllowedInDemo()
    {
        //Disable in demo
        if (config('app.env') == 'demo') {
            $output = ['success' => 0,
                    'msg' => __('lang_v1.disabled_in_demo')
                ];
            if (request()->ajax()) {
                return $output;
            } else {
                return back()->with('status', $output);
            }
        }
    }

    /**
     * Sends SMS notification.
     *
     * @param  array $data
     * @return void
     */
    public function sendSms($data)
    {
        $sms_settings = $data['sms_settings'];
        $request_data = [
            $sms_settings['send_to_param_name'] => $data['mobile_number'],
            $sms_settings['msg_param_name'] => $data['sms_body'],
        ];

        if (!empty($sms_settings['param_1'])) {
            $request_data[$sms_settings['param_1']] = $sms_settings['param_val_1'];
        }
        if (!empty($sms_settings['param_2'])) {
            $request_data[$sms_settings['param_2']] = $sms_settings['param_val_2'];
        }
        if (!empty($sms_settings['param_3'])) {
            $request_data[$sms_settings['param_3']] = $sms_settings['param_val_3'];
        }
        if (!empty($sms_settings['param_4'])) {
            $request_data[$sms_settings['param_4']] = $sms_settings['param_val_4'];
        }
        if (!empty($sms_settings['param_5'])) {
            $request_data[$sms_settings['param_5']] = $sms_settings['param_val_5'];
        }
        if (!empty($sms_settings['param_6'])) {
            $request_data[$sms_settings['param_6']] = $sms_settings['param_val_6'];
        }
        if (!empty($sms_settings['param_7'])) {
            $request_data[$sms_settings['param_7']] = $sms_settings['param_val_7'];
        }
        if (!empty($sms_settings['param_8'])) {
            $request_data[$sms_settings['param_8']] = $sms_settings['param_val_8'];
        }
        if (!empty($sms_settings['param_9'])) {
            $request_data[$sms_settings['param_9']] = $sms_settings['param_val_9'];
        }
        if (!empty($sms_settings['param_10'])) {
            $request_data[$sms_settings['param_10']] = $sms_settings['param_val_10'];
        }

        $client = new Client();

        if ($sms_settings['request_method'] == 'get') {
            $response = $client->get($sms_settings['url'] . '?'. http_build_query($request_data));
        } else {
            $response = $client->post($sms_settings['url'], [
                'form_params' => $request_data
            ]);
        }

        return $response;
    }

    /**
    * Retrieves sub units of a base unit
    *
    * @param integer $business_id
    * @param integer $unit_id
    * @param boolean $return_main_unit_if_empty = false
    * @param integer $product_id = null
    *
    * @return array
    */
    public function getSubUnits($business_id, $unit_id, $return_main_unit_if_empty = false, $product_id = null)
    {
        $unit = Unit::where('business_id', $business_id)
                    ->with(['sub_units'])
                    ->findOrFail($unit_id);

        //Find related subunits for the product.
        $related_sub_units = [];
        if (!empty($product_id)) {
            $product = Product::where('business_id', $business_id)->findOrFail($product_id);
            $related_sub_units = $product->sub_unit_ids;
        }

        $sub_units = [];

        //Add main unit as per given parameter or conditions.
        if (($return_main_unit_if_empty && count($unit->sub_units) == 0)) {
            $sub_units[$unit->id] = [
                      'name' => $unit->actual_name,
                      'multiplier' => 1,
                      'allow_decimal' => $unit->allow_decimal
                    ];
        } elseif (empty($related_sub_units) || in_array($unit->id, $related_sub_units)) {
            $sub_units[$unit->id] = [
                      'name' => $unit->actual_name,
                      'multiplier' => 1,
                      'allow_decimal' => $unit->allow_decimal
                    ];
        }

        if (count($unit->sub_units) > 0) {
            foreach ($unit->sub_units as $sub_unit) {
                //Check if subunit is related to the product or not.
                if (empty($related_sub_units) || in_array($sub_unit->id, $related_sub_units)) {
                    $sub_units[$sub_unit->id] = [
                        'name' => $sub_unit->actual_name,
                        'multiplier' => $sub_unit->base_unit_multiplier,
                        'allow_decimal' => $sub_unit->allow_decimal
                        ];
                }
            }
        }

        return $sub_units;
    }

    public function getMultiplierOf2Units($base_unit_id, $unit_id)
    {
        if ($base_unit_id == $unit_id || is_null($base_unit_id) || is_null($unit_id)) {
            return 1;
        }

        $unit = Unit::where('base_unit_id', $base_unit_id)
                    ->where('id', $unit_id)
                    ->first();
        if (empty($unit)) {
            return 1;
        } else {
            return $unit->base_unit_multiplier;
        }
    }

    /**
     * Generates unique token
     *
     * @param void
     *
     * @return string
     */
    public function generateToken()
    {
        return md5(rand(1, 10) . microtime());
    }

    /**
     * Generates invoice url for the transaction
     *
     * @param int $transaction_id, int $business_id
     *
     * @return string
     */
    public function getInvoiceUrl($transaction_id, $business_id)
    {
        $transaction = Transaction::where('business_id', $business_id)
                            ->findOrFail($transaction_id);

        if (empty($transaction->invoice_token)) {
            $transaction->invoice_token = $this->generateToken();
            $transaction->save();
        }

        return route('show_invoice', ['token' => $transaction->invoice_token]);
    }

    /**
     * Uploads document to the server if present in the request
     * @param obj $request, string $file_name, string dir_name
     *
     * @return string
     */
    public function uploadFile($request, $file_name, $dir_name, $file_type = 'document')
    {
        //If app environment is demo return null
        if (config('app.env') == 'demo') {
            return null;
        }
        
        $uploaded_file_name = null;
        if ($request->hasFile($file_name) && $request->file($file_name)->isValid()) {
            
            //Check if mime type is image
            if ($file_type == 'image') {
                if (strpos($request->$file_name->getClientMimeType(), 'image/') === false) {
                    throw new \Exception("Invalid image file");
                }
            }

            if ($file_type == 'document') {
                if (!in_array($request->$file_name->getClientMimeType(), array_keys(config('constants.document_upload_mimes_types')))) {
                    throw new \Exception("Invalid document file");
                }
            }
            
            if ($request->$file_name->getSize() <= config('constants.document_size_limit')) {
                $new_file_name = time() . '_' . $request->$file_name->getClientOriginalName();
                if ($request->$file_name->storeAs($dir_name, $new_file_name)) {
                    $uploaded_file_name = $new_file_name;
                }
            }
        }

        return $uploaded_file_name;
    }
    
    public function serviceStaffDropdown($business_id, $location_id = null)
    {
        $waiters = [];
        //Get all service staff roles
        $service_staff_roles_obj = Role::where('business_id', $business_id)
                            ->where('is_service_staff', 1)
                            ->get();

        $service_staff_roles = $service_staff_roles_obj->pluck('name')->toArray();
        
        //Get all users of service staff roles
        if (!empty($service_staff_roles)) {
            $waiters = User::where('business_id', $business_id)
                        ->role($service_staff_roles);

            if (!empty($location_id)) {
                $waiters->permission(['location.'.$location_id, 'access_all_locations']);
            }

            $waiters = $waiters->select('id', DB::raw('CONCAT(COALESCE(first_name, ""), " ", COALESCE(last_name, "")) as full_name'))->get()->pluck('full_name', 'id');
        }

        return $waiters;
    }

    /**
     * Replaces tags from notification body with original value
     *
     * @param  text  $body
     * @param  int  $transaction_id
     *
     * @return array
     */
    public function replaceTags($business_id, $data, $transaction, $contact = null)
    {
        if (!empty($transaction) && !is_object($transaction)) {
            $transaction = Transaction::where('business_id', $business_id)
                            ->with(['contact', 'payment_lines'])
                            ->findOrFail($transaction);
        }
        
        $business = Business::findOrFail($business_id);

        foreach ($data as $key => $value) {
            //Replace contact name
            if (strpos($value, '{contact_name}') !== false) {
                $contact_name = empty($contact) ? $transaction->contact->name : $contact->name;

                $data[$key] = str_replace('{contact_name}', $contact_name, $data[$key]);
            }

            //Replace invoice number
            if (strpos($value, '{invoice_number}') !== false) {
                $invoice_number = $transaction->type == 'sell' ? $transaction->invoice_no : '';

                $data[$key] = str_replace('{invoice_number}', $invoice_number, $data[$key]);
            }

            //Replace ref number
            if (strpos($value, '{order_ref_number}') !== false) {
                $order_ref_number = $transaction->ref_no;

                $data[$key] = str_replace('{order_ref_number}', $order_ref_number, $data[$key]);
            }
            //Replace total_amount
            if (strpos($value, '{total_amount}') !== false) {
                $total_amount = $this->num_f($transaction->final_total, true);

                $data[$key] = str_replace('{total_amount}', $total_amount, $data[$key]);
            }

            $total_paid = 0;
            $payment_ref_number = [];
            if (!empty($transaction)) {
                foreach ($transaction->payment_lines as $payment) {
                    if ($payment->is_return != 1) {
                        $total_paid += $payment->amount;
                        $payment_ref_number[] = $payment->payment_ref_no;
                    }
                }
            }
            
            $paid_amount = $this->num_f($total_paid, true);

            //Replace paid_amount
            if (strpos($value, '{paid_amount}') !== false) {
                $data[$key] = str_replace('{paid_amount}', $paid_amount, $data[$key]);
            }

            //Replace received_amount
            if (strpos($value, '{received_amount}') !== false) {
                $data[$key] = str_replace('{received_amount}', $paid_amount, $data[$key]);
            }

            //Replace payment_ref_number
            if (strpos($value, '{payment_ref_number}') !== false) {
                $data[$key] = str_replace('{payment_ref_number}', implode(', ', $payment_ref_number), $data[$key]);
            }

            //Replace due_amount
            if (strpos($value, '{due_amount}') !== false) {
                $due = $transaction->final_total - $total_paid;
                $due_amount = $this->num_f($due, true);

                $data[$key] = str_replace('{due_amount}', $due_amount, $data[$key]);
            }

            //Replace business_name
            if (strpos($value, '{business_name}') !== false) {
                $business_name = $business->name;
                $data[$key] = str_replace('{business_name}', $business_name, $data[$key]);
            }

            //Replace business_logo
            if (strpos($value, '{business_logo}') !== false) {
                $logo_name = $business->logo;
                $business_logo = !empty($logo_name) ? '<img src="' . url('uploads/business_logos/' . $logo_name) . '" alt="Business Logo" >' : '';

                $data[$key] = str_replace('{business_logo}', $business_logo, $data[$key]);
            }

            //Replace invoice_url
            if (!empty($transaction) && strpos($value, '{invoice_url}') !== false && $transaction->type == 'sell') {
                $invoice_url = $this->getInvoiceUrl($transaction->id, $transaction->business_id);
                $data[$key] = str_replace('{invoice_url}', $invoice_url, $data[$key]);
            }

            if (strpos($value, '{cumulative_due_amount}') !== false) {
                $due = $this->getContactDue($transaction->contact_id);
                $data[$key] = str_replace('{cumulative_due_amount}', $due, $data[$key]);
            }

            if (strpos($value, '{due_date}') !== false) {
                $due_date = $transaction->due_date;
                if (!empty($due_date)) {
                    $due_date = $this->format_date($due_date->toDateTimeString(), true);
                }
                $data[$key] = str_replace('{due_date}', $due_date, $data[$key]);
            }

            if (strpos($value, '{contact_business_name}') !== false) {
                $contact_business_name = !empty($transaction->contact->supplier_business_name) ? $transaction->contact->supplier_business_name : '';
                $data[$key] = str_replace('{contact_business_name}', $contact_business_name, $data[$key]);
            }
        }

        return $data;
    }

    public function getCronJobCommand()
    {
        $php_binary_path = empty(PHP_BINARY) ? "php" : PHP_BINARY;

        $command = "* * * * * " . $php_binary_path . " " . base_path('artisan') . " schedule:run >> /dev/null 2>&1";

        if (config('app.env') == 'demo') {
            $command = '';
        }

        return $command;
    }

    /**
     * Checks whether mail is configured or not
     *
     * @return boolean
     */
    public function IsMailConfigured()
    {
        $is_mail_configured = false;

        if (!empty(env('MAIL_DRIVER')) &&
            !empty(env('MAIL_HOST')) &&
            !empty(env('MAIL_PORT')) &&
            !empty(env('MAIL_USERNAME')) &&
            !empty(env('MAIL_PASSWORD')) &&
            !empty(env('MAIL_FROM_ADDRESS'))
            ) {
            $is_mail_configured = true;
        }

        return $is_mail_configured;
    }

    /**
    * Returns the list of barcode types
    *
    * @return array
    */
    public function barcode_types()
    {
        $types = [ 'C128' => 'Code 128 (C128)', 'C39' => 'Code 39 (C39)', 'EAN13' => 'EAN-13', 'EAN8' => 'EAN-8', 'UPCA' => 'UPC-A', 'UPCE' => 'UPC-E'];

        return $types;
    }

    /**
     * Returns the default barcode.
     *
     * @return string
     */
    public function barcode_default()
    {
        return 'C128';
    }

    /**
     * Retrieves user role name.
     *
     * @return string
     */
    public function getUserRoleName($user_id)
    {
        $user = User::findOrFail($user_id);

        $roles = $user->getRoleNames();

        $role_name = '';

        if (!empty($roles[0])) {
            $array = explode('#', $roles[0], 2);
            $role_name = !empty($array[0]) ? $array[0] : '';
        }
        return $role_name;
    }

    /**
    * Retrieves all admins of a business
    *
    * @param int $business_id
    *
    * @return obj
    */
    public function get_admins($business_id)
    {
        $admins = User::role('Admin#' . $business_id)->get();

        return $admins;
    }

    /**
    * Retrieves IP address of the user
    *
    * @return string
    */
    public function getUserIpAddr()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //ip pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * This function updates the stock of products present in combo product and also updates transaction sell line.
     *
     * @param array $lines
     * @param int $location_id
     * @param boolean $adjust_stock = true
     *
     * @return void
     */
    public function updateEditedSellLineCombo($lines, $location_id, $adjust_stock = true)
    {
        if (empty($lines)) {
            return true;
        }

        $change_percent = null;

        foreach ($lines as $key => $line) {
            $prev_line = TransactionSellLine::find($line['transaction_sell_lines_id']);

            $difference = $prev_line->quantity - $line['quantity'];
            if ($difference != 0) {
                //Update stock in variation location details table.
                //Adjust Quantity in variations location table
                if ($adjust_stock) {
                    VariationLocationDetails::where('variation_id', $line['variation_id'])
                    ->where('product_id', $line['product_id'])
                    ->where('location_id', $location_id)
                    ->increment('qty_available', $difference);
                }
                
                //Update the child line quantity
                $prev_line->quantity = $line['quantity'];
            }
            
            //Recalculate the price.
            if (is_null($change_percent)) {
                $parent = TransactionSellLine::findOrFail($prev_line->parent_sell_line_id);
                $child_sum = TransactionSellLine::where('parent_sell_line_id', $prev_line->parent_sell_line_id)
                    ->select(DB::raw('SUM(unit_price_inc_tax * quantity) as total_price'))
                    ->first()
                    ->total_price;
                    
                $change_percent = $this->get_percent($child_sum, $parent->unit_price_inc_tax * $parent->quantity);
            }

            $price = $this->calc_percentage($prev_line->unit_price_inc_tax, $change_percent, $prev_line->unit_price_inc_tax);
            $prev_line->unit_price_before_discount = $price;
            $prev_line->unit_price = $price;
            $prev_line->unit_price_inc_tax = $price;

            $prev_line->save();
        }
    }

    /**
     *
     * Generates string to calculate sum of purchase line quantity used
     */
    public function get_pl_quantity_sum_string($table_name = '')
    {
        $table_name = !empty($table_name) ? $table_name . '.' : '';
        $string = $table_name . "quantity_sold + " . $table_name . "quantity_adjusted + " . $table_name . "quantity_returned + " . $table_name . "mfg_quantity_used";
        
        return $string;
    }

    public function shipping_statuses()
    {
        $statuses = [
            'ordered' => __('lang_v1.ordered'),
            'packed' => __('lang_v1.packed'),
            'shipped' => __('lang_v1.shipped'),
            'delivered' => __('lang_v1.delivered'),
            'cancelled' => __('restaurant.cancelled')
        ];

        return $statuses;
    }

    /**
     * Retrieves sum of due amount of a contact
     * @param int $contact_id
     *
     * @return mixed
     */
    public function getContactDue($contact_id)
    {
        $contact_payments = Contact::where('contacts.id', $contact_id)
                    ->join('transactions AS t', 'contacts.id', '=', 't.contact_id')
                    ->whereIn('t.type', ['sell', 'opening_balance', 'purchase'])
                    ->select(
                        DB::raw("SUM(IF(t.status = 'final' AND t.type = 'sell', final_total, 0)) as total_invoice"),
                        DB::raw("SUM(IF(t.type = 'purchase', final_total, 0)) as total_purchase"),
                        DB::raw("SUM(IF(t.status = 'final' AND t.type = 'sell', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as total_paid"),
                        DB::raw("SUM(IF(t.type = 'purchase', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_paid"),
                        DB::raw("SUM(IF(t.type = 'opening_balance', final_total, 0)) as opening_balance"),
                        DB::raw("SUM(IF(t.type = 'opening_balance', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as opening_balance_paid")
                    )->first();
        $due = $contact_payments->total_invoice + $contact_payments->total_purchase - $contact_payments->total_paid - $contact_payments->purchase_paid + $contact_payments->opening_balance - $contact_payments->opening_balance_paid;

        return $due;
    }

    public function getDays()
    {
      return [
            'sunday' => __('lang_v1.sunday'),
            'monday' => __('lang_v1.monday'),
            'tuesday' => __('lang_v1.tuesday'),
            'wednesday' => __('lang_v1.wednesday'),
            'thursday' => __('lang_v1.thursday'),
            'friday' => __('lang_v1.friday'),
            'saturday' => __('lang_v1.saturday')
        ];
    }
}
