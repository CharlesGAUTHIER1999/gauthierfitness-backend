<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'sender_type',
        'sender_id',
        'message',
    ];

    // Ticket this message belongs to
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    // User who sent this message
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
