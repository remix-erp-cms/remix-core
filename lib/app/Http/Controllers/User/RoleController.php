<?php

namespace App\Http\Controllers\User;

use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Excel;

class RoleController extends Controller
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

            $Role = Role::where('business_id', $business_id)
                ->where("id","<>", 1)
                ->select(['name', 'id', 'is_default', 'business_id']);

            if (isset($request->keyword) && $request->keyword) {
                $Role->where("name", "LIKE", "%$request->keyword%");
            }

            $data = $Role->paginate($request->limit);

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

            $role_name = $request->input('name');
            $permissions = $request->input('permissions');

            $count = Role::where('name', $role_name . '#' . $business_id)
                ->where('business_id', $business_id)
                ->count();
            if ($count == 0) {
                $is_service_staff = 0;
                if ($request->input('is_service_staff') == 1) {
                    $is_service_staff = 1;
                }

                $role = Role::create([
                    'name' => $role_name,
                    'business_id' => $business_id,
                    'is_service_staff' => $is_service_staff
                ]);

                //Include selling price group permissions
                $spg_permissions = $request->input('spg_permissions');
                if (!empty($spg_permissions)) {
                    foreach ($spg_permissions as $spg_permission) {
                        $permissions[] = $spg_permission;
                    }
                }

                $this->__createPermissionIfNotExists($permissions);

                if (!empty($permissions)) {
                    $role->syncPermissions($permissions);
                }

                DB::commit();
                $message = "Thêm nhóm quyền thành công!";

                return $this->respondSuccess($role, $message);
            }

            DB::rollBack();
            $message = "Nhóm quyền đã tồn tại";

            return $this->respondWithError($message, [], 500);

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

            $role = Role::where('business_id', $business_id)
                ->with(['permissions'])
                ->find($id);
            $role_permissions = [];
            foreach ($role->permissions as $role_perm) {
                $role_permissions[] = $role_perm->name;
            }

            $data = [
                "role" => $role,
                "permissions" => $role_permissions,
            ];

            return $this->respondSuccess($data);
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
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $role_name = $request->input('name');
            $permissions = $request->input('permissions');

            $count = Role::where('name', $role_name . '#' . $business_id)
                ->where('id', '!=', $id)
                ->where('business_id', $business_id)
                ->count();
            if ($count == 0) {
                $role = Role::findOrFail($id);

                if (!$role->is_default || $role->name == 'Cashier#' . $business_id) {
                    if ($role->name == 'Cashier#' . $business_id) {
                        $role->is_default = 0;
                    }

                    $is_service_staff = 0;
                    if ($request->input('is_service_staff') == 1) {
                        $is_service_staff = 1;
                    }
                    $role->is_service_staff = $is_service_staff;
                    $role->name = $role_name;
                    $role->save();

                    //Include selling price group permissions
                    $spg_permissions = $request->input('spg_permissions');
                    if (!empty($spg_permissions)) {
                        foreach ($spg_permissions as $spg_permission) {
                            $permissions[] = $spg_permission;
                        }
                    }

                    $this->__createPermissionIfNotExists($permissions);

                    if (!empty($permissions)) {
                        $role->syncPermissions($permissions);
                    }

                    DB::commit();
                    $message = "Cập nhật nhóm quyền thành công";

                    return $this->respondSuccess([], $message);;
                } else {

                    DB::commit();
                    $message = "Bạn không thể sửa đổi nhóm quyền này";

                    return $this->respondSuccess([], $message, 500);
                }
            } else {

                DB::commit();
                $message = "Nhóm quyền không tồn tại";

                return $this->respondSuccess([], $message, 500);
            }

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

            $role = Role::where('business_id', $business_id)->find($id);

            if (!$role->is_default || $role->name == 'Cashier#' . $business_id) {
                $role->delete();
                DB::commit();
                $message = "Xóa nhóm quyền thành công";

                return $this->respondSuccess([], $message);
            } else {
                $message = "Bạn không có quyền sửa nhóm quyền này";

                return $this->respondSuccess([], $message);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    private function __createPermissionIfNotExists($permissions)
    {
        $exising_permissions = Permission::whereIn('name', $permissions)
            ->pluck('name')
            ->toArray();

        $non_existing_permissions = array_diff($permissions, $exising_permissions);

        if (!empty($non_existing_permissions)) {
            foreach ($non_existing_permissions as $new_permission) {
                $time_stamp = \Carbon::now()->toDateTimeString();
                Permission::create([
                    'name' => $new_permission,
                    'guard_name' => 'web'
                ]);
            }
        }
    }
}
