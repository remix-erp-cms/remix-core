<?php

namespace App\Http\Controllers\Options;

use App\Http\Controllers\Controller;
use App\Menu;
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

            if (isset($request->sub_type) && $request->sub_type) {
                $options->where('options.sub_type', $request->sub_type);
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

            if ($request->sort == true) {
                $options->orderBy('name', "asc");
                $options->orderBy('amount', "asc");
                $options->orderBy('sub_type', "asc");
            }

            $options->orderBy('created_at', "desc");

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

            if (isset($request->data) && count($request->data) > 0) {
                $listData = $request->data;

                foreach ($listData as $menu) {
                    $menu = (object)$menu;

                    $inputData = [
                        'name' => $menu->name,
                        'value' => isset($menu->value) ? $menu->value : null,
                        'amount' => isset($menu->amount) ? $menu->amount : null,
                        'description' => isset($menu->description) ? $menu->description : null,
                        'type' => $menu->type,
                        'sub_type' => isset($menu->sub_type) ? $menu->sub_type : null,
                        'auto_load' => isset($menu->auto_load) ? $menu->auto_load : 0,
                        "business_id" => $business_id,
                        "created_by" => $user_id,
                    ];

                    if (isset($input['images'])) {
                        $urlImage = !empty($input["images"]) ? str_replace(url("/"), "", $input["images"]) : "";
                        $input['images'] = $urlImage;
                    }

                    $checkMenu = Option::where('name', $menu->name)
                        ->where("type", $menu->type)
                        ->first();

                    if ($checkMenu) {
                        $result = Option::where('name', $menu->name)
                            ->where("type", $menu->type)
                            ->update($inputData);

                        if (!$result) {
                            $message = "Không thể lưu dữ liệu";
                            return $this->respondWithError($message, [], 503);
                        }
                    } else {
                        if (isset($inputData['images'])) {
                            $urlImage = !empty($inputData["images"]) ? str_replace(url("/"), "", $inputData["images"]) : "";
                            $inputData['images'] = $urlImage;
                        }

                        $result = Option::create($inputData);

                        if (!$result) {
                            $message = "Không thể lưu dữ liệu";
                            return $this->respondWithError($message, [], 503);
                        }
                    }
                }


            } else {
                $input = $request->only(['name', 'amount', 'value', 'images', 'auto_load', 'type', 'sub_type']);
                $input['business_id'] = $business_id;
                $input['created_by'] = $user_id;

                if (isset($input['images'])) {
                    $urlImage = !empty($input["images"]) ? str_replace(url("/"), "", $input["images"]) : "";
                    $input['images'] = $urlImage;
                }

                $result = Option::create($input);

                if (!$result) {
                    $message = "Không thể lưu dữ liệu";
                    return $this->respondWithError($message, [], 503);
                }
            }

            DB::commit();
            $message = "Thêm dữ liệu thành công";

            return $this->respondSuccess($message, $message);
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
            $input = $request->only(['name', 'amount', 'value', 'images', 'auto_load', 'type', 'sub_type']);

            $business_id = Auth::guard('api')->user()->business_id;

            $options = Option::where('business_id', $business_id)->findOrFail($id);

            if (isset($input['name'])) {
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

            if (isset($input['images'])) {
                $urlImage = !empty($input["images"]) ? str_replace(url("/"), "", $input["images"]) : "";
                $options->images = $urlImage;
            }

            if (isset($input['auto_load'])) {
                $options->auto_load = $input['auto_load'];
            }
            if (isset($input['type'])) {
                $options->type = $input['type'];
            }

            if (isset($input['sub_type'])) {
                $options->sub_type = $input['sub_type'];
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
