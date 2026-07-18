<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'firstname',
        'lastname',
        'address',
        'zip',
        'city',
        'country',
        'phone',
        'method',
        'cost',
        'carrier',
        'tracking_url',
        'status',
    ];

    /** Order this shipment is for. */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
