<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLot extends Model
{
    use HasFactory;
    protected $fillable = ['product_id', 'lot_number', 'expiration_date', 'quantity'];
    protected $casts = [
        'expiration_date' => 'date',
    ];
    public function product() {
        return $this->belongsTo(Product::class);
    }
    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'lot_id');
    }
}
