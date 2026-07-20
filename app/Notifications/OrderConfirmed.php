<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\Pricing\ShippingCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmed extends Notification
{
    use Queueable;

    // Bind the confirmed order to this notification
    public function __construct(public Order $order) {}

    // Deliver this notification by mail only
    public function via($notifiable): array
    {
        return ['mail'];
    }

    // Build the order confirmation email
    public function toMail($notifiable): MailMessage
    {
        $order = $this->order->loadMissing(['items.product', 'shipment']);
        $name = $order->user?->firstname ?? $order->shipment?->firstname ?? 'client';

        $mail = (new MailMessage)
            ->subject("Commande #$order->id confirmée ✅")
            ->replyTo(config('mail.support_address') ?? config('mail.from.address'))
            ->greeting("Bonjour $name,")
            ->line('Merci pour votre commande chez Gauthier Fitness.')
            ->line('Votre paiement a bien été confirmé.')
            ->line(' ')
            ->line('—— Détails de votre commande ——')
            ->line("Commande : #$order->id")
            ->line("Date : {$order->created_at->format('d/m/Y à H:i')}")
            ->line('Statut : Confirmée')
            ->line('Total : '.number_format((float) $order->total_ttc, 2, ',', ' ').' €')
            ->line(' ')
            ->line('Produits :');

        foreach ($order->items as $item) {
            $name = $item->product?->name ?? 'Produit';
            $quantity = (int) $item->quantity;
            $line_total = number_format((float) $item->total, 2, ',', ' ').' €';
            $mail->line("• $name ×$quantity — $line_total");
        }

        if ($order->shipment) {
            $method_label = ShippingCalculator::METHOD_LABELS[$order->shipment->method] ?? 'Standard';
            $shipping_cost = (float) $order->shipment->cost;
            $shipping_line = $shipping_cost > 0 ? number_format($shipping_cost, 2, ',', ' ').' €' : 'Gratuite';
            $mail->line(' ')->line("Livraison ($method_label) : $shipping_line");
        }

        // Guest orders aren't tied to an account
        if ($order->user) {
            $mail->line(' ')->line('Vous pouvez suivre l’évolution de votre commande depuis votre espace client.')->action('Voir mes commandes', config('app.front_url').'/account/orders');
        }

        return $mail->salutation('— Gauthier Fitness');
    }
}
