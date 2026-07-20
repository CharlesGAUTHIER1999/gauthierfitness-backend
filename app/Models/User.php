<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    protected $casts = ['email_verified_at' => 'datetime',];
    protected $hidden = ['password', 'remember_token'];
    protected $appends = ['name'];

    // Returns the user's full name
    public function getNameAttribute(): string
    {
        return trim(($this->firstname ?? '').' '.($this->lastname ?? ''));
    }

    // Roles assigned to this user
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    // Orders placed by this user
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Cart belonging to this user
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    // Checks whether this user has the given role
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    // Checks whether this user has the admin role
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    // Public-facing user payload
    public function authPayload(): array
    {
        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'zip' => $this->zip,
            'city' => $this->city,
            'is_admin' => $this->isAdmin(),
        ];
    }

    // Sends the password reset notification
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
