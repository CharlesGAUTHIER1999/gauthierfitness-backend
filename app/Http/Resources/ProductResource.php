<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    // Transform product into its public API representation
    public function toArray(Request $request): array
    {
        // Fallback if mainImage/hoverImage relations aren't loaded
        $main_from_images = $this->relationLoaded('images') ? $this->images->firstWhere('is_main', true) : null;
        $hover_from_images = $this->relationLoaded('images') ? $this->images->firstWhere('is_main', false) : null;
        $main = $this->relationLoaded('mainImage') ? $this->mainImage : $main_from_images;
        $hover = $this->relationLoaded('hoverImage') ? $this->hoverImage : $hover_from_images;
        $main_url = is_object($main) ? $main->full_url : (is_string($main) ? asset('storage/'.ltrim($main, '/')) : null);
        $hover_url = is_object($hover) ? $hover->full_url : (is_string($hover) ? asset('storage/'.ltrim($hover, '/')) : null);
        $variant_type = $this->group?->type ?: $this->inferVariantTypeFromCategories();
        $variant_name = $variant_type === 'flavor' ? 'Goûts' : 'Couleurs';

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price_ht' => $this->price_ht,
            'price_ttc' => $this->price_ttc,
            'vat' => $this->vat,
            'color_code' => $this->color_code,
            'color_label' => $this->color_label,
            'variant_type' => $variant_type,
            'variant_name' => $variant_name,
            'variant_value_code' => $this->color_code,
            'variant_value_label' => $this->color_label,
            'flavor_code' => $variant_type === 'flavor' ? $this->color_code : null,
            'flavor_label' => $variant_type === 'flavor' ? $this->color_label : null,

            'group' => $this->whenLoaded('group', fn () => [
                'id' => $this->group?->id,
                'name' => $this->group?->name,
                'slug' => $this->group?->slug,
                'type' => $this->group?->type,
            ]),

            'main_image' => $main_url,
            'hover_image' => $hover_url,

            // Full gallery
            'images' => $this->whenLoaded('images', function () {
                return $this->images
                    ->sortBy(function ($image) {
                        return ((int) ($image->position ?? 0)) * 1000000 + (int) $image->id;
                    })
                    ->values()
                    ->map(fn ($image) => [
                        'id' => $image->id,
                        'url' => $image->full_url,
                        'is_main' => (bool) $image->is_main,
                        'position' => (int) ($image->position ?? 0),
                    ]);
            }),

            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
            ]),

            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])->values();
            }),

            'sizes_preview' => $this->whenLoaded('options', function () {
                return $this->options
                    ->where('type', 'size')
                    ->pluck('code')
                    ->values();
            }),

            'options' => $this->whenLoaded('options', function () {
                return $this->options->map(fn ($option) => [
                    'id' => $option->id,
                    'type' => $option->type,
                    'code' => $option->code,
                    'label' => $option->label,
                    'stock_qty' => (int) ($option->stock_qty ?? 0),
                    'in_stock' => ((int) ($option->stock_qty ?? 0)) > 0,
                ])->values();
            }),

            'lots' => $this->whenLoaded('lots', function () {
                return $this->lots->map(fn ($lot) => [
                    'id' => $lot->id,
                    'label' => $lot->lot_number,
                    'quantity' => (int) $lot->quantity,
                    'in_stock' => (int) $lot->quantity > 0,
                ])->values();
            }),

            'variants' => $this->when(
                $this->relationLoaded('group') && $this->group && $this->group->relationLoaded('products'),
                function () use ($variant_type, $variant_name) {
                    return $this->group->products->map(function ($p) use ($variant_type, $variant_name) {
                        $image = $p->relationLoaded('mainImage') ? $p->mainImage : null;
                        $url = is_object($image) ? $image->full_url : (is_string($image) ? asset('storage/'.ltrim($image, '/')) : null);

                        return [
                            'id' => $p->id,
                            'slug' => $p->slug,
                            'color_code' => $p->color_code,
                            'color_label' => $p->color_label,
                            'variant_type' => $variant_type,
                            'variant_name' => $variant_name,
                            'variant_value_code' => $p->color_code,
                            'variant_value_label' => $p->color_label,
                            'flavor_code' => $variant_type === 'flavor' ? $p->color_code : null,
                            'flavor_label' => $variant_type === 'flavor' ? $p->color_label : null,
                            'thumb_url' => $url,
                        ];
                    })->values();
                }
            ),

            'customization' => [
                'mode' => $this->customization_mode,
                'text' => (bool) $this->allow_text_customization,
                'image' => (bool) $this->allow_image_upload,
                'ai' => (bool) $this->allow_ai_generation,
            ],

            'is_customizable' => (bool) $this->is_customizable,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    // Variant type ("flavor" vs "color") from product's rootcategory
    private function inferVariantTypeFromCategories(): ?string
    {
        if (! $this->relationLoaded('categories')) return null;
        $category = $this->categories->first();
        if (! $category) return null;
        $root = $category->parent?->slug ?? $category->slug;
        return $root === 'nutrition' ? 'flavor' : 'color';
    }
}
