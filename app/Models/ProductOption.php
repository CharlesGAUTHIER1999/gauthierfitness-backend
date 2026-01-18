<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'code',
        'label',
        'price_ht',
        'price_ttc',
        'vat',
        'position',
        'meta',
        'sku',
        'is_active',
    ];

    protected $casts = [
        'price_ht' => 'float',
        'price_ttc' => 'float',
        'vat' => 'float',
        'position' => 'integer',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function lots() {
        return $this->hasMany(StockLot::class, 'product_option_id');
    }
}
