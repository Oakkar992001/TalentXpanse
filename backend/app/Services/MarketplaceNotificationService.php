<?php

namespace App\Services;

use App\Models\MarketplaceNotification;
use App\Models\User;

class MarketplaceNotificationService
{
    public function send(User|int $user, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        $recipient = $user instanceof User ? $user : User::find($user);
        if (! $recipient || ! $this->isEnabled($recipient, $type)) {
            return;
        }

        MarketplaceNotification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);
    }

    private function isEnabled(User $user, string $type): bool
    {
        $category = str_starts_with($type, 'message_') ? 'messages' : (str_starts_with($type, 'proposal_') ? 'proposals' : 'projects');
        $preferences = array_replace(['messages' => true, 'proposals' => true, 'projects' => true], $user->notification_preferences ?: []);

        return $preferences[$category];
    }
}
