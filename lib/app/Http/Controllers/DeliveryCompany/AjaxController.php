<?php

namespace App\Http\Controllers\DeliveryCompany;

use App\Brands;
use App\DeliveryCompany;
use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AjaxController extends Controller
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

            $deliveryCompany = DeliveryCompany::where('business_id', $business_id);

            if (isset($request->keyword) && $request->keyword) {
                $deliveryCompany->where("name", "LIKE", "%$request->keyword%");
            }

            $deliveryCompany->orderBy('created_at', "desc");
            $data = $deliveryCompany->paginate($request->limit);

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
            $input = $request->only(['name', 'description', 'link', 'image', 'tracking']);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            $image = $request->input('image');
            $input['image'] = !empty($image) ? str_replace(url("/"), "", $image)  : "";

            $deliveryCompany = DeliveryCompany::create($input);

            if (!$deliveryCompany) {
                DB::rollBack();
                $message = "Lỗi trong quá trình tạo nhãn hiệu";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Thêm nhãn hiệu thành công";

            return $this->respondSuccess($deliveryCompany, $message);
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

            $deliveryCompany = DeliveryCompany::where('business_id', $business_id)->findOrFail($id);

            return $this->respondSuccess($deliveryCompany);
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
            $input = $request->only(['name', 'description', 'link', 'image', 'tracking']);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $deliveryCompany = DeliveryCompany::where('business_id', $business_id)->findOrFail($id);
            $deliveryCompany->name = $input['name'];
            $deliveryCompany->description = $input['description'];
            $deliveryCompany->link = $input['link'];
            $deliveryCompany->tracking = $input['tracking'];

            $image = $input['image'];
            if (!empty($image)) {
                $deliveryCompany->image = str_replace(url("/"), "", $image);
            }

            $deliveryCompany->save();

            DB::commit();
            $message = "Cập nhật nhãn hiệu thành công";

            return $this->respondSuccess($deliveryCompany, $message);

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

            $deliveryCompany = DeliveryCompany::where('business_id', $business_id)->findOrFail($id);
            $deliveryCompany->delete();
            DB::commit();
            $message = "Xóa nhãn hiệu thành công";

            return $this->respondSuccess($deliveryCompany, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

}
