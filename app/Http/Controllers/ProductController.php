<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // ✅ 1 seul produit par groupe (anti-duplication)
        $query = Product::active()
            ->select('products.*')
            ->whereIn('products.id', function ($sub) {
                $sub->selectRaw('MIN(id)')
                    ->from('products')
                    ->where('is_active', true)
                    ->groupBy(DB::raw('COALESCE(group_id, id)'));
            })
            ->with([
                'mainImage',
                'hoverImage',
                'images',
                'categories.parent', // ✅ utile pour deviner nutrition => flavor
                'group',             // ✅ pour type color/flavor
                'options' => fn ($q) => $q->where('type', 'size')->orderBy('position'),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Filtre par genre (racine)
        |--------------------------------------------------------------------------
        | femmes / hommes / nutrition / equipments
        */
        if ($request->filled('gender')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('slug', 'like', $request->gender . '%');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre par catégorie EN CONTEXTE
        |--------------------------------------------------------------------------
        */
        if ($request->filled('category')) {

            if ($request->filled('gender')) {
                $query->whereHas('categories', function ($q) use ($request) {
                    $q->where(
                        'slug',
                        'like',
                        $request->gender . '%-' . $request->category
                    );
                });
            } else {
                $query->whereHas('categories', function ($q) use ($request) {
                    $q->where('slug', 'like', '%-' . $request->category);
                });
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Tags (new / bestseller)
        |--------------------------------------------------------------------------
        */
        if ($request->filled('tag')) {
            $tag = (string) $request->get('tag');

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

        $perPage = (int) $request->get('per_page', $defaultPerPage);

        return ProductResource::collection(
            $query->paginate($perPage)
        );
    }

    public function show(string $slug): ProductResource
    {
        $product = Product::with([
            'supplier',
            'images',
            'mainImage',
            'hoverImage',
            'categories.parent',
            'group',

            // variantes (couleurs ou goûts)
            'group.products' => function ($q) {
                $q->select('id', 'slug', 'group_id', 'color_code', 'color_label')
                    ->where('is_active', true);
            },
            'group.products.mainImage',

            'options' => function ($q) {
                $q->orderBy('position')
                    ->withSum('lots as stock_qty', 'quantity');
            },

            'lots',
        ])->where('slug', $slug)->firstOrFail();

        return new ProductResource($product);
    }
}
