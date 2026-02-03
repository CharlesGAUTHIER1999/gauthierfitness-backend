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

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['name'];

    public function getNameAttribute(): string
    {
        return trim(($this->firstname ?? '') . ' ' . ($this->lastname ?? ''));
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
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

    /**
     * ✅ IMPORTANT: hasManyThrough doit avoir les clés explicites,
     * sinon tu peux te retrouver avec "panier vide" côté back.
     */
    public function cartItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            CartItem::class,
            Cart::class,
            'user_id', // FK on carts
            'cart_id', // FK on cart_items
            'id',      // local key on users
            'id'       // local key on carts
        );
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
}
