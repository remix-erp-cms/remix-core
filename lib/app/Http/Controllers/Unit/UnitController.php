<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Unit;
use App\Product;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use Excel;

use App\Utils\Util;

class UnitController extends Controller
{
    /**
     * All Utils instance.
     *
     */
    protected $commonUtil;

    /**
     * Constructor
     *
     * @param ProductUtils $product
     * @return void
     */
    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
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

            $unit = Unit::where('business_id', $business_id)
                ->select(['actual_name', 'short_name', 'allow_decimal', 'id',
                    'base_unit_id', 'base_unit_multiplier']);

            if (isset($request->keyword) && $request->keyword) {
                $unit->where("actual_name", "LIKE", "%$request->keyword%");
            }

            $sort_field = "created_at";
            $sort_des = "desc";

            if(isset($request->order_field) && $request->order_field) {
                $sort_field = $request->order_field;
            }

            if(isset($request->order_by) && $request->order_by) {
                $sort_des = $request->order_by;
            }

            $unit->orderBy($sort_field, $sort_des);

            $data = $unit->paginate($request->limit);

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
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input = $request->only(['actual_name', 'short_name', 'allow_decimal']);
            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            if ($request->has('define_base_unit')) {
                if (!empty($request->input('base_unit_id')) && !empty($request->input('base_unit_multiplier'))) {
                    $base_unit_multiplier = $this->commonUtil->num_uf($request->input('base_unit_multiplier'));
                    if ($base_unit_multiplier != 0) {
                        $input['base_unit_id'] = $request->input('base_unit_id');
                        $input['base_unit_multiplier'] = $base_unit_multiplier;
                    }
                }
            }

            $unit = Unit::create($input);

            if (!$unit) {
                DB::rollBack();
                $message = "Lỗi trong quá trình tạo đơn vị";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Thêm đơn vị thành công";

            return $this->respondSuccess($unit, $message);
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

            $unit = Unit::where('business_id', $business_id)->findOrFail($id);

            return $this->respondSuccess($unit);
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
                $input = $request->only(['actual_name', 'short_name', 'allow_decimal']);

                $business_id = Auth::guard('api')->user()->business_id;
                $user_id = Auth::guard('api')->user()->id;

                $unit = Unit::where('business_id', $business_id)->findOrFail($id);
                $unit->actual_name = $input['actual_name'];
                $unit->short_name = $input['short_name'];
                $unit->allow_decimal = $input['allow_decimal'];
                if ($request->has('define_base_unit')) {
                    if (!empty($request->input('base_unit_id')) && !empty($request->input('base_unit_multiplier'))) {
                        $base_unit_multiplier = $this->commonUtil->num_uf($request->input('base_unit_multiplier'));
                        if ($base_unit_multiplier != 0) {
                            $unit->base_unit_id = $request->input('base_unit_id');
                            $unit->base_unit_multiplier = $base_unit_multiplier;
                        }
                    }
                } else {
                    $unit->base_unit_id = null;
                    $unit->base_unit_multiplier = null;
                }

                $unit->save();

                DB::commit();
                $message = "Cập nhật đơn vị thành công";

                return $this->respondSuccess($unit, $message);
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

            $unit = Unit::where('business_id', $business_id)->findOrFail($id);

            //check if any product associated with the unit
            $exists = Product::where('unit_id', $unit->id)
                ->exists();

            if ($exists) {
                DB::rollBack();
                $message = "Không tồn tại đơn vị đơn vị";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Xóa đơn vị thành công";
            $unit->delete();

            return $this->respondSuccess($unit, $message);
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
                    if (count($value) < 2 ) {
                        $is_valid =  false;
                        $error_msg = "Thiếu cột trong quá trình tải lên dữ liệu. vui lòng tuần thủ dữ template";
                        break;
                    }

                    $row_no = $key + 1;
                    $unit_array = [];
                    $unit_array['business_id'] = $business_id;
                    $unit_array['created_by'] = $user_id;


                    //Add id
                    $id = trim($value[0]);

                    $unit_array['id'] = $id;

                    //Add SKU
                    $actual_name = trim($value[1]);
                    if (!empty($actual_name)) {
                        $unit_array['actual_name'] = $actual_name;
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu tên đơn vị";
                        break;
                    }

                    //Add product name
                    $short_name = trim($value[2]);
                    if (!empty($short_name)) {
                        $unit_array['short_name'] = $short_name;
                    } else {
                        $is_valid =  false;
                        $error_msg = "Không tìm thấy tên viết tắt. $row_no";
                        break;
                    }

                    $formated_data[] = $unit_array;
                }

                if (!$is_valid) {
                    throw new \Exception($error_msg);
                }

                if (!empty($formated_data)) {
                    foreach ($formated_data as $index => $item) {
                        $exitItem = Unit::where('id', $item['id'])
                            ->first();

                        if($exitItem && $is_replace === true) {
                            $dataUpdate = [];

                            foreach ($columnReplace as $value) {
                                if (isset($item[$value])) {
                                    $dataUpdate[$value] = $item[$value];
                                }

                                Unit::where('id', $item['id'])
                                    ->update($dataUpdate);
                            }
                        } else {
                            Unit::create($item);
                        }
                    }
                }
            }

            DB::commit();
            $message = "Nhập liệu đơn vị thành công";

            return $this->respondSuccess($message, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }


}
