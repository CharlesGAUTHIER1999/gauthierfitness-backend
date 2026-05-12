<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class AdminProductController extends Controller
{
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'sku'                      => ['required', 'string', 'max:100', 'unique:products,sku'],
            'description'              => ['nullable', 'string'],
            'price_ht'                 => ['required', 'numeric', 'min:0'],
            'price_ttc'                => ['required', 'numeric', 'min:0'],
            'vat'                      => ['required', 'numeric', 'min:0'],
            'is_active'                => ['boolean'],
            'is_customizable'          => ['boolean'],
            'customization_mode'       => ['nullable', 'in:2d,3d'],
            'allow_text_customization' => ['boolean'],
            'allow_image_upload'       => ['boolean'],
            'allow_ai_generation'      => ['boolean'],
            'options'                  => ['nullable', 'array'],
            'options.*.type'           => ['required', 'in:size,format,capacity'],
            'options.*.code'           => ['required', 'string', 'max:50'],
            'options.*.label'          => ['nullable', 'string', 'max:255'],
            'options.*.position'       => ['nullable', 'integer'],
        ]);

        $slug = Str::slug($data['name']);
        $base = $slug;
        $i    = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        $product = Product::create([
            'name'                     => $data['name'],
            'slug'                     => $slug,
            'sku'                      => $data['sku'],
            'description'              => $data['description'] ?? null,
            'price_ht'                 => $data['price_ht'],
            'price_ttc'                => $data['price_ttc'],
            'vat'                      => $data['vat'],
            'is_active'                => $data['is_active'] ?? true,
            'is_customizable'          => $data['is_customizable'] ?? false,
            'customization_mode'       => $data['customization_mode'] ?? null,
            'allow_text_customization' => $data['allow_text_customization'] ?? false,
            'allow_image_upload'       => $data['allow_image_upload'] ?? false,
            'allow_ai_generation'      => $data['allow_ai_generation'] ?? false,
        ]);

        foreach ($data['options'] ?? [] as $idx => $opt) {
            ProductOption::create([
                'product_id' => $product->id,
                'type'       => $opt['type'],
                'code'       => $opt['code'],
                'label'      => $opt['label'] ?? $opt['code'],
                'position'   => $opt['position'] ?? $idx,
                'is_active'  => true,
            ]);
        }

        $product->load('options');

        return response()->json(new ProductResource($product), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'name'                     => ['sometimes', 'string', 'max:255'],
            'description'              => ['nullable', 'string'],
            'price_ht'                 => ['sometimes', 'numeric', 'min:0'],
            'price_ttc'                => ['sometimes', 'numeric', 'min:0'],
            'vat'                      => ['sometimes', 'numeric', 'min:0'],
            'is_active'                => ['boolean'],
            'is_customizable'          => ['boolean'],
            'customization_mode'       => ['nullable', 'in:2d,3d'],
            'allow_text_customization' => ['boolean'],
            'allow_image_upload'       => ['boolean'],
            'allow_ai_generation'      => ['boolean'],
        ]);

        $product->update($data);

        return response()->json(new ProductResource($product->fresh()));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        return response()->json(['message' => 'Produit supprimé.']);
    }

    public function toggleActive(Product $product): JsonResponse
    {
        $product->update(['is_active' => !$product->is_active]);
        return response()->json(['is_active' => $product->is_active]);
    }
}
