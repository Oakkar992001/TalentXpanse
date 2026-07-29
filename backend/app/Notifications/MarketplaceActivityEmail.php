<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceActivityEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly ?string $body,
        private readonly ?string $path,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->title)->greeting("Hello {$notifiable->name},");
        if ($this->body) $message->line($this->body);
        if ($this->path) $message->action('Open TalentXpanse', rtrim(config('app.frontend_url', config('app.url')), '/').$this->path);

        return $message->line('You can adjust marketplace alerts in Settings.');
    }
}
