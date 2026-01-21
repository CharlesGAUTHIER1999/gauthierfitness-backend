<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $fillable = [
        'firstname', 'lastname', 'email', 'password', 'phone',
        'is_b2b', 'company_name', 'address', 'zip', 'city',
    ];

    protected $hidden = ['password', 'remember_token'];

    // Optionnel mais très utile : expose user.name (sans colonne SQL)
    protected $appends = ['name'];

    public function getNameAttribute(): string
    {
        return trim(($this->firstname ?? '') . ' ' . ($this->lastname ?? ''));
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function loyaltyAccount(): HasOne
    {
        return $this->hasOne(LoyaltyPointAccount::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ✅ Relation la plus logique : user -> cart -> items
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    // ⚠️ Ce hasMany direct peut rester, mais il est “moins propre” conceptuellement
    // car CartItem appartient à Cart, pas à User.
    public function cartItems(): HasManyThrough
    {
        return $this->hasManyThrough(CartItem::class, Cart::class);
    }
}
