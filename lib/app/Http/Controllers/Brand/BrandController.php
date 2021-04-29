<?php

namespace App\Http\Controllers\Brand;

use App\Brands;
use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Excel;

class BrandController extends Controller
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

            $brands = Brands::where('business_id', $business_id)
                ->select(['name', 'description', 'image', 'id']);

            if (isset($request->keyword) && $request->keyword) {
                $brands->where("name", "LIKE", "%$request->keyword%");
            }

            $data = $brands->paginate($request->limit);

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
            $input = $request->only(['name', 'description', 'image']);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            if (isset($input['image'])) {
                $urlImage = !empty($input["image"]) ? str_replace(url("/"), "", $input["image"]) : "";
                $input['image'] = $urlImage;
            }

            $brand = Brands::create($input);

            if (!$brand) {
                DB::rollBack();
                $message = "Lỗi trong quá trình tạo nhãn hiệu";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Thêm nhãn hiệu thành công";

            return $this->respondSuccess($brand, $message);
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

            $brand = Brands::where('business_id', $business_id)->findOrFail($id);

            return $this->respondSuccess($brand);
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
            $input = $request->only(['name', 'description', 'image']);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $brand = Brands::where('business_id', $business_id)->findOrFail($id);

            if (isset($input['name'])) {
                $brand->name = $input['name'];
            }


            if (isset($input['description'])) {
                $brand->description = $input['description'];
            }

            if (isset($input['image'])) {
                $urlImage = !empty($input["image"]) ? str_replace(url("/"), "", $input["image"]) : "";

                $brand->image = $urlImage;
            }

            $brand->save();

            DB::commit();
            $message = "Cập nhật nhãn hiệu thành công";

            return $this->respondSuccess($brand, $message);

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

            $brand = Brands::where('business_id', $business_id)->findOrFail($id);
            $brand->delete();
            DB::commit();
            $message = "Xóa nhãn hiệu thành công";

            return $this->respondSuccess($brand, $message);
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
                    $brand_array = [];
                    $brand_array['business_id'] = $business_id;
                    $brand_array['created_by'] = $user_id;


                    //Add SKU
                    $actual_name = trim($value[0]);
                    if (!empty($actual_name)) {
                        $brand_array['name'] = $actual_name;
                        //Check if product with same SKU already exist
                        $is_exist = Brands::where('name', $brand_array['name'])
                            ->where('business_id', $business_id)
                            ->exists();
                        if ($is_exist) {
                            $is_valid = false;
                            $error_msg = "Tên nhãn hiệu : $actual_name đã tồn tại ở dòng thứ. $row_no";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu tên nhãn hiệu";
                        break;
                    }

                    //Add product name
                    $description = trim($value[1]);
                    if (!empty($description)) {
                        $brand_array['description'] = $description;
                    }

                    $formated_data[] = $brand_array;
                }

                if (!$is_valid) {
                    throw new \Exception($error_msg);
                }

                if (!empty($formated_data)) {
                    foreach ($formated_data as $index => $brand_data) {
                        //Create new product
                        Brands::create($brand_data);
                    }
                }
            }

            DB::commit();
            $message = "Nhập liệu nhãn hiệu thành công";

            return $this->respondSuccess($message, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }


}
