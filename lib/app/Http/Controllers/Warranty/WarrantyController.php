<?php

namespace App\Http\Controllers\Warranty;

use App\Brands;
use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use App\Warranty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class WarrantyController extends Controller
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

            $warranties = Warranty::where('business_id', $business_id)
                ->select(['name', 'description', 'id', "duration", "duration_type"]);

            if (isset($request->keyword) && $request->keyword) {
                $warranties->where("name", "LIKE", "%$request->keyword%");
            }

            $data = $warranties->paginate($request->limit);

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
            $request->validate([
                'name',
                'duration',
                'duration_type'
            ]);

            $input = $request->only(['name', 'description', "duration", "duration_type"]);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            $warranty = Warranty::create($input);

            if (!$warranty) {
                DB::rollBack();
                $message = "Lỗi trong quá trình tạo chính sách bảo hành";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Thêm chính sách bảo hành thành công";

            return $this->respondSuccess($warranty, $message);
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

            $warranty = Warranty::where('business_id', $business_id)->findOrFail($id);

            return $this->respondSuccess($warranty);
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
            $request->validate([
                'name',
                'duration',
                'duration_type'
            ]);

            $input = $request->only(['name', 'description', "duration", "duration_type"]);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $warranty = Warranty::where('business_id', $business_id)->findOrFail($id);
            $warranty->name = $input['name'];
            $warranty->description = $input['description'];
            $warranty->duration = $input['duration'];
            $warranty->duration_type = $input['duration_type'];

            $warranty->save();

            DB::commit();
            $message = "Cập nhật chính sách bảo hành thành công";

            return $this->respondSuccess($warranty, $message);

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

            $warranty = Warranty::where('business_id', $business_id)->findOrFail($id);
            $warranty->delete();
            DB::commit();
            $message = "Xóa chính sách bảo hành thành công";

            return $this->respondSuccess($warranty, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

}
