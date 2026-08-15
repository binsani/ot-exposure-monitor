<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExposureChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Alert $alert) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[OTEIM] '.$this->alert->title)
            ->error()
            ->line($this->alert->message)
            ->action('Review exposure', route('exposure.index', ['exposed' => 1]))
            ->line('Acknowledge and investigate this change as soon as practical.');
    }
}
