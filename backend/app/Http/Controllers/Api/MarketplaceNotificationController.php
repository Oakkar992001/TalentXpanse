<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceNotification;
use Illuminate\Http\Request;

class MarketplaceNotificationController extends Controller
{
    public function index(Request $request)
    {
        return ['data' => $request->user()->marketplaceNotifications()->latest()->take(60)->get()];
    }

    public function summary(Request $request)
    {
        return ['data' => ['unread_count' => $request->user()->marketplaceNotifications()->whereNull('read_at')->count()]];
    }

    public function markRead(Request $request, MarketplaceNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return ['data' => $notification->fresh()];
    }

    public function markAllRead(Request $request)
    {
        $request->user()->marketplaceNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return ['data' => ['success' => true]];
    }
}
