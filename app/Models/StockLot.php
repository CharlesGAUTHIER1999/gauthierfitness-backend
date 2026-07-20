<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLot extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'product_option_id', 'lot_number', 'expiration_date', 'initial_quantity', 'quantity'];

    protected $casts = ['expiration_date' => 'date'];

    // Product this stock lot belongs to. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Product option this stock lot belongs to
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    // Stock movements recorded against this lot
    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'lot_id');
    }
}
