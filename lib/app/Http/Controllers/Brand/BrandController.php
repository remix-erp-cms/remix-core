<?php

namespace App\Http\Controllers\Brand;

use App\Brands;
use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

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
                ->select(['name', 'description', 'id']);

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
            $input = $request->only(['name', 'description']);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

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
            $input = $request->only(['name', 'description']);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $brand = Brands::where('business_id', $business_id)->findOrFail($id);
            $brand->name = $input['name'];
            $brand->description = $input['description'];
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

}
