<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductOption;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

#[Group(name: 'Admin - Produits', weight: 10)]
class AdminProductController extends Controller
{
    /**
     * Liste paginée des produits (admin).
     *
     * Inclut les produits inactifs et permet la recherche/filtrage. Pagination fixe à 20/page.
     *
     * @queryParam search string Recherche par nom ou SKU. Example: t-shirt
     * @queryParam is_active boolean Filtrer par statut actif. Example: true
     * @queryParam is_customizable boolean Filtrer les produits customisables. Example: true
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::with([
            'mainImage:id,product_id,url,is_main,position',
            'categories:id,name,slug',
            'options:id,product_id,type,code,label,position',
        ])->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('is_customizable')) {
            $query->where('is_customizable', filter_var($request->query('is_customizable'), FILTER_VALIDATE_BOOLEAN));
        }

        return ProductResource::collection($query->paginate(20));
    }

    /**
     * Détail d'un produit (admin).
     */
    public function show(Product $product): ProductResource
    {
        $product->load([
            'images:id,product_id,url,is_main,position',
            'mainImage:id,product_id,url,is_main,position',
            'hoverImage:id,product_id,url,is_main,position',
            'options:id,product_id,type,code,label,position,is_active',
            'categories:id,name,slug',
            'supplier:id,name',
        ]);

        return new ProductResource($product);
    }

    /**
     * Créer un produit.
     *
     * Le slug est dérivé du nom et dédupliqué automatiquement (suffixe `-N` si conflit). Les
     * options (taille, format, capacité) peuvent être passées en une seule requête.
     *
     * @response 422 scenario="SKU déjà utilisé" {"message": "The sku has already been taken."}
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'price_ht' => ['required', 'numeric', 'min:0'],
            'price_ttc' => ['required', 'numeric', 'min:0'],
            'vat' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'is_customizable' => ['boolean'],
            'customization_mode' => ['nullable', 'in:2d,3d'],
            'allow_text_customization' => ['boolean'],
            'allow_image_upload' => ['boolean'],
            'allow_ai_generation' => ['boolean'],
            'options' => ['nullable', 'array'],
            'options.*.type' => ['required', 'in:size,format,capacity'],
            'options.*.code' => ['required', 'string', 'max:50'],
            'options.*.label' => ['nullable', 'string', 'max:255'],
            'options.*.position' => ['nullable', 'integer'],
        ]);

        $slug = Str::slug($data['name']);
        $base = $slug;
        $i = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        $product = Product::create([
            'name' => $data['name'],
            'slug' => $slug,
            'sku' => $data['sku'],
            'description' => $data['description'] ?? null,
            'price_ht' => $data['price_ht'],
            'price_ttc' => $data['price_ttc'],
            'vat' => $data['vat'],
            'is_active' => $data['is_active'] ?? true,
            'is_customizable' => $data['is_customizable'] ?? false,
            'customization_mode' => $data['customization_mode'] ?? null,
            'allow_text_customization' => $data['allow_text_customization'] ?? false,
            'allow_image_upload' => $data['allow_image_upload'] ?? false,
            'allow_ai_generation' => $data['allow_ai_generation'] ?? false,
        ]);

        foreach ($data['options'] ?? [] as $idx => $opt) {
            ProductOption::create([
                'product_id' => $product->id,
                'type' => $opt['type'],
                'code' => $opt['code'],
                'label' => $opt['label'] ?? $opt['code'],
                'position' => $opt['position'] ?? $idx,
                'is_active' => true,
            ]);
        }

        $product->load('options');

        return response()->json(new ProductResource($product), 201);
    }

    /**
     * Mettre à jour un produit.
     *
     * Tous les champs sont optionnels (`sometimes`). Le slug n'est pas modifiable ici.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_ht' => ['sometimes', 'numeric', 'min:0'],
            'price_ttc' => ['sometimes', 'numeric', 'min:0'],
            'vat' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'is_customizable' => ['boolean'],
            'customization_mode' => ['nullable', 'in:2d,3d'],
            'allow_text_customization' => ['boolean'],
            'allow_image_upload' => ['boolean'],
            'allow_ai_generation' => ['boolean'],
        ]);

        $product->update($data);

        return response()->json(new ProductResource($product->fresh()));
    }

    /**
     * Supprimer un produit.
     *
     * Soft-delete si le modèle utilise `SoftDeletes`. Les commandes existantes conservent leur snapshot.
     *
     * @response 200 {"message": "Produit supprimé."}
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Produit supprimé.']);
    }

    /**
     * Activer / désactiver un produit.
     *
     * Bascule la valeur `is_active`. Renvoie l'état final.
     *
     * @response 200 {"is_active": true}
     */
    public function toggleActive(Product $product): JsonResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return response()->json(['is_active' => $product->is_active]);
    }
}
