<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;
    protected $fillable = [
        'lot_id',
        'product_id',
        'quantity',
        'type',
        'reason',
    ];

    public function lot() {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }
}
