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
                ->select([
                    'id',
                    'parent_id',
                    'order',
                    'scope',
                    'target',
                    'role',
                    'url',
                    'title'
                ])
                ->with([
                    "parent:id,parent_id,scope,target,role,url,title,order",
                    "children:id,parent_id,scope,target,role,url,title,order",
                    "children.children:id,parent_id,scope,target,role,url,title,order"
                ]);

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
                $menus->where('parent_id', $parent_id);
            } else {
                $menus->where('parent_id', null);
            }

            $menus->orderBy('parent_id', "asc");
            $menus->orderBy('order', "asc");
            $menus->orderBy('created_at', "desc");
            $menus->orderBy('title', "asc");

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
            ]);

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            $slug = '';

            if (isset($request->title) && $request->title) {
                $slug = Str::slug($request->title, '-');
            }

            if (isset($request->url) && $request->url) {
                $slug = $request->url;
            }

            $input['url'] = $slug;

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
            ]);


            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $menus = Menu::where('business_id', $business_id)->findOrFail($id);

            $menus->title = $input['title'];
            $menus->icon = isset($input['icon']) ? $input['icon'] : null;
            $menus->rating = isset($input['rating']) ? $input['rating'] : null;
            if (isset($input['description'])) {
                $menus->description = $input['description'];
            }

            if (isset($input['scope'])) {
                $menus->scope = $input['scope'];
            }

            if (isset($input['role'])) {
                $menus->role = $input['role'];
            }


            if (isset($input['target'])) {
                $menus->target = $input['target'];
            }

            if (isset($input['order'])) {
                $menus->order = $input['order'];
            }

            if (isset($input['parent_id'])) {
                $menus->parent_id = $input['parent_id'];
            }

            $slug = '';

            if (isset($request->title) && $request->title) {
                $slug = Str::slug($request->title, '-');
            }

            if (isset($request->url) && $request->url) {
                $slug = $request->url;
            }

            $menus->url = $slug;


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

    private function updateMenuList($menus, $parent_id = null) {
        foreach ($menus as $key=>$menu) {
            $menu_id = $menu["key"];
            $dataUpdate = [
                'order' => $key,
                'parent_id' => $menu["parent_id"]
            ];

            if ($parent_id) {
                $dataUpdate["parent_id"] = $parent_id;
            }

            $result = Menu::where('id', $menu_id)
                ->update($dataUpdate);

            if (!$result) {
                return false;
            }

            if (isset($menu['children']) && count($menu['children']) > 0) {
                $this->updateMenuList($menu['children'], $menu_id);
            }
        }
        return true;
    }

    public function updateNode(Request $request)
    {
        DB::beginTransaction();
        try {

            $input = $request->only([
                'menus',
                'parent_id'
            ]);

            $request->validate([
                'menus' => 'required',
                'parent_id' => 'required',
            ]);

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $result  = $this->updateMenuList($request->menus);

            if (!$result) {
                DB::rollBack();
                $message = "Lỗi trong quá trình tạo menu";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Cập nhật menu thành công";

            return $this->respondSuccess($request->menus, $message);
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
