<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_option_id',
        'custom_product_session_id',
        'quantity',
    ];

    protected $casts = ['quantity' => 'integer',];
    protected $touches = ['cart'];

    // Cart this item belongs to
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    // Product referenced by this cart item
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Selected product option for this cart item
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    // Custom product session associated with this cart item
    public function customProductSession(): BelongsTo
    {
        return $this->belongsTo(CustomProductSession::class, 'custom_product_session_id');
    }
}
