<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketplaceMessageCreated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $conversationId, public int $recipientId)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("marketplace.user.{$this->recipientId}")];
    }

    public function broadcastAs(): string
    {
        return 'marketplace.message.created';
    }

    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId];
    }
}
