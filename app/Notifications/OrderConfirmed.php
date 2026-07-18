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

    /** Bind the confirmed order to this notification. */
    public function __construct(public Order $order) {}

    /** Deliver this notification by mail only. */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /** Build the order confirmation email, listing items and total. */
    public function toMail($notifiable): MailMessage
    {
        $order = $this->order->loadMissing(['items.product']);

        $mail = (new MailMessage)
            ->subject("Commande #{$order->id} confirmée ✅")
            ->replyTo(config('mail.support_address') ?? config('mail.from.address'))
            ->greeting("Bonjour {$notifiable->firstname},")
            ->line('Merci pour votre commande chez Gauthier Fitness.')
            ->line('Votre paiement a bien été confirmé.')
            ->line(' ')
            ->line('—— DÉTAILS DE LA COMMANDE ——')
            ->line("Commande : #{$order->id}")
            ->line("Date : {$order->created_at->format('d/m/Y à H:i')}")
            ->line('Statut : Confirmée')
            ->line('Total : '.number_format((float) $order->total_ttc, 2, ',', ' ').' €')
            ->line(' ')
            ->line('Produits :');

        foreach ($order->items as $item) {
            $name = $item->product?->name ?? 'Produit';
            $qty = (int) $item->quantity;
            $lineTotal = number_format((float) $item->total, 2, ',', ' ').' €';
            $mail->line("• {$name} ×{$qty} — {$lineTotal}");
        }

        if ($order->shipment) {
            $methodLabel = ShippingCalculator::METHOD_LABELS[$order->shipment->method] ?? 'Standard';
            $shippingCost = (float) $order->shipment->cost;
            $shippingLine = $shippingCost > 0
                ? number_format($shippingCost, 2, ',', ' ').' €'
                : 'Gratuite';
            $mail->line(' ')
                ->line("Livraison ({$methodLabel}) : {$shippingLine}");
        }

        // Guest orders aren't tied to an account
        if ($order->user) {
            $mail->line(' ')
                ->line('Vous pouvez suivre l’évolution de votre commande depuis votre espace client.')
                ->action('Voir mes commandes', config('app.front_url').'/account/orders');
        }

        return $mail->salutation('— Gauthier Fitness');
    }
}
