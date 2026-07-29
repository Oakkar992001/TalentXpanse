<?php

namespace App\Events;

use App\Models\MarketplaceNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketplaceNotificationCreated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MarketplaceNotification $notification)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("marketplace.user.{$this->notification->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'marketplace.notification.created';
    }

    public function broadcastWith(): array
    {
        return ['notification' => [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'url' => $this->notification->url,
            'read_at' => $this->notification->read_at?->toISOString(),
            'created_at' => $this->notification->created_at?->toISOString(),
        ]];
    }
}
