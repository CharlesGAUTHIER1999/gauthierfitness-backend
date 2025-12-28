<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyPointMovement extends Model
{
    use HasFactory;
    protected $fillable = [
        'account_id',
        'type',
        'amount',
        'reason'
    ];

    public function account() {
        return $this->belongsTo(LoyaltyPointAccount::class, 'account_id');
    }
}
