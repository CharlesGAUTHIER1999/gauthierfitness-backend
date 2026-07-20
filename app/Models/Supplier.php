<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'contact_email',
        'phone',
    ];

    // Products provided by this supplier
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
