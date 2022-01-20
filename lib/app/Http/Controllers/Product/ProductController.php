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
use App\Transaction;
use App\TransactionSellLine;
use App\Unit;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Variation;
use App\VariationGroupPrice;
use App\VariationLocationDetails;
use App\VariationTemplate;
use App\Warranty;
use Balping\JsonRaw\Raw;
use Carbon\Carbon;
use Illuminate\Database\Schema\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Excel;

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
            $contact_id = $request->contact_id;
            $showAll = $request->showAll;
            $type = $request->type;

            if ($showAll == "false" && !empty($contact_id)) {
                if ($type === "purchase") {
                    $data = $this->getListAllProduct($request, $business_id, $contact_id);
                    return $this->respondSuccess($data);
                } else {
                    $data = $this->getListProductByContact($request, $business_id, $contact_id);
                    return $this->respondSuccess($data);
                }
            }

            $data = $this->getListAllProduct($request, $business_id);
            return $this->respondSuccess($data);
        } catch (\Exception $e) {
//            dd($e);
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }

    private function getListProductByContact($request, $business_id, $contact_id)
    {
        $latestTransactionLines = TransactionSellLine::leftJoin(
            'transactions',
            'transactions.id',
            '=',
            'transaction_sell_lines.transaction_id'
        )
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.contact_id', $contact_id)
            ->select(
                'transaction_sell_lines.product_id',
                DB::raw('MAX(transaction_sell_lines.id) as id'))
            ->groupBy('transaction_sell_lines.product_id');
        $stock_id = request()->get('stock_id', null);

        $products = Product::leftJoinSub($latestTransactionLines, 'tsl2', function ($join) {
            $join->on('products.id', '=', 'tsl2.product_id');
        })
            ->leftJoin(
                'transaction_sell_lines as tsl',
                'tsl.id',
                '=',
                'tsl2.id'
            )
            ->leftJoin("stock_products", function ($join) use($stock_id) {
                $join->on('stock_products.product_id', '=', 'products.id');
                $join->on('stock_products.stock_id', '=', DB::raw($stock_id));
            })
            ->leftJoin(
                'units',
                'units.id',
                '=',
                'products.unit_id'
            )
            ->leftJoin(
                'brands',
                'brands.id',
                '=',
                'products.brand_id'
            )
            ->leftJoin(
                'categories',
                'categories.id',
                '=',
                'products.category_id'
            )
            ->leftJoin(
                'contacts',
                'contacts.id',
                '=',
                'products.contact_id'
            )
            ->where('tsl.id', '<>', null)
            ->with([
                'product_images',
                'stock_products',
                "brand",
                "contact",
                "unit",
                "category",
                "warranty"
            ])
            ->select(
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
                'products.warranty_id',
                'tsl.unit_price',
                'tsl.purchase_price',
                'tsl.quantity'
            )
            ->groupBy("products.id");

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

        $category = request()->get('category', null);
        if (!empty($category)) {
            $products->where("categories.name", "LIKE", "%$category%");
        }

        $brand = request()->get('brand', null);
        if (!empty($brand)) {
            $products->where("brands.name", "LIKE", "%$brand%");
        }

        $unit = request()->get('unit', null);
        if (!empty($unit)) {
            $products->where("units.actual_name", "LIKE", "%$unit%");
        }

        $tax_id = request()->get('tax_id', null);
        if (!empty($tax_id)) {
            $products->where('products.tax', $tax_id);
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
                ->orWhere("products.sku", "LIKE", "%$request->keyword%")
                ->orWhere("products.barcode", "LIKE", "%$request->keyword%");
        }

        $is_image = request()->get('is_image');
        if (!empty($is_image)) {
            $products->where('products.image', "");
        }

        $sort_field = "products.created_at";
        $sort_des = "desc";

        if(isset($request->order_field) && $request->order_field) {
            $sort_field = $request->order_field;
        }

        if(isset($request->order_by) && $request->order_by) {
            $sort_des = $request->order_by;
        }

        $products->orderBy($sort_field, $sort_des);

        $data = $products->paginate($request->limit);
        return $data;
    }


    private function getListAllProduct($request, $business_id, $contact_id = null)
    {
        $stock_id = request()->get('stock_id', null);
        $query = Product::where('products.business_id', $business_id)
            ->leftJoin("stock_products", function ($join) use($stock_id) {
                $join->on('stock_products.product_id', '=', 'products.id');
                $join->on('stock_products.stock_id', '=', DB::raw($stock_id));
            })
            ->leftJoin(
                'units',
                'units.id',
                '=',
                'products.unit_id'
            )
            ->leftJoin(
                'categories',
                'categories.id',
                '=',
                'products.category_id'
            )
            ->leftJoin(
                'brands',
                'brands.id',
                '=',
                'products.brand_id'
            )
            ->leftJoin(
                'contacts',
                'contacts.id',
                '=',
                'products.contact_id'
            )
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

        if (!empty($contact_id)) {
            $query->where("products.contact_id", $contact_id);
        }

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
            'products.warranty_id',
            'products.created_at'
        )->groupBy('products.id');

        $status = request()->get('status', null);
        if (!empty($status) && $status !== "all") {
            $products->where('stock_products.status', '=', $status);
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

        $category = request()->get('category', null);
        if (!empty($category)) {
            $products->where("categories.name", "LIKE", "%$category%");
        }

        $brand = request()->get('brand', null);
        if (!empty($brand)) {
            $products->where("brands.name", "LIKE", "%$brand%");
        }

        $unit = request()->get('unit', null);
        if (!empty($unit)) {
            $products->where("units.actual_name", "LIKE", "%$unit%");
        }

        $tax_id = request()->get('tax_id', null);
        if (!empty($tax_id)) {
            $products->where('products.tax', $tax_id);
        }

        $active_state = request()->get('active_state', null);
        if ($active_state == 'active') {
            $products->Active();
        }
        if ($active_state == 'inactive') {
            $products->Inactive();
        }

        $contact_id = request()->get('contact_id', null);
        if (!empty($contact_id) && (empty($request->showAll) || $request->showAll != "true")) {
            $products->where('products.contact_id', $contact_id);
        }

        if (isset($request->contact_name) && $request->contact_name) {
            $products->where("contacts.first_name", "LIKE", "%$request->contact_name%")
                ->orWhere("contacts.supplier_business_name", "LIKE", "%$request->contact_name%");
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
                ->orWhere("products.sku", "LIKE", "%$request->keyword%")
                ->orWhere("products.barcode", "LIKE", "%$request->keyword%");
        }

        if (!empty(request()->start_date)) {
            $start = Carbon::createFromFormat('d/m/Y', request()->start_date);
            $products->where('products.created_at', '>=', $start);
        }

        if (!empty(request()->end_date)) {
            $end = Carbon::createFromFormat('d/m/Y', request()->end_date);
            $products->where('products.created_at', '<=', $end);
        }

        $is_image = request()->get('is_image');
        if (!empty($is_image)) {
            $products->where('products.image', "");
        }

        $sort_field = "products.created_at";
        $sort_des = "desc";

        if(isset($request->order_field) && $request->order_field) {
            $sort_field = $request->order_field;
        }

        if(isset($request->order_by) && $request->order_by) {
            $sort_des = $request->order_by;
        }

        $products->orderBy($sort_field, $sort_des);
        $data = $products->paginate($request->limit);
        return $data;
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
                'listVariation',
                'images',
                'enable_sr_no'
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

            if (isset($product_details['enable_sr_no'])) {
                $product_details['enable_sr_no'] = $product_details['enable_sr_no'] == true ? 1 : 0;
            }

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
                        'sale_price' => !empty($item["sale_price"]) ? $item["sale_price"] : 0,
                        'sale_price_max' => !empty($item["sale_price_max"]) ? $item["sale_price_max"] : 0,
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
                            'name' => !empty($item["name"]) ? $item["name"] : "",
                            'value' => !empty($item["value"]) ? $item["value"] : "",
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

            $listVariation = $request->listVariation;

            if (!empty($listVariation) && count($listVariation) > 0) {
                $dataListVariations = [];

                foreach ($listVariation as $item) {
                    if (isset($item["is_new"]) && $item["is_new"] === true) {
                        $urlImage = !empty($item["images"]) ? str_replace(url("/"), "", $item["images"]) : "";

                        $dataVariations = [
                            'name' => !empty($item["name"]) ? $item["name"] : "",
                            'description' => !empty($item["description"]) ? $item["description"] : "",
                            'allowSerial' => !empty($item["allowSerial"]) ? $item["allowSerial"] : 0,
                            'barcode' => !empty($item["barcode"]) ? $item["barcode"] : "",
                            'sub_sku' => !empty($item["sku"]) ? $item["sku"] : "",
                            'image' => !empty($item["images"]) ? $urlImage : "",
                            'status' => !empty($item["status"]) ? $item["status"] : "active",
                            'default_sell_price' => !empty($item["unit_price"]) ? $item["unit_price"] : 0,
                            'sell_price_inc_tax' => !empty($item["sale_price"]) ? $item["sale_price"] : 0,
                            'product_id' => $product->id,
                            'created_at' => now(),
                        ];

                        array_push($dataListVariations, $dataVariations);
                    }
                }


                if (count($dataListVariations) > 0) {
                    $variationResult = Variation::insert($dataListVariations);
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
                    'variations:id,product_id,name,sub_sku,description,image,barcode,allowSerial,status,default_sell_price,sell_price_inc_tax',
                    'product_variations',
                    'stock_products',
                    'stock_products.stock',
                    "brand",
                    "contact",
                    "unit",
                    "category",
                    "warranty",
                    "product_serial:id,product_id,serial,is_sell"
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
                'enable_sr_no',
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

            if (isset($product_details['enable_sr_no'])) {
                $product->enable_sr_no = $product_details['enable_sr_no'] == true ? 1 : 0;
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
                        'sale_price' => !empty($item["sale_price"]) ? $item["sale_price"] : 0,
                        'sale_price_max' => !empty($item["sale_price_max"]) ? $item["sale_price_max"] : 0,
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

            $listVariation = $request->listVariation;

            if (!empty($listVariation) && count($listVariation) > 0) {
                $dataListVariations = [];

                foreach ($listVariation as $item) {
                    $urlImage = !empty($item["images"]) ? str_replace(url("/"), "", $item["images"]) : "";

                    if (isset($item["is_new"]) && $item["is_new"] === true) {
                        $dataVariations = [
                            'name' => !empty($item["name"]) ? $item["name"] : "",
                            'description' => !empty($item["description"]) ? $item["description"] : "",
                            'allowSerial' => !empty($item["allowSerial"]) ? $item["allowSerial"] : 0,
                            'barcode' => !empty($item["barcode"]) ? $item["barcode"] : "",
                            'sub_sku' => !empty($item["sku"]) ? $item["sku"] : "",
                            'image' => !empty($item["images"]) ? $urlImage : "",
                            'status' => !empty($item["status"]) ? $item["status"] : "active",
                            'default_sell_price' => !empty($item["unit_price"]) ? $item["unit_price"] : 0,
                            'sell_price_inc_tax' => !empty($item["sale_price"]) ? $item["sale_price"] : 0,
                            'product_id' => $product->id,
                            'created_at' => now(),
                        ];

                        array_push($dataListVariations, $dataVariations);
                    }

                    if (isset($item["is_update"]) && $item["is_update"] === true) {
                        $dataVariations = [
                            'name' => !empty($item["name"]) ? $item["name"] : "",
                            'description' => !empty($item["description"]) ? $item["description"] : "",
                            'allowSerial' => !empty($item["allowSerial"]) ? $item["allowSerial"] : 0,
                            'barcode' => !empty($item["barcode"]) ? $item["barcode"] : "",
                            'sub_sku' => !empty($item["sku"]) ? $item["sku"] : "",
                            'image' => !empty($item["images"]) ? $urlImage : "",
                            'status' => !empty($item["status"]) ? $item["status"] : "active",
                            'default_sell_price' => !empty($item["unit_price"]) ? $item["unit_price"] : 0,
                            'sell_price_inc_tax' => !empty($item["sale_price"]) ? $item["sale_price"] : 0,
                            'updated_at' => now(),
                        ];


                        return $this->respondWithError($urlImage, [], 500);

                        if (isset($item["is_remove"]) && $item["is_remove"] === true) {
                            $variationResult = Variation::where("product_id", $product->id)
                                ->where("id", $item["id"])
                                ->delete();

                            if (!$variationResult) {
                                $message = "Không thể lưu dữ liệu";
                                return $this->respondWithError($message, [], 503);
                            }
                        } else {
                            $variationResult = Variation::where("product_id", $product->id)
                                ->where("id", $item["id"])
                                ->update($dataVariations);

                            if (!$variationResult) {
                                $message = "Không thể lưu dữ liệu";
                                return $this->respondWithError($message, [], 503);
                            }
                        }
                    }
                }


                if (count($dataListVariations) > 0) {
                    $variationResult = Variation::insert($dataListVariations);
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

    public function updateStatus(Request $request)
    {
        try {
            $ids = $request->ids;

            if (!$ids || count($ids) === 0) {
                $res = [
                    'status' => 'danger',
                    'msg' => "Không tìm sản phẩm"
                ];

                return response()->json($res, 404);
            }

            $business_id = Auth::guard('api')->user()->business_id;
            $user_id = Auth::guard('api')->user()->id;
            $stock_id = $request->stock_id;

            $status = $request->status;

            if (empty($status)) {
                $res = [
                    'status' => 'danger',
                    'msg' => "Không có dữ liệu được cập nhật"
                ];

                return response()->json($res, 500);
            }

            $dataUpdate = [
                'status' => $status
            ];

            $transaction = StockProduct::where("stock_id", $stock_id)
                ->whereIn('product_id', $ids)
                ->update($dataUpdate);

            DB::commit();

            return $this->respondSuccess($transaction);
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

    public function importData(Request $request)
    {
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
                $is_replace = false;
                $columnReplace = [];
                $error_msg = '';

                $total_rows = count($imported_data);

                if(isset($request->column_select) && $request->column_select) {
                    $columnReplace = explode(',', $request->column_select);
                    if (count($columnReplace) > 0) {
                        $is_replace = true;
                    }
                }

                foreach ($imported_data as $key => $value) {
                    //Check if any column is missing
                    if (count($value) < 8) {
                        $is_valid = false;
                        $error_msg = "Thiếu cột trong quá trình tải lên dữ liệu. vui lòng tuần thủ dữ template";
                        break;
                    }

                    $row_no = $key + 1;
                    $product_array = [];
                    $product_array['business_id'] = $business_id;
                    $product_array['created_by'] = $user_id;
                    $product_array['type'] = "single";
                    $product_array['barcode_type'] = 'C128';

                    //Add product name
                    $product_name = trim($value[1]);
                    if (!empty($product_name)) {
                        $product_array['name'] = $product_name;
                    } else {
                        $is_valid = false;
                        $error_msg = "Tên sản phẩm không được tìm thấy ở hàng thứ. $row_no";
                        break;
                    }

                    //Add unit
                    $unit_name = trim($value[3]);
                    if (!empty($unit_name)) {
                        $unit = Unit::where('business_id', $business_id)
                            ->where(function ($query) use ($unit_name) {
                                $query->where('short_name', $unit_name)
                                    ->orWhere('actual_name', $unit_name);
                            })->first();
                        if (!empty($unit)) {
                            $product_array['unit_id'] = $unit->id;
                        } else {
                            $is_valid = false;
                            $error_msg = "Đơn vị không được tìm thấy ở hàng thứ . $row_no";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "Đơn vị không được tìm thấy ở hàng thứ . $row_no";
                        break;
                    }

                    //Add category
                    $category_name = trim($value[4]);
                    if (!empty($category_name)) {
                        $category = Category::where('business_id', $business_id)
                            ->where(function ($query) use ($category_name) {
                                $query->where('name', $category_name);
                            })->first();
                        if (!empty($category)) {
                            $product_array['category_id'] = $category->id;
                        } else {
                            $is_valid = false;
                            $error_msg = "Danh mục không được tìm thấy ở hàng thứ . $row_no";
                            break;
                        }
                    }

                    //Add brand
                    $brand_name = trim($value[5]);
                    if (!empty($brand_name)) {
                        $brand = Brands::where('business_id', $business_id)
                            ->where(function ($query) use ($brand_name) {
                                $query->where('name', $brand_name);
                            })->first();
                        if (!empty($brand)) {
                            $product_array['brand_id'] = $brand->id;
                        } else {
                            $is_valid = false;
                            $error_msg = "Thương hiệu không được tìm thấy ở hàng thứ . $row_no";
                            break;
                        }
                    }

                    // supplier
                    $category_name = trim($value[2]);
                    if (!empty($category_name)) {
                        $supplier = Contact::where('type', "supplier")
                            ->where(function ($query) use ($category_name) {
                                $query->where('supplier_business_name', $category_name)
                                    ->orWhere('first_name', $category_name);
                            })->first();
                        if (!empty($supplier)) {
                            $product_array['contact_id'] = $supplier->id;
                        } else {
                            $is_valid = false;
                            $error_msg = "Nhà cung cấp không được tìm thấy ở hàng thứ. $row_no";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "Nhà cung cấp không được tìm thấy ở hàng thứ. $row_no";
                        break;
                    }

                    //Add SKU
                    $sku = trim($value[0]);
                    if (!empty($sku)) {
                        $product_array['sku'] = $sku;
                        //Check if product with same SKU already exist
                        $is_exist = Product::where('sku', $product_array['sku'])
                            ->where('business_id', $business_id)
                            ->exists();
                        if ($is_exist && $is_replace === false) {
                            $is_valid = false;
                            $error_msg = "$sku SKU đã tồn tại ở dòng thứ. $row_no";
                            break;
                        }
                    } else {
                        $product_array['sku'] = ' ';
                    }

                    // sell price
                    $sell_price = trim($value[8]);
                    $purchase_price = trim($value[7]);
                    $quantity = trim($value[9]);
                    $sale_price = trim($value[10]);
                    $sale_price_max = trim($value[11]);

                    if (!$sell_price) $sell_price = 0;
                    if (!$purchase_price) $purchase_price = 0;
                    if (!$quantity) $quantity = 0;
                    if (!$sale_price) $sale_price = 0;
                    if (!$sale_price_max) $sale_price_max = 0;

                    $formated_data[] = $product_array;
                    $prices_data[] = [
                        "sale_price" => $sale_price,
                        "sale_price_max" => $sale_price_max,
                        "sell_price" => $sell_price,
                        "purchase_price" => $purchase_price,
                        "quantity" => $quantity
                    ];
                }

                if (!$is_valid) {
                    throw new \Exception($error_msg);
                }

                if (!empty($formated_data)) {
                    foreach ($formated_data as $index => $product_data) {
                        $data_stock = [
                            "stock_id" => 6,
                            "purchase_price" => $prices_data[$index]['purchase_price'],
                            "unit_price" => $prices_data[$index]['sell_price'],
                            "sale_price" => $prices_data[$index]['sale_price'],
                            "sale_price_max" => $prices_data[$index]['sale_price_max'],
                            "quantity" => $prices_data[$index]['quantity'],
                            "status" => "approve",
                        ];

                        //Create new product
                        $prodItem = Product::where('sku', $product_data['sku'])
                            ->where('business_id', $business_id)
                            ->first();

                        if($prodItem && $is_replace === true) {
                            $dataProductUpdate = [];
                            $dataPriceUpdate = [];

                            foreach ($columnReplace as $value) {
                                if (isset($product_data[$value])) {
                                    $dataProductUpdate[$value] = $product_data[$value];
                                }

                                if (isset($data_stock[$value])) {
                                    $dataPriceUpdate[$value] = $data_stock[$value];
                                }

                                Product::where('sku', $product_data['sku'])
                                    ->update($dataProductUpdate);

                                StockProduct::where('stock_id', 6)
                                    ->where("product_id", $prodItem->id)
                                    ->update($dataPriceUpdate);
                            }
                        } else {
                            $product = Product::create($product_data);

                            $data_stock["product_id"] = $product->id;

                            StockProduct::create($data_stock);

                            if ($product->sku == ' ') {
                                $sku = $this->productUtil->generateProductSku($product->id);
                                $product->sku = $sku;
                                $product->save();
                            }
                        }
                    }
                }
            }

            $message = "Nhập liệu sản phẩm thành công";

            return $this->respondSuccess([], $message);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            return $this->respondWithError($message, [], 500);
        }
    }


}
