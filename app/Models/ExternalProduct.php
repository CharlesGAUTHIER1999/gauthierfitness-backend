<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalProduct extends Model
{
    use HasFactory;

    protected $table = 'external_products';

    /**
     * Champs assignables en masse
     */
    protected $fillable = [
        'source',
        'source_product_id',
        'name',
        'description',
        'price',
        'images',
        'category_path',
        'raw_payload',
        'imported_at',
    ];

    /**
     * Casts automatiques
     */
    protected $casts = [
        'images' => 'array',
        'raw_payload' => 'array',
        'imported_at' => 'datetime',
    ];

    /**
     * Scopes utiles
     */

    // Filtrer par source (gymshark, tsunami, rogue)
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    // Produits importés récemment
    public function scopeRecentlyImported($query, int $days = 1)
    {
        return $query->where('imported_at', '>=', now()->subDays($days));
    }

    /**
     * Helpers métier
     */

    public function markAsImported(): void
    {
        $this->update([
            'imported_at' => now(),
        ]);
    }

    public function hasImages(): bool
    {
        return !empty($this->images);
    }

    public function getMainImageAttribute(): ?string
    {
        return $this->images[0] ?? null;
    }
}

