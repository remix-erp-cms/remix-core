<?php

namespace App\Http\Controllers\Blog;

use App\Blog;
use App\Brands;
use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class BlogController extends Controller
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

            $blogs = Blog::where('business_id', $business_id)
            ->with(["category", "created_by"]);

            if (isset($request->keyword) && $request->keyword) {
                $blogs->where("title", "LIKE", "%$request->keyword%");
            }

            if (isset($request->slug) && $request->slug) {
                $blogs->where("slug", "LIKE", "%$request->slug%");
            }

            $type = request()->get('type', null);
            if (!empty($type)) {
                $blogs->where('type', $type);
            }

            $types = request()->get('types', null);
            if (!empty($types)) {
                $blogs->whereIn('type', $types);
            }

            $categoryId = request()->get('category_id', null);
            if (!empty($categoryId)) {
                $blogs->where('category_id', $categoryId);
            }

            $blogs->orderBy('created_at', "desc");
            $data = $blogs->paginate($request->limit);

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
                'slug',
                'description',
                'content',
                'thumb',
                'image',
                'type',
                'status',
                'category_id'
            ]);

            $request->validate([
                'title' => 'required',
                'content' => 'required',
                'type' => 'required',
            ]);

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $input['business_id'] = $business_id;
            $input['created_by'] = $user_id;

            $slug = '';

            if(isset($request->title) && $request->title) {
                $slug = Str::slug($request->title, '-');
            }

            if(isset($request->slug) && $request->slug) {
                $slug = $request->slug;
            }

            $input["slug"] = $slug;

            $image = $request->input('image');
            $input['image'] = !empty($image) ? str_replace(url("/"), "", $image) : "";

            $thumb = $request->input('thumb');
            $input['thumb'] = !empty($thumb) ? str_replace(url("/"), "", $thumb) : "";

            $input['status'] = "draft";

            if(isset($request->status) && $request->status) {
                $input['status'] = $request->status;
            }

            $blogs = Blog::create($input);

            if (!$blogs) {
                DB::rollBack();
                $message = "Lỗi trong quá trình tạo tin tức";
                return $this->respondWithError($message, [], 503);
            }

            DB::commit();
            $message = "Thêm tin tức thành công";

            return $this->respondSuccess($blogs, $message);
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

            $blogs = Blog::where('business_id', $business_id)
                ->with(["category", "created_by"])
                ->findOrFail($id);

            return $this->respondSuccess($blogs);
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
                'slug',
                'description',
                'content',
                'thumb',
                'image',
                'type',
                'status',
                'category_id',
            ]);

            $request->validate([
                'title' => 'required',
                'content' => 'required',
                'type' => 'required',
            ]);


            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;

            $blogs = Blog::where('business_id', $business_id)->findOrFail($id);

            $blogs->title = $input['title'];
            $blogs->content = $input['content'];
            $blogs->type = $input['type'];
            $blogs->status = $input['status'];
            if (isset($input['description'])) {
                $blogs->description = $input['description'];
            }

            if (isset($input['category_id'])) {
                $blogs->category_id = $input['category_id'];
            }

            if (isset($input['auto_load'])) {
                $blogs->auto_load = $input['auto_load'];
            }

            $slug = '';

            if(isset($request->title) && $request->title) {
                $slug = Str::slug($request->title, '-');
            }

            if(isset($request->slug) && $request->slug) {
                $slug = $request->slug;
            }

            $blogs->slug = $slug;

            if (isset($input['image'])) {
                $image = $input['image'];
                $blogs->image = str_replace(url("/"), "", $image);
            }

            if (isset($input['thumb'])) {
                $thumb = $input['thumb'];
                $blogs->thumb = str_replace(url("/"), "", $thumb);
            }

            $blogs->save();

            DB::commit();
            $message = "Cập nhật tin tức thành công";

            return $this->respondSuccess($blogs, $message);

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

            $blogs = Blog::where('business_id', $business_id)->findOrFail($id);
            $blogs->delete();
            DB::commit();
            $message = "Xóa tin tức thành công";

            return $this->respondSuccess($blogs, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

}
