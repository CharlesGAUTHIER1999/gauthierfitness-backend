<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomProductSession extends Model
{
    protected $fillable = [
        'user_id',
        'guest_token',
        'product_id',
        'product_option_id',
        'status',
        'configuration',
        'design_id',
        'preview_image_path',
        'unit_price_snapshot',
    ];

    protected $casts = [
        'configuration' => 'array',
        'unit_price_snapshot' => 'decimal:2',
    ];

    // Owner of this customization session
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Product being customized in this session
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Product option selected in this session
    public function productOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class);
    }

    // Design attached to this customization session
    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    // Cart items created from this session
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // Order items created from this session
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
