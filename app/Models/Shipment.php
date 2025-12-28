<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'address',
        'carrier',
        'tracking_url',
        'status',
    ];

    public function order() {
        return $this->belongsTo(Order::class);
    }
}
