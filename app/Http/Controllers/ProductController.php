<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::active()
            ->with(['mainImage', 'categories']);

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

            // Si on an un genre → on force le contexte
            if ($request->filled('gender')) {
                $query->whereHas('categories', function ($q) use ($request) {
                    $q->where('slug', 'like', $request->gender . '%-' . $request->category);
                });
            } else {
                // fallback (ancien comportement)
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
            if ($request->tag === 'new') {
                $query->orderByDesc('created_at');
            }

            if ($request->tag === 'bestseller') {
                $query->inRandomOrder(); // temporaire
            }
        }

        return ProductResource::collection(
            $query->paginate(12)
        );
    }

    public function show(int $id)
    {
        $product = Product::with([
            'images',
            'mainImage',
            'categories',
            'lots',
        ])->findOrFail($id);

        return new ProductResource($product);
    }
}
