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

    /** French labels for each contact reason, used in the subject line and the email body. */
    public const REASON_LABELS = [
        'order' => 'Commande',
        'delivery' => 'Livraison',
        'payment' => 'Paiement',
        'customization' => 'Personnalisation',
        'other' => 'Autre',
    ];

    /**
     * Store the submitted contact form data.
     *
     * @param  array{name: string, email: string, reason: string, subject: ?string, message: string}  $data
     */
    public function __construct(public array $data) {}

    /** Build the envelope: subject line (prefixed with the reason) and reply-to set to the sender. */
    public function envelope(): Envelope
    {
        $subject = $this->data['subject'] ?? null;
        $reasonLabel = self::REASON_LABELS[$this->data['reason']] ?? 'Autre';

        return new Envelope(
            subject: "Contact GauthierFitness — [{$reasonLabel}] ".($subject ?: 'Nouveau message'),
            replyTo: [new Address($this->data['email'], $this->data['name'])],
        );
    }

    /** Render the contact message using the "emails.contact" view. */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contact',
            with: ['data' => $this->data],
        );
    }
}
