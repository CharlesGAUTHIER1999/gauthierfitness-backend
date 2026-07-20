<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'subject', 'status'];

    // Customer who opened this ticket
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Messages exchanged within this ticket
    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }
}
