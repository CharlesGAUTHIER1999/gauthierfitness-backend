<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'url',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    protected $appends = ['full_url'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getFullUrlAttribute(): ?string
    {
        if (!$this->url) {
            return null;
        }

        // Si c'est déjà une URL absolue
        if (str_starts_with($this->url, 'http://') || str_starts_with($this->url, 'https://')) {
            return $this->url;
        }

        // Génère l’URL publique correcte : /storage/products/...
        return asset('storage/' . ltrim($this->url, '/'));
    }
}