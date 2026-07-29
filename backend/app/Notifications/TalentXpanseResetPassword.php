<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class TalentXpanseResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct($token)
    {
        parent::__construct($token);
        $this->afterCommit();
    }

    public function toMail($notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/').'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Reset your TalentXpanse password')
            ->line('We received a request to reset the password for your TalentXpanse account.')
            ->action('Reset password', $url)
            ->line('This link expires in 60 minutes. If you did not request a reset, no action is needed.');
    }
}
