<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
{
    use Queueable;

    /** Bind the order and its new status to this notification. */
    public function __construct(
        public Order $order,
        public string $status,
    ) {}

    /** Deliver this notification by mail only. */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /** Build the order status update email with a human-readable status label. */
    public function toMail($notifiable): MailMessage
    {
        $labels = [
            'shipped' => 'expédiée 📦',
            'delivered' => 'livrée ✅',
            'canceled' => 'annulée ❌',
        ];

        $label = $labels[$this->status] ?? $this->status;

        return (new MailMessage)
            ->subject("Commande #{$this->order->id} $label")
            ->replyTo(config('mail.support_address'))
            ->greeting("Bonjour $notifiable->firstname,")
            ->line('Le statut de votre commande a été mis à jour.')
            ->line("Commande : #{$this->order->id}")
            ->line("Nouveau statut : $label")
            ->action('Voir ma commande', config('app.front_url').'/account/orders')
            ->salutation('— Gauthier Fitness');
    }
}
