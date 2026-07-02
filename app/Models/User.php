<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'firstname', 'lastname', 'email', 'password', 'phone',
        'is_b2b', 'company_name', 'address', 'zip', 'city',
        'user_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['name'];

    /** Returns the user's full name. */
    public function getNameAttribute(): string
    {
        return trim(($this->firstname ?? '').' '.($this->lastname ?? ''));
    }

    /** Roles assigned to this user. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /** Orders placed by this user. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Most logical relation: user -> cart -> items

    /** Cart belonging to this user. */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * Items in the user's cart.
     *
     * IMPORTANT: hasManyThrough needs explicit keys, otherwise you can end up
     * with an empty cart on the backend.
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

    /** Checks whether this user has the given role. */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /** Checks whether this user has the admin role. */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /** Sends the password reset notification with a link pointing to the frontend */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
