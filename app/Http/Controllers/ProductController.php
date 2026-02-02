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
        // --- pagination: cap pour éviter les OOM ---
        $defaultPerPage = 12;
        $maxPerPage = 48; // ✅ important: évite per_page=200 qui tue la RAM

        $isAllProductsPage = !$request->filled('gender') && !$request->filled('category') && !$request->filled('tag');
        if (!$request->has('per_page') && $isAllProductsPage) {
            $defaultPerPage = 24; // au lieu de 50 => plus safe
        }

        $perPage = (int) $request->get('per_page', $defaultPerPage);
        $perPage = max(1, min($perPage, $maxPerPage));

        // --- base query ---
        $query = Product::active()
            ->select('products.*') // tu peux réduire ici si ton ProductResource n'a pas besoin de tout
            ->whereIn('products.id', function ($sub) {
                $sub->selectRaw('MIN(id)')
                    ->from('products')
                    ->where('is_active', true)
                    ->groupBy(DB::raw('COALESCE(group_id, id)'));
            })
            ->with([
                // ✅ listing: seulement ce qui sert à afficher une carte produit
                'mainImage',
                'hoverImage',

                // ⚠️ 'images' retiré du listing (trop lourd) => garde-le dans show()
                // 'images',

                // si ton listing affiche des badges/catégories
                'categories:id,slug,parent_id',
                'categories.parent:id,slug,parent_id',

                // si tu affiches un "type" / variantes / etc.
                'group:id',
                'options' => fn ($q) => $q->where('type', 'size')->orderBy('position'),
            ]);

        // --- Filtre gender ---
        if ($request->filled('gender')) {
            $gender = (string) $request->get('gender');
            $query->whereHas('categories', function ($q) use ($gender) {
                $q->where('slug', 'like', $gender . '%');
            });
        }

        // --- Filtre category (contextuel) ---
        if ($request->filled('category')) {
            $category = (string) $request->get('category');
            $gender = $request->filled('gender') ? (string) $request->get('gender') : null;

            $query->whereHas('categories', function ($q) use ($category, $gender) {
                if ($gender) {
                    $q->where('slug', 'like', $gender . '%-' . $category);
                } else {
                    $q->where('slug', 'like', '%-' . $category);
                }
            });
        }

        // --- Tags / ordering ---
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
