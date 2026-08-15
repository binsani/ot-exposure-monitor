<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyAdvisoryDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $matches) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('[OTEIM] Weekly advisory digest')
            ->line(count($this->matches).' new advisory '.str('match')->plural(count($this->matches)).' affected your inventory this week.');
        foreach (array_slice($this->matches, 0, 10) as $match) {
            $mail->line(strtoupper($match['severity']).': '.$match['advisory'].' - '.$match['asset']);
        }
        if (count($this->matches) > 10) {
            $mail->line('And '.(count($this->matches) - 10).' more matches.');
        }

        return $mail->action('Review relevant advisories', route('advisories.index'))
            ->line('This digest includes only advisories matched to assets in your inventory.');
    }
}
