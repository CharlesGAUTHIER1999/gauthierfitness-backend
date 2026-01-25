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
        'name',
        'firstname',
        'email',
        'phone',
        'cellphone',
        'address',
        'zip',
        'city',
        'siret',
        'activate',
        'created_at',
        'is_b2b'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['name'];

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

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function cartItems(): HasManyThrough
    {
        return $this->hasManyThrough(CartItem::class, Cart::class);
    }
}
