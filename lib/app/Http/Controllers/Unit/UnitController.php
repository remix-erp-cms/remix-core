<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Unit;
use App\Product;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;

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
                ->with(['base_unit'])
                ->select(['actual_name', 'short_name', 'allow_decimal', 'id',
                    'base_unit_id', 'base_unit_multiplier'])
                ->orderBy('created_at', "desc");

            if (isset($request->keyword) && $request->keyword) {
                $unit->where("actual_name", "LIKE", "%$request->keyword%");
            }

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
}
