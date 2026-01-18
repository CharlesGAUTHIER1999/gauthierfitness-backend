<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Infos principales
            'name' => $this->name,
            'description' => $this->description,
            'price_ht' => $this->price_ht,
            'price_ttc' => $this->price_ttc,
            'vat' => $this->vat,

            // Image principale
            'image' => $this->mainImage?->url,

            // Toutes les images (page détail)
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(fn($img) => [
                    'url' => $img->url,
                    'is_main' => $img->is_main,
                ]);
            }),

            // Catégories (pour filtres et breadcrumbs)
            'categories' => $this->categories->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ]),

            // Variantes (tailles / formats)
            'variants' => $this->whenLoaded('lots', function () {
                return $this->lots->map(fn($lot) => [
                    'id' => $lot->id,
                    'label' => $lot->lot_number, // XS, S, M, 1kg, 500g…
                    'quantity' => $lot->quantity,
                    'in_stock' => $lot->quantity > 0,
                ]);
            }),

            // Flags utiles front
            'is_active' => $this->is_active,

            // Métadonnées
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
