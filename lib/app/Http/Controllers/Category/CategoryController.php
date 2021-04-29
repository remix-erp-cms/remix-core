<?php

namespace App\Http\Controllers\Category;

use App\Category;
use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use Excel;

class CategoryController extends Controller
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

            $category = Category::where('business_id', $business_id)
                ->with(["parent"])
                ->select(['name', 'short_code', 'description', 'id', 'parent_id', 'slug']);

            if (isset($request->keyword) && $request->keyword) {
                $category->where("name", "LIKE", "%$request->keyword%");
            }

            if (isset($request->type) && $request->type) {
                $category->where("category_type",$request->type);
            }

            $parent_id = request()->get('parent_id', null);
            if (!empty($parent_id)) {
                $category->where('parent_id', $parent_id)
                    ->orWhere('id', $parent_id);
            }

            $category->orderBy('created_at', "desc");
            $category->orderBy('name', "asc");

            $data = $category->paginate($request->limit);

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
            $input = $request->only(['name', 'short_code', 'type', 'description', 'image', "slug", "parent_id"]);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            $slug = '';

            if(isset($request->name) && $request->name) {
                $slug = Str::slug($request->name, '-');
            }

            if(isset($request->slug) && $request->slug) {
                $slug = $request->slug;
            }

            $input["slug"] = $slug;
            $input['category_type'] = $request->type;

            if (isset($input['image'])) {
                $urlImage = !empty($input["image"]) ? str_replace(url("/"), "", $input["image"]) : "";
                $input['image'] = $urlImage;
            }

            $category = Category::create($input);

            DB::commit();
            $message = "Thêm danh mục thành công";

            return $this->respondSuccess($category, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Category $category
     * @return \Illuminate\Http\Response
     */
    public function detail($id)
    {
        DB::beginTransaction();
        try {
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $category = Category::where('business_id', $business_id)->findOrFail($id);

            return $this->respondSuccess($category);
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
            $input = $request->only(['name', 'short_code', 'type', 'description', 'image', "slug", "parent_id"]);
            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $category = Category::where('business_id', $business_id)->findOrFail($id);
            $category->name = $input['name'];
            if (isset($input['short_code'])) {
                $category->short_code = $input['short_code'];
            }

            if (isset($input['type'])) {
                $category->category_type = $input['type'];
            }

            if (isset($input['description'])) {
                $category->description = $input['description'];
            }

            if (isset($input['image'])) {
                $urlImage = !empty($input["image"]) ? str_replace(url("/"), "", $input["image"]) : "";

                $category->image = $urlImage;
            }

            $slug = '';

            if(isset($request->name) && $request->name) {
                $slug = Str::slug($request->name, '-');
            }

            if(isset($request->slug) && $request->slug) {
                $slug = $request->slug;
            }

            $category->slug = $slug;

            if (isset($input['parent_id'])) {
                $category->parent_id = $input['parent_id'];
            }

            $category->save();

            DB::commit();
            $message = "Cập nhật danh mục thành công";

            return $this->respondSuccess($category, $message);

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

                $category = Category::where('business_id', $business_id)->findOrFail($id);
                $category->delete();

                DB::commit();
                $message = "Xóa danh mục thành công";

                return $this->respondSuccess($category, $message);
            } catch (\Exception $e) {
                DB::rollBack();
                $message = $e->getMessage();

                return $this->respondWithError($message, [], 500);
            }
    }

    public function getCategoriesApi()
    {
        try {
            $api_token = request()->header('API-TOKEN');

            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $categories = Category::catAndSubCategories($api_settings->business_id);
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($categories);
    }

    /**
     * get taxonomy index page
     * through ajax
     * @return \Illuminate\Http\Response
     */
    public function getTaxonomyIndexPage(Request $request)
    {
        if (request()->ajax()) {
            $category_type = $request->get('category_type');
            $module_category_data = $this->moduleUtil->getTaxonomyData($category_type);

            return view('taxonomy.ajax_index')
                ->with(compact('module_category_data', 'category_type'));
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
                    $category_array = [];
                    $category_array['business_id'] = $business_id;
                    $category_array['created_by'] = $user_id;
                    $category_array['category_type'] = "product";


                    //Add SKU
                    $actual_name = trim($value[0]);
                    if (!empty($actual_name)) {
                        $category_array['name'] = $actual_name;
                        //Check if product with same SKU already exist
                        $is_exist = Category::where('name', $category_array['name'])
                            ->where('business_id', $business_id)
                            ->exists();
                        if ($is_exist) {
                            $is_valid = false;
                            $error_msg = "Tên danh mục : $actual_name đã tồn tại ở dòng thứ. $row_no";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "Thiếu tên danh mục";
                        break;
                    }

                    //Add product name
                    $description = trim($value[1]);
                    if (!empty($description)) {
                        $category_array['description'] = $description;
                    }

                    $formated_data[] = $category_array;
                }

                if (!$is_valid) {
                    throw new \Exception($error_msg);
                }

                if (!empty($formated_data)) {
                    foreach ($formated_data as $index => $category_data) {
                        //Create new product
                        Category::create($category_data);
                    }
                }
            }

            DB::commit();
            $message = "Nhập liệu danh mục thành công";

            return $this->respondSuccess($message, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

}
