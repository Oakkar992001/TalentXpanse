<?php

namespace App\Services;

use App\Events\MarketplaceNotificationCreated;
use App\Models\MarketplaceNotification;
use App\Models\User;
use App\Notifications\MarketplaceActivityEmail;

class MarketplaceNotificationService
{
    public function send(User|int $user, string $type, string $title, ?string $body = null, ?string $url = null): ?MarketplaceNotification
    {
        $recipient = $user instanceof User ? $user : User::find($user);
        if (! $recipient || ! $this->isEnabled($recipient, $type)) {
            return null;
        }

        $notification = MarketplaceNotification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);
        MarketplaceNotificationCreated::dispatch($notification);

        if ($this->emailEnabled($recipient, $type)) {
            $recipient->notify(new MarketplaceActivityEmail($title, $body, $url));
        }

        return $notification;
    }

    private function isEnabled(User $user, string $type): bool
    {
        $category = $this->category($type);
        $preferences = array_replace(['messages' => true, 'proposals' => true, 'projects' => true, 'job_alerts' => true, 'email_updates' => false], $user->notification_preferences ?: []);

        return $preferences[$category];
    }

    private function emailEnabled(User $user, string $type): bool
    {
        $preferences = array_replace(['email_updates' => false], $user->notification_preferences ?: []);

        return config('marketplace_notifications.email_enabled') && $preferences['email_updates'] && $this->isEnabled($user, $type);
    }

    private function category(string $type): string
    {
        if ($type === 'job_alert') return 'job_alerts';
        if (str_starts_with($type, 'message_')) return 'messages';
        if (str_starts_with($type, 'proposal_') || str_starts_with($type, 'freelancer_invite')) return 'proposals';

        return 'projects';
    }
}
