<?php

namespace App\Http\Controllers\Product;

use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Contact;
use App\CustomerGroup;
use App\Helpers\Activity;
use App\Http\Controllers\Controller;
use App\Media;
use App\Product;
use App\ProductContact;
use App\ProductImage;
use App\ProductVariation;
use App\PurchaseLine;
use App\SellingPriceGroup;
use App\Stock;
use App\StockProduct;
use App\TaxRate;
use App\Unit;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Variation;
use App\VariationGroupPrice;
use App\VariationLocationDetails;
use App\VariationTemplate;
use App\Warranty;
use Illuminate\Database\Schema\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class ProductController extends Controller
{
    /**
     * All Utils instance.
     *
     */
    protected $productUtil;
    protected $moduleUtil;

    private $barcode_types;

    /**
     * Constructor
     *
     * @param ProductUtils $product
     * @param BusinessUtil $businessUtil
     * @return void
     */
    public function __construct(ProductUtil $productUtil, ModuleUtil $moduleUtil, BusinessUtil $businessUtil)
    {
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;
        $this->businessUtil = $businessUtil;

        //barcode types
        $this->barcode_types = $this->productUtil->barcode_types();

        $this->dummyPaymentLine = ['method' => 'cash', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => ''];
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
            $user_id = Auth::guard('api')->user()->id;

            $query = Product::where('products.business_id', $business_id)
                ->with([
                    'product_images',
                    'stock_products',
                    "brand",
                    "contact",
                    "unit",
                    "category",
                    "warranty"
                ])
                ->where('products.type', '!=', 'modifier');

            $products = $query->select(
                'products.id',
                'products.name as product',
                'products.type',
                'products.sku',
                'products.barcode',
                'products.image',
                'products.not_for_selling',
                'products.is_inactive',
                'products.unit_id',
                'products.brand_id',
                'products.category_id',
                'products.contact_id',
                'products.warranty_id'
            )->groupBy('products.id');

            $stock_id = request()->get('stock_id', null);
            if (!empty($stock_id)) {
                $products->whereHas('stock_products', function ($products) use ($stock_id) {
                    $products->where('stock_products.stock_id', '=', $stock_id);
                });
            }

            $status = request()->get('status', null);
            if (!empty($status) && $status !== "all") {
                $products->whereHas('stock_products', function ($products) use ($status) {
                    $products->where('stock_products.status', '=', $status);
                });
            }

            $category_id = request()->get('category_id', null);
            if (!empty($category_id)) {
                $products->where('products.category_id', $category_id);
            }

            $brand_id = request()->get('brand_id', null);
            if (!empty($brand_id)) {
                $products->where('products.brand_id', $brand_id);
            }

            $unit_id = request()->get('unit_id', null);
            if (!empty($unit_id)) {
                $products->where('products.unit_id', $unit_id);
            }

            $tax_id = request()->get('tax_id', null);
            if (!empty($tax_id)) {
                $products->where('products.tax', $tax_id);
            }

            $contact_id = request()->get('contact_id', null);
            if (!empty($contact_id)) {
                $products->where('products.contact_id', $contact_id);
            }

            $active_state = request()->get('active_state', null);
            if ($active_state == 'active') {
                $products->Active();
            }
            if ($active_state == 'inactive') {
                $products->Inactive();
            }

            if (isset($request->barcode) && $request->barcode) {
                $products->where("products.barcode", "LIKE", "%$request->barcode%");
            }

            if (isset($request->id) && $request->id) {
                $products->where("products.id", "LIKE", "%$request->id%");
            }

            if (isset($request->product_name) && $request->product_name) {
                $products->where("products.name", "LIKE", "%$request->product_name%");
            }

            if (isset($request->sku) && $request->sku) {
                $products->where("products.sku", "LIKE", "%$request->sku%");
            }

            if (isset($request->keyword) && $request->keyword) {
                $products->where("products.name", "LIKE", "%$request->keyword%")
                    ->orWhere("products.sku", "LIKE", "%$request->keyword%");
            }

            $is_image = request()->get('is_image');
            if (!empty($is_image)) {
                $products->where('products.image', "");
            }

            $products->orderBy('products.created_at', "desc");
            $products->orderBy('products.name', 'asc');
            $data = $products->paginate($request->limit);

            return $this->respondSuccess($data);
        } catch (\Exception $e) {
//            dd($e);
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
            $statusStock = $request->status;

            $form_fields = [
                'contact_id',
                'name',
                'brand_id',
                'unit_id',
                'category_id',
                'tax',
                'type',
                'barcode_type',
                'sku',
                'barcode',
                'alert_quantity',
                'tax_type',
                'weight',
                'product_custom_field1',
                'product_custom_field2',
                'product_custom_field3',
                'product_custom_field4',
                'product_description',
                'sub_unit_ids',
                'thumbnail',
                'priceData',
                'images'
            ];

            $module_form_fields = $this->moduleUtil->getModuleFormField('product_form_fields');
            if (!empty($module_form_fields)) {
                $form_fields = array_merge($form_fields, $module_form_fields);
            }

            $product_details = $request->only($form_fields);
            $product_details['business_id'] = $business_id;
            $product_details['created_by'] = $user_id;

            $product_details['enable_stock'] = (!empty($request->input('enable_stock')) && $request->input('enable_stock') == 1) ? 1 : 0;
            $product_details['not_for_selling'] = (!empty($request->input('not_for_selling')) && $request->input('not_for_selling') == 1) ? 1 : 0;

            if (!empty($request->input('sub_category_id'))) {
                $product_details['sub_category_id'] = $request->input('sub_category_id');
            }

            if (empty($product_details['sku'])) {
                $product_details['sku'] = ' ';
            }

            //upload document
            $thumbnail = $request->input('thumbnail');
            $product_details['image'] = !empty($thumbnail) ? str_replace(url("/"), "", $thumbnail) : "";

            $product_details['warranty_id'] = !empty($request->input('warranty_id')) ? $request->input('warranty_id') : null;

            $product = Product::create($product_details);

            if (!$product) {
                $message = "Không thể lưu dữ liệu";
                return $this->respondWithError($message, [], 503);
            }

            if (empty(trim($request->input('sku')))) {
                $sku = $this->productUtil->generateProductSku($product->id, $business_id, "SP");
                $product->sku = $sku;
                $product->save();
            }

            //Add product locations
            $product_locations = $request->input('product_locations');
            if (!empty($product_locations)) {
                $product->product_locations()->sync($product_locations);
            }


            // image
            $requestImages = $request->input('images');
            if (!empty($requestImages) && count($requestImages) > 0) {
                $dataProductImage = [];
                foreach ($requestImages as $item) {
                    $urlImage = !empty($item["url"]) ? str_replace(url("/"), "", $item["url"]) : "";
                    $dataProductImage[] = [
                        'product_id' => $product->id,
                        'image' => str_replace("/thumb", "", $urlImage),
                        'thumb' => $urlImage,
                        'created_at' => now(),
                    ];
                }

                $productImage = ProductImage::insert($dataProductImage);

                if (!$productImage) {
                    $message = "Không thể lưu dữ liệu";
                    return $this->respondWithError($message, [], 503);
                }
            }

            $priceData = $request->input('priceData');
            if (!empty($priceData) && count($priceData) > 0) {
                $dataProductStock = [];
                $status = "pending";
                if (!empty($statusStock)) {
                    $status = $statusStock;
                }

                foreach ($priceData as $item) {
                    $dataProductStock[] = [
                        'product_id' => $product->id,
                        'stock_id' => $item["id"],
                        'quantity' => !empty($item["quantity"]) ? $item["quantity"] : 0,
                        'purchase_price' => !empty($item["purchase_price"]) ? $item["purchase_price"] : 0,
                        'unit_price' => !empty($item["sell_price"]) ? $item["sell_price"] : 0,
                        'status' => $status,
                        'created_at' => now(),
                    ];
                }

                $productImage = StockProduct::insert($dataProductStock);

                if (!$productImage) {
                    $message = "Không thể lưu dữ liệu";
                    return $this->respondWithError($message, [], 503);
                }
            }

            $variations = $request->variations;
            if (!empty($variations) && count($variations) > 0) {
                $dataVariations = [];

                foreach ($variations as $item) {
                    if (isset($item["is_new"]) && $item["is_new"] === true) {
                        $dataProductVariations = [
                            'name' => !empty($item["name"]) ? $item["name"] : 0,
                            'value' => !empty($item["value"]) ? $item["value"] : 0,
                            'product_id' => $product->id,
                            'created_at' => now(),
                        ];

                        array_push($dataVariations, $dataProductVariations);
                    }
                }

                if (count($dataVariations) > 0) {
                    $variationResult = ProductVariation::insert($dataVariations);
                    if (!$variationResult) {
                        $message = "Không thể lưu dữ liệu";
                        return $this->respondWithError($message, [], 503);
                    }
                }
            }

            $message = "Thêm sản phẩm '" . $request->input("name") . "' thhành công";
            $dataLog = [
                'created_by' => $user_id,
                'business_id' => $business_id,
                'log_name' => "Thêm sản phẩm mới",
                'subject_id' => $product->id
            ];

            Activity::history($message, "product", $dataLog);

            DB::commit();

            return $this->respondSuccess($product, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Product $product
     * @return \Illuminate\Http\Response
     */
    public function detail($id)
    {
        try {
            $business_id = Auth::guard('api')->user()->business_id;

            $product = Product::where('business_id', $business_id)
                ->where('id', $id)
                ->with([
                    'product_images',
                    'product_variations',
                    'stock_products',
                    'stock_products.stock',
                    "brand",
                    "contact",
                    "unit",
                    "category",
                    "warranty"
                ])
                ->first();

            return $this->respondSuccess($product);
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
            $statusStock = $request->status;
            $notForSell = $request->not_for_selling;

            $product_details = $request->only([
                'contact_id',
                'name',
                'brand_id',
                'unit_id',
                'category_id',
                'tax',
                'type',
                'barcode_type',
                'barcode',
                'sku',
                'alert_quantity',
                'tax_type',
                'weight',
                'product_custom_field1',
                'product_custom_field2',
                'product_custom_field3',
                'product_custom_field4',
                'product_description',
                'sub_unit_ids',
                'thumbnail',
                'images',
                'remove_images',
                'not_for_selling',
            ]);

            $product = Product::where('business_id', $business_id)
                ->where('id', $id)
                ->first();

            if (isset($product_details['name'])) {
                $product->name = $product_details['name'];
            }

            if (isset($product_details['brand_id'])) {
                $product->brand_id = $product_details['brand_id'];
            }

            if (isset($product_details['unit_id'])) {
                $product->unit_id = $product_details['unit_id'];
            }

            if (isset($product_details['contact_id'])) {
                $product->contact_id = $product_details['contact_id'];
            }

            if (isset($product_details['category_id'])) {
                $product->category_id = $product_details['category_id'];
            }

            if (isset($product_details['tax'])) {
                $product->tax = $product_details['tax'];
            }

            if (isset($product_details['barcode_type'])) {
                $product->barcode_type = $product_details['barcode_type'];
            }

            if (isset($product_details['barcode'])) {
                $product->barcode = $product_details['barcode'];
            }

            if (isset($product_details['sku'])) {
                $product->sku = $product_details['sku'];
            }

            if (isset($product_details['alert_quantity'])) {
                $product->alert_quantity = $product_details['alert_quantity'];
            }

            if (isset($product_details['tax_type'])) {
                $product->tax_type = $product_details['tax_type'];
            }

            if (isset($product_details['weight'])) {
                $product->weight = $product_details['weight'];
            }

            if (isset($product_details['product_custom_field1'])) {
                $product->product_custom_field1 = $product_details['product_custom_field1'];
            }

            if (isset($product_details['product_custom_field2'])) {
                $product->product_custom_field2 = $product_details['product_custom_field2'];
            }

            if (isset($product_details['product_custom_field3'])) {
                $product->product_custom_field3 = $product_details['product_custom_field3'];
            }

            if (isset($product_details['product_custom_field4'])) {
                $product->product_custom_field4 = $product_details['product_custom_field4'];
            }

            if (isset($product_details['product_description'])) {
                $product->product_description = $product_details['product_description'];
            }

            if (isset($product_details['warranty_id'])) {
                $product->warranty_id = $product_details['warranty_id'];
            }

            if (isset($product_details['sub_unit_ids'])) {
                $product->sub_unit_ids = $product_details['sub_unit_ids'];
            }

            $thumbnail = $request->input('thumbnail');
            if (isset($thumbnail)) {
                $product->image = !empty($thumbnail) ? str_replace(url("/"), "", $thumbnail) : "";
            }

            if (!empty($request->input('enable_stock')) && $request->input('enable_stock') == 1) {
                $product->enable_stock = 1;
            } else {
                $product->enable_stock = 0;
            }

            if (isset($request->not_for_selling)) {
                $product->not_for_selling = $notForSell;
            }

            if (!empty($request->input('sub_category_id'))) {
                $product->sub_category_id = $request->input('sub_category_id');
            } else {
                $product->sub_category_id = null;
            }

            // remove_images
            $removeImages = $request->input('remove_images');
            if (!empty($removeImages) && count($removeImages) > 0) {
                foreach ($removeImages as $item) {
                    ProductImage::findOrFail($item)->delete();
                }
            }

            $priceData = $request->priceData;
            if (!empty($priceData) && count($priceData) > 0) {
                $status = "pending";
                if (!empty($statusStock)) {
                    $status = $statusStock;
                }

                foreach ($priceData as $item) {
                    $dataProductStock = [
                        'quantity' => !empty($item["quantity"]) ? $item["quantity"] : 0,
                        'purchase_price' => !empty($item["purchase_price"]) ? $item["purchase_price"] : 0,
                        'unit_price' => !empty($item["sell_price"]) ? $item["sell_price"] : 0,
                        'status' => $status,
                        'updated_at' => now(),
                    ];

                    $productStock = StockProduct::where("product_id", $product->id)
                        ->where("stock_id", $item["id"])
                        ->update($dataProductStock);

                    if (!$productStock) {
                        $message = "Không thể lưu dữ liệu";
                        return $this->respondWithError($message, [], 503);
                    }
                }
            }

            $variations = $request->variations;
            if (!empty($variations) && count($variations) > 0) {
                $dataVariations = [];

                foreach ($variations as $item) {
                    if (isset($item["is_new"]) && $item["is_new"] === true) {
                        $dataProductVariations = [
                            'name' => !empty($item["name"]) ? $item["name"] : 0,
                            'value' => !empty($item["value"]) ? $item["value"] : 0,
                            'product_id' => $product->id,
                            'created_at' => now(),
                        ];

                        array_push($dataVariations, $dataProductVariations);
                    }


                    if (isset($item["is_update"]) && $item["is_update"] === true) {
                        $dataProductVariations = [
                            'name' => !empty($item["name"]) ? $item["name"] : 0,
                            'value' => !empty($item["value"]) ? $item["value"] : 0,
                            'updated_at' => now(),
                        ];

                        $variationResult = ProductVariation::where("product_id", $product->id)
                            ->where("id", $item["id"])
                            ->update($dataProductVariations);

                        if (!$variationResult) {
                            $message = "Không thể lưu dữ liệu";
                            return $this->respondWithError($message, [], 503);
                        }
                    }

                    if (isset($item["is_remove"]) && $item["is_remove"] === true) {
                        $variationResult = ProductVariation::where("product_id", $product->id)
                            ->where("id", $item["id"])
                            ->delete();

                        if (!$variationResult) {
                            $message = "Không thể lưu dữ liệu";
                            return $this->respondWithError($message, [], 503);
                        }
                    }
                }


                if (count($dataVariations) > 0) {
                    $variationResult = ProductVariation::insert($dataVariations);
                    if (!$variationResult) {
                        $message = "Không thể lưu dữ liệu";
                        return $this->respondWithError($message, [], 503);
                    }
                }
            }

            // remove_images
            $requestImages = $request->input('images');
            if (!empty($requestImages) && count($requestImages) > 0) {
                $dataProductImage = [];
                foreach ($requestImages as $item) {
                    $urlImage = !empty($item["url"]) ? str_replace(url("/"), "", $item["url"]) : "";
                    $dataProductImage[] = [
                        'product_id' => $product->id,
                        'image' => str_replace("/thumb", "", $urlImage),
                        'thumb' => $urlImage,
                        'created_at' => now(),
                    ];
                }

                $productImage = ProductImage::insert($dataProductImage);

                if (!$productImage) {
                    $message = "Không thể lưu dữ liệu";
                    return $this->respondWithError($message, [], 503);
                }
            }

            $stock_id = $request->stock_id;

            if (isset($request->update_status) && $stock_id) {
                $productStock = StockProduct::where("product_id", $product->id)
                    ->where("stock_id", $stock_id)
                    ->update([
                        "status" => $statusStock
                    ]);

                if (!$productStock) {
                    $message = "Không thể lưu dữ liệu";
                    return $this->respondWithError($message, [], 503);
                }
            }
            $product->save();
            $product->touch();

            DB::commit();
            $message = "Cập nhật sản phẩm thành công";

            return $this->respondSuccess($product, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Product $product
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        DB::beginTransaction();

        try {
            $business_id = Auth::guard('api')->user()->business_id;

            $can_be_deleted = true;
            $error_msg = '';

            //Check if any purchase or transfer exists
            $count = PurchaseLine::join(
                'transactions as T',
                'purchase_lines.transaction_id',
                '=',
                'T.id'
            )
                ->whereIn('T.type', ['purchase'])
                ->where('T.business_id', $business_id)
                ->where('purchase_lines.product_id', $id)
                ->count();
            if ($count > 0) {
                $can_be_deleted = false;
                $error_msg = __('lang_v1.purchase_already_exist');
            } else {
                //Check if any opening stock sold
                $count = PurchaseLine::join(
                    'transactions as T',
                    'purchase_lines.transaction_id',
                    '=',
                    'T.id'
                )
                    ->where('T.type', 'opening_stock')
                    ->where('T.business_id', $business_id)
                    ->where('purchase_lines.product_id', $id)
                    ->where('purchase_lines.quantity_sold', '>', 0)
                    ->count();
                if ($count > 0) {
                    $can_be_deleted = false;
                    $error_msg = __('lang_v1.opening_stock_sold');
                } else {
                    //Check if any stock is adjusted
                    $count = PurchaseLine::join(
                        'transactions as T',
                        'purchase_lines.transaction_id',
                        '=',
                        'T.id'
                    )
                        ->where('T.business_id', $business_id)
                        ->where('purchase_lines.product_id', $id)
                        ->where('purchase_lines.quantity_adjusted', '>', 0)
                        ->count();
                    if ($count > 0) {
                        $can_be_deleted = false;
                        $error_msg = __('lang_v1.stock_adjusted');
                    }
                }
            }

            $product = Product::where('id', $id)
                ->where('business_id', $business_id)
                ->with('variations')
                ->first();

            if ($can_be_deleted) {
                if (!empty($product)) {
                    VariationLocationDetails::where('product_id', $id)
                        ->delete();
                    $product->delete();

                    DB::commit();
                    $message = "Xóa sản phẩm thành công";

                    return $this->respondSuccess($product, $message);
                }
            }
            return $this->respondWithError($error_msg, [], 500);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }

    }

    /**
     * Get subcategories list for a category.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getSubCategories(Request $request)
    {
        if (!empty($request->input('cat_id'))) {
            $category_id = $request->input('cat_id');
            $business_id = $request->session()->get('user.business_id');
            $sub_categories = Category::where('business_id', $business_id)
                ->where('parent_id', $category_id)
                ->select(['name', 'id'])
                ->get();
            $html = '<option value="">None</option>';
            if (!empty($sub_categories)) {
                foreach ($sub_categories as $sub_category) {
                    $html .= '<option value="' . $sub_category->id . '">' . $sub_category->name . '</option>';
                }
            }
            echo $html;
            exit;
        }
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getProductVariationFormPart(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $action = $request->input('action');
        if ($request->input('action') == "add") {
            if ($request->input('type') == 'single') {
                return view('product.partials.single_product_form_part')
                    ->with(['profit_percent' => $profit_percent]);
            } elseif ($request->input('type') == 'variable') {
                $variation_templates = VariationTemplate::where('business_id', $business_id)->pluck('name', 'id')->toArray();
                $variation_templates = ["" => __('messages.please_select')] + $variation_templates;

                return view('product.partials.variable_product_form_part')
                    ->with(compact('variation_templates', 'profit_percent', 'action'));
            } elseif ($request->input('type') == 'combo') {
                return view('product.partials.combo_product_form_part')
                    ->with(compact('profit_percent', 'action'));
            }
        } elseif ($request->input('action') == "edit" || $request->input('action') == "duplicate") {
            $product_id = $request->input('product_id');
            $action = $request->input('action');
            if ($request->input('type') == 'single') {
                $product_deatails = ProductVariation::where('product_id', $product_id)
                    ->with(['variations', 'variations.media'])
                    ->first();

                return view('product.partials.edit_single_product_form_part')
                    ->with(compact('product_deatails', 'action'));
            } elseif ($request->input('type') == 'variable') {
                $product_variations = ProductVariation::where('product_id', $product_id)
                    ->with(['variations', 'variations.media'])
                    ->get();
                return view('product.partials.variable_product_form_part')
                    ->with(compact('product_variations', 'profit_percent', 'action'));
            } elseif ($request->input('type') == 'combo') {
                $product_deatails = ProductVariation::where('product_id', $product_id)
                    ->with(['variations', 'variations.media'])
                    ->first();
                $combo_variations = $this->__getComboProductDetails($product_deatails['variations'][0]->combo_variations, $business_id);

                $variation_id = $product_deatails['variations'][0]->id;
                return view('product.partials.combo_product_form_part')
                    ->with(compact('combo_variations', 'profit_percent', 'action', 'variation_id'));
            }
        }
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getVariationValueRow(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $variation_index = $request->input('variation_row_index');
        $value_index = $request->input('value_index') + 1;

        $row_type = $request->input('row_type', 'add');

        return view('product.partials.variation_value_row')
            ->with(compact('profit_percent', 'variation_index', 'value_index', 'row_type'));
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getProductVariationRow(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $variation_templates = VariationTemplate::where('business_id', $business_id)
            ->pluck('name', 'id')->toArray();
        $variation_templates = ["" => __('messages.please_select')] + $variation_templates;

        $row_index = $request->input('row_index', 0);
        $action = $request->input('action');

        return view('product.partials.product_variation_row')
            ->with(compact('variation_templates', 'row_index', 'action', 'profit_percent'));
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getVariationTemplate(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $template = VariationTemplate::where('id', $request->input('template_id'))
            ->with(['values'])
            ->first();
        $row_index = $request->input('row_index');

        return view('product.partials.product_variation_template')
            ->with(compact('template', 'row_index', 'profit_percent'));
    }

    /**
     * Return the view for combo product row
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getComboProductEntryRow(Request $request)
    {
        if (request()->ajax()) {
            $product_id = $request->input('product_id');
            $variation_id = $request->input('variation_id');
            $business_id = $request->session()->get('user.business_id');

            if (!empty($product_id)) {
                $product = Product::where('id', $product_id)
                    ->with(['unit'])
                    ->first();

                $query = Variation::where('product_id', $product_id)
                    ->with(['product_variation']);

                if ($variation_id !== '0') {
                    $query->where('id', $variation_id);
                }
                $variations = $query->get();

                $sub_units = $this->productUtil->getSubUnits($business_id, $product['unit']->id);

                return view('product.partials.combo_product_entry_row')
                    ->with(compact('product', 'variations', 'sub_units'));
            }
        }
    }

}
