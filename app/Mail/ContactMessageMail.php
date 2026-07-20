<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    // French labels for each contact reason
    public const array REASON_LABELS = [
        'order' => 'Commande',
        'delivery' => 'Livraison',
        'payment' => 'Paiement',
        'customization' => 'Personnalisation',
        'other' => 'Autre',
    ];

    /**
     * Store the submitted contact form data.
     * @param  array{name: string, email: string, reason: string, subject: ?string, message: string}  $data
     */
    public function __construct(public array $data) {}

    // Build the envelope
    public function envelope(): Envelope
    {
        $subject = $this->data['subject'] ?? null;
        $reason_label = self::REASON_LABELS[$this->data['reason']] ?? 'Autre';

        return new Envelope(
            replyTo: [new Address($this->data['email'], $this->data['name'])],
            subject: "Contact GauthierFitness — [$reason_label] ".($subject ?: 'Nouveau message'),
        );
    }

    // Render contact message
    public function content(): Content
    {
        return new Content(
            view: 'emails.contact',
            with: ['data' => $this->data],
        );
    }
}
