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

    /** Product this image belongs to. */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Returns the absolute public URL of the image. */
    public function getFullUrlAttribute(): ?string
    {
        if (!$this->url) {
            return null;
        }

        // If it's already an absolute URL
        if (str_starts_with($this->url, 'http://') || str_starts_with($this->url, 'https://')) {
            return $this->url;
        }

        // Generate the correct public URL: /storage/products/...
        return asset('storage/' . ltrim($this->url, '/'));
    }
}
