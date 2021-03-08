<?php

namespace App\Http\Controllers\Menu;

use App\Menu;
use App\Brands;
use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class MenuController extends Controller
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

            $menus = Menu::where('business_id', $business_id)
                ->with(["parent"]);

            if (isset($request->keyword) && $request->keyword) {
                $menus->where("title", "LIKE", "%$request->keyword%");
            }

            if (isset($request->description) && $request->description) {
                $menus->where("description", "LIKE", "%$request->description%");
            }

            $role = request()->get('role', null);
            if (!empty($role)) {
                $menus->where('role', $role);
            }

            $scope = request()->get('scope', null);
            if (!empty($scope)) {
                $menus->where('scope', $scope);
            }
            $target = request()->get('target', null);
            if (!empty($target)) {
                $menus->where('target', $target);
            }

            $parent_id = request()->get('parent_id', null);
            if (!empty($parent_id)) {
                $menus->where('parent_id', $parent_id)
                    ->orWhere('id', $parent_id);
            } else {
                $menus->where('parent_id', '=', null );
            }

            $menus->orderBy('parent_id', "asc");
            $menus->orderBy('order', "desc");
            $menus->orderBy('title', "asc");
            $menus->orderBy('created_at', "desc");

            $data = $menus->paginate($request->limit);

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
            $input = $request->only([
                'title',
                'url',
                'icon',
                'description',
                'rating',
                'role',
                'scope',
                'target',
                'order',
                'parent_id'
            ]);

            $request->validate([
                'title' => 'required',
                'url' => 'required',
            ]);

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            $menus = Menu::create($input);

            if (!$menus) {
                DB::rollBack();
                $message = "Lỗi trong quá trình tạo menu";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Thêm menu thành công";

            return $this->respondSuccess($menus, $message);
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

            $menus = Menu::where('business_id', $business_id)
                ->with(["parent"])
                ->findOrFail($id);

            return $this->respondSuccess($menus);
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
            $input = $request->only([
                'title',
                'url',
                'icon',
                'description',
                'rating',
                'role',
                'scope',
                'target',
                'order',
                'parent_id'
            ]);

            $request->validate([
                'title' => 'required',
                'url' => 'required',
            ]);


            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $menus = Menu::where('business_id', $business_id)->findOrFail($id);

            $menus->title = $input['title'];
            $menus->url = $input['url'];
            $menus->description = isset($input['description']) ? $input['description'] : null;
            $menus->icon = isset($input['icon']) ? $input['icon'] : null;
            $menus->rating = isset($input['rating']) ? $input['rating'] : null;
            $menus->role = isset($input['role']) ? $input['role'] : null;
            $menus->scope = isset($input['scope']) ? $input['scope'] : null;
            $menus->target = isset($input['target']) ? $input['target'] : null;
            $menus->order = isset($input['order']) ? $input['order'] : null;
            $menus->parent_id = isset($input['parent_id']) ? $input['parent_id'] : null;

            $menus->save();

            DB::commit();
            $message = "Cập nhật menu thành công";

            return $this->respondSuccess($menus, $message);

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

            $menus = Menu::where('business_id', $business_id)->findOrFail($id);
            $menus->delete();
            DB::commit();
            $message = "Xóa menu thành công";

            return $this->respondSuccess($menus, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

}
