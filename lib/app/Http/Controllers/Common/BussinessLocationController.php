<?php

namespace App\Http\Controllers\Common;

use App\BusinessLocation;
use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Excel;

class BussinessLocationController extends Controller
{
    /**
     * All Utils instance.
     *
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param ProductUtils $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
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

            $locations = BusinessLocation::where('business_id', $business_id)
                ->select();

            if (isset($request->keyword) && $request->keyword) {
                $locations->where("name", "LIKE", "%$request->keyword%");
            }

            $data = $locations->paginate($request->limit);

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        DB::beginTransaction();

        try {
            $input = $request->only(['location_id', 'name', 'landmark', 'mobile', 'email', 'website', 'is_active']);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            $location = BusinessLocation::create($input);

            if (!$location) {
                DB::rollBack();
                $message = "Lỗi trong quá trình tạo cửa hàng";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Thêm cửa hàng thành công";

            return $this->respondSuccess($location, $message);
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
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $location = BusinessLocation::where('business_id', $business_id)->findOrFail($id);

            return $this->respondSuccess($location);
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
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $input = $request->only(['location_id', 'name', 'landmark', 'mobile', 'email', 'website', 'is_active']);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $location = BusinessLocation::where('business_id', $business_id)->findOrFail($id);

            if (isset($input['name'])) {
                $location->name = $input['name'];
            }

            if (isset($input['landmark'])) {
                $location->landmark = $input['landmark'];
            }

            if (isset($input['mobile'])) {
                $location->mobile = $input['mobile'];
            }

            if (isset($input['email'])) {
                $location->email = $input['email'];
            }

            if (isset($input['website'])) {
                $location->website = $input['website'];
            }

            if (isset($input['is_active'])) {
                $location->is_active = $input['is_active'];
            }

            if (isset($input['location_id'])) {
                $location->location_id = $input['location_id'];
            }

            $location->save();

            DB::commit();
            $message = "Cập nhật cửa hàng thành công";

            return $this->respondSuccess($location, $message);

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
    public function delete($id)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $location = BusinessLocation::where('business_id', $business_id)->findOrFail($id);
            $location->delete();
            DB::commit();
            $message = "Xóa cửa hàng thành công";

            return $this->respondSuccess($location, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function importData(Request $request)
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

                $total_rows = count($imported_data);

                foreach ($imported_data as $key => $value) {
                    //Check if any column is missing
                    if (count($value) < 2 ) {
                        $is_valid =  false;
                        $error_msg = "Thiếu cột trong quá trình tải lên dữ liệu. vui lòng tuần thủ dữ template";
                        break;
                    }

                    $row_no = $key + 1;
                    $location_array = [];
                    $location_array['business_id'] = $business_id;
                    $location_array['created_by'] = $user_id;


                    //Add SKU
                    $actual_name = trim($value[0]);
                    if (!empty($actual_name)) {
                        $location_array['name'] = $actual_name;
                        //Check if product with same SKU already exist
                        $is_exist = BusinessLocation::where('name', $location_array['name'])
                            ->where('business_id', $business_id)
                            ->exists();
                        if ($is_exist) {
                            $is_valid = false;
                            $error_msg = "Tên cửa hàng : $actual_name đã tồn tại ở dòng thứ. $row_no";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu tên cửa hàng";
                        break;
                    }

                    //Add product name
                    $description = trim($value[1]);
                    if (!empty($description)) {
                        $location_array['description'] = $description;
                    }

                    $formated_data[] = $location_array;
                }

                if (!$is_valid) {
                    throw new \Exception($error_msg);
                }

                if (!empty($formated_data)) {
                    foreach ($formated_data as $index => $location_data) {
                        //Create new product
                        BusinessLocation::create($location_data);
                    }
                }
            }

            DB::commit();
            $message = "Nhập liệu cửa hàng thành công";

            return $this->respondSuccess($message, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }


}
