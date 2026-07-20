<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookFailure extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_event_id',
        'error_message',
        'retry_count',
    ];

    // Webhook event this failure is associated with
    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }
}
