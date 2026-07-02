<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /** Bind the password reset token to this notification. */
    public function __construct(public string $token) {}

    /** Deliver this notification by mail only. */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /** Build the password reset email with a link valid for 60 minutes. */
    public function toMail($notifiable): MailMessage
    {
        $url = config('app.front_url').'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe — Gauthier Fitness')
            ->replyTo(config('mail.support_address') ?? config('mail.from.address'))
            ->greeting("Bonjour {$notifiable->firstname},")
            ->line('Vous avez demandé la réinitialisation de votre mot de passe.')
            ->action('Réinitialiser mon mot de passe', $url)
            ->line('Ce lien expire dans 60 minutes.')
            ->line("Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.")
            ->salutation('— Gauthier Fitness');
    }
}
