<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'guest_token'];

    // Owner of this cart
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Items contained in this cart
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
