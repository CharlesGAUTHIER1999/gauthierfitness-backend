<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'group_id',
        'name',
        'slug',
        'brand',
        'origin',
        'color_code',
        'color_label',
        'description',
        'price_ht',
        'price_ttc',
        'vat',
        'sku',
        'barcode',
        'weight',
        'is_active',
        'attributes',
        'is_customizable',
        'customization_mode',
        'allow_text_customization',
        'allow_image_upload',
        'allow_ai_generation',
    ];

    protected $casts = [
        'price_ht' => 'float',
        'price_ttc' => 'float',
        'vat' => 'float',
        'weight' => 'float',
        'is_active' => 'boolean',
        'attributes' => 'array',
        'group_id' => 'integer',
        'is_customizable' => 'boolean',
        'allow_text_customization' => 'boolean',
        'allow_image_upload' => 'boolean',
        'allow_ai_generation' => 'boolean',
    ];

    protected $appends = [
        'main_image',
        'hover_image',
    ];

    // Supplier providing this product
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // Product group this product belongs to
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'group_id');
    }

    // Other product variants
    public function variants()
    {
        return $this->hasMany(Product::class, 'group_id', 'group_id')->whereKeyNot($this->getKey())->orderBy('color_code');
    }

    // Categories this product is assigned to
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category');
    }

    // All images for this product, ordered by position
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    // Primary (main) image of this product
    public function mainImage()
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_main', true)
            ->orderBy('position')
            ->orderBy('id');
    }

    // Secondary (hover) image of this product
    public function hoverImage()
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_main', false)
            ->orderBy('position')
            ->orderBy('id');
    }

    // Selectable options for this product
    public function options()
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    // Stock lots for this product
    public function lots()
    {
        return $this->hasMany(StockLot::class);
    }

    // Cart items referencing this product
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    // Designs created for this product
    public function designs()
    {
        return $this->hasMany(Design::class);
    }

    // Customization sessions created for this product
    public function customizationSessions()
    {
        return $this->hasMany(CustomProductSession::class);
    }

    // Returns the URL of the product's main image
    public function getMainImageAttribute(): ?string
    {
        return $this->images->firstWhere('is_main', true)?->url;
    }

    // Returns the URL of the product's hover image
    public function getHoverImageAttribute(): ?string
    {
        return $this->images->firstWhere('is_main', false)?->url;
    }

    // Scopes the query to only active products
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scopes the query to products
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%$term%")
                ->orWhere('sku', 'like', "%$term%");
        });
    }
}
