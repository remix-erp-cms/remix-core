<?php

namespace App\Http\Controllers\Options;

use App\Http\Controllers\Controller;
use App\Option;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AjaxController extends Controller
{

    public function __construct()
    {
    }

    public function list(Request $request)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;

            $options = Option::with([
                'business',
                'created_user'
            ])
                ->where('business_id', $business_id);

            if (isset($request->type) && $request->type) {
                $options->where('options.type', $request->type);
            }

            if (isset($request->value) && $request->value) {
                $options->where('options.value', 'LIKE', "%$request->value%");
            }

            if (isset($request->name) && $request->name) {
                $options->where('options.name', 'LIKE', "%$request->name%");
            }

            if (isset($request->auto_load) && $request->auto_load) {
                $options->where('options.auto_load', $request->auto_load);
            }

            $options->select();

            $data = $options->paginate($request->limit);

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function create(Request $request)
    {
        DB::beginTransaction();
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input = $request->only(['name', 'amount', 'value', 'images', 'auto_load', 'type']);

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            $result = Option::create($input);

            if (!$result) {
                $message = "Không thể lưu dữ liệu";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Thêm dữ liệu thành công";

            return $this->respondSuccess($result, $message);
        } catch (\Exception $e) {
            DB::commit();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function detail($id)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;

            $option = Option::where('business_id', $business_id)
            ->findOrFail($id);

            $message = "Lấy dữ liệu thành công";

            return $this->respondSuccess($option, $message);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $input = $request->only(['name', 'amount', 'value', 'images', 'auto_load', 'type']);

            $business_id = Auth::guard('api')->user()->business_id;

            $options = Option::where('business_id', $business_id)->findOrFail($id);

            if(isset($input['name'])) {
                $options->name = $input['name'];
            }
            if (isset($input['amount'])) {
                $options->amount = $input['amount'];
            }
            if (isset($input['value'])) {
                $options->value = $input['value'];
            }
            if (isset($input['images'])) {
                $options->images = $input['images'];
            }
            if (isset($input['auto_load'])) {
                $options->auto_load = $input['auto_load'];
            }
            if (isset($input['type'])) {
                $options->type = $input['type'];
            }

            $result = $options->save();

            if (!$result) {
                $message = "Không thể cập nhật dữ liệu";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Cập nhật dữ liệu thành công";

            return $this->respondSuccess($result, $message);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    public function delete($id)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;

            $tax_rate = Option::where('business_id', $business_id)->findOrFail($id);
            $result = $tax_rate->delete();

            if (!$result) {
                $message = "Không thể cập nhật dữ liệu";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Xóa dữ dữ liệu thành công";
            return $this->respondSuccess($result, $message);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }
}
