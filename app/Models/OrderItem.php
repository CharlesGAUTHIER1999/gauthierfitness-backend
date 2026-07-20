<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_option_id',
        'custom_product_session_id',
        'lot_id',
        'unit_price',
        'quantity',
        'total',
        'customization_snapshot',
        'customization_preview_path',
    ];

    protected $casts = [
        'customization_snapshot' => 'array',
    ];

    // Order this item belongs to
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Product purchased in this order item
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Product option purchased in this order item
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    // Stock lot this order item was fulfilled from
    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    // Custom product session tied to this order item, if any
    public function customProductSession(): BelongsTo
    {
        return $this->belongsTo(CustomProductSession::class);
    }
}
