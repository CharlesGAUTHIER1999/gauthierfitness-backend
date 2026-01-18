<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookFailure extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_event_id',
        'error_message',
        'retry_count',
    ];

    public function event()
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }
}
