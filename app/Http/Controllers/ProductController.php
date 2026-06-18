<?php

namespace App\Http\Controllers;

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
     * Liste paginée des produits publics.
     *
     * Renvoie les produits actifs visibles sur la boutique. Les produits déclinés en variantes
     * (couleur, taille) sont regroupés : un seul produit par groupe est retourné. Supporte le
     * filtrage par genre, catégorie et tag.
     *
     * @unauthenticated
     *
     * @queryParam per_page integer Nombre de produits par page (1–60, défaut 12). Example: 24
     * @queryParam gender string Slug d'une catégorie genre (ex: "homme", "femme"). Example: homme
     * @queryParam category string Slug d'une catégorie (ex: "t-shirts"). Example: t-shirts
     * @queryParam tag string Tri spécial : "new" (nouveautés), "bestseller" (meilleures ventes). Example: new
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $defaultPerPage = 12;
        $maxPerPage = 60;

        $perPage = (int)$request->query('per_page', $defaultPerPage);
        $perPage = max(1, min($perPage, $maxPerPage));

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
                $q->where('slug', $gender)
                    ->orWhere('slug', 'like', $gender . '-%');
            });
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
                $query->orderByDesc('created_at')
                    ->orderByDesc('products.id');
            } elseif ($tag === 'bestseller') {
                $query->orderByDesc('products.id');
            } else {
                $query->orderByDesc('products.id');
            }
        } else {
            $query->orderByDesc('products.id');
        }

        return ProductResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Détail d'un produit par slug.
     *
     * Renvoie un produit avec toutes ses relations (images, catégories, options, lots de stock,
     * variantes du groupe). Utilisé sur la page produit du frontend.
     *
     * @unauthenticated
     *
     * @urlParam slug string required Slug du produit (URL-safe). Example: t-shirt-fitness-noir
     *
     * @response 404 scenario="Produit introuvable" {"message": "No query results for model [App\\Models\\Product]."}
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
                $q->select('id', 'product_id', 'type', 'code', 'label', 'position')
                    ->orderBy('position')
                    ->withSum('lots as stock_qty', 'quantity');
            },
            'lots:id,product_id,product_option_id,lot_number,quantity',
        ])->where('slug', $slug)->firstOrFail();

        return new ProductResource($product);
    }
}
