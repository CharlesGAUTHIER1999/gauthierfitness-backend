<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

#[Group(name: 'Catalogue', weight: 2)]
class ProductController extends Controller
{
    /**
     * Paginated list of public products
     * @unauthenticated
     * @queryParam per_page integer Products per page (1-60, default 12)
     * @queryParam gender string Gender category slug (e.g. "homme", "femme")
     * @queryParam category string Category slug (e.g. "t-shirts")
     * @queryParam tag string Special sort: "new" or "bestseller"
     * @queryParam search string Free-text search on the product name
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $default_page = 12;
        $max_page = 60;
        $per_page = (int)$request->query('per_page', $default_page);
        $per_page = max(1, min($per_page, $max_page));

        $query = Product::active()
            ->select('products.*')
            ->whereIn('products.id', function ($sub) {
                $sub->selectRaw('MIN(id)')
                    ->from('products')
                    ->where('is_active', true)
                    ->groupBy(DB::raw('COALESCE(group_id, id)'));
            })
            ->with([
                'mainImage:id,product_id,url,is_main,position',
                'hoverImage:id,product_id,url,is_main,position',
                'categories:id,name,slug,parent_id',
                'categories.parent:id,name,slug,parent_id',
                'group:id,name,slug,type',
                'options:id,product_id,type,code,label,position',
            ]);

        if ($request->filled('gender')) {
            $gender = (string)$request->query('gender');
            $query->whereHas('categories', function ($q) use ($gender) {
                $q->where('slug', $gender)->orWhere('slug', 'like', $gender . '-%');
            });
        }

        if ($request->filled('search')) {
            $search = (string)$request->query('search');
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where('name', 'like', '%' . $escaped . '%');
        }

        if ($request->filled('category')) {
            $category = (string)$request->query('category');
            $query->whereHas('categories', function ($q) use ($category) {
                $q->where('slug', $category);
            });
        }

        if ($request->filled('tag')) {
            $tag = (string)$request->query('tag');

            if ($tag === 'new') {
                $query->orderByDesc('created_at')->orderByDesc('products.id');
            } elseif ($tag === 'bestseller') {
                $query->orderByDesc('products.id');
            } else {
                $query->orderByDesc('products.id');
            }
        } else {
            $query->orderByDesc('products.id');
        }

        return ProductResource::collection($query->paginate($per_page));
    }

    /**
     * Product detail by slug.
     * @unauthenticated
     * @urlParam slug string required Product slug (URL-safe)
     * @response 404 scenario="Product not found" {"message": "No query results for model [App\\Models\\Product]."}
     */
    public function show(string $slug): ProductResource
    {
        $product = Product::with([
            'supplier:id,name',
            'images:id,product_id,url,is_main,position',
            'mainImage:id,product_id,url,is_main,position',
            'hoverImage:id,product_id,url,is_main,position',
            'categories:id,name,slug,parent_id',
            'categories.parent:id,name,slug,parent_id',
            'group:id,name,slug,type',
            'group.products:id,group_id,slug,color_code,color_label,is_active',
            'group.products.mainImage:id,product_id,url,is_main,position',
            'options' => function ($q) {
                $q->select('id', 'product_id', 'type', 'code', 'label', 'position')->orderBy('position')->withSum('lots as stock_qty', 'quantity');
            },
            'lots:id,product_id,product_option_id,lot_number,quantity',
        ])->where('slug', $slug)->firstOrFail();

        return new ProductResource($product);
    }
}
