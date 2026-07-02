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

    /**
     * Store the submitted contact form data.
     * @param array{name: string, email: string, subject: ?string, message: string} $data
     */
    public function __construct(public array $data)
    {
    }

    /** Build the envelope: subject line and reply-to set to the sender. */
    public function envelope(): Envelope
    {
        $subject = $this->data['subject'] ?? null;

        return new Envelope(
            subject: 'Contact GauthierFitness — ' . ($subject ?: 'Nouveau message'),
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
