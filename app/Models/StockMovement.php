<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'lot_id',
        'product_id',
        'product_option_id',
        'user_id',
        'quantity',
        'type',
        'reason',
    ];

    // Stock lot this movement is recorded against
    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    // Product this movement relates to
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
