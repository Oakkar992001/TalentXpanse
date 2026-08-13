<?php

namespace App\Services;

use App\Models\MarketplaceProductEvent;
use App\Models\User;

class MarketplaceProductAnalyticsService
{
    /**
     * Product events intentionally keep only small operational metadata. Never send
     * message bodies, CV data, identity documents, credentials, or payment data.
     */
    public function track(?User $user, string $event, array $properties = []): void
    {
        MarketplaceProductEvent::create([
            'user_id' => $user?->id,
            'event' => $event,
            'properties' => $properties ?: null,
            'occurred_at' => now(),
        ]);
    }
}
