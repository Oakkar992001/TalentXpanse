<?php

namespace App\Services;

use App\Models\MarketplaceNotification;
use App\Models\User;

class MarketplaceNotificationService
{
    public function send(User|int $user, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        MarketplaceNotification::create([
            'user_id' => $user instanceof User ? $user->id : $user,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);
    }
}
