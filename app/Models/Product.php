<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'supplier_id', 'name', 'description', 'price_ht', 'price_ttc',
        'vat', 'sku', 'barcode', 'weight', 'is_active',
    ];

    protected $casts = [
        'price_ht' => 'float',
        'price_ttc' => 'float',
        'vat' => 'float',
        'weight' => 'float',
        'is_active' => 'boolean',
        'attributes' => 'array',
    ];

    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_category');
    }

    public function images() {
        return $this->hasMany(ProductImage::class);
    }

    public function mainImage() {
        return $this->hasOne(ProductImage::class)->where('is_main', true);
    }

    public function lots() {
        return $this->hasMany(StockLot::class);
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
}
