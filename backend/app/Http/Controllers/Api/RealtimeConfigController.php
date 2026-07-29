<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RealtimeConfigController extends Controller
{
    public function show(Request $request): array
    {
        $connection = config('broadcasting.connections.reverb');
        abort_unless(filled($connection['key'] ?? null), 503, 'Realtime updates are not configured.');

        return ['data' => [
            'key' => $connection['key'],
            'host' => $connection['options']['host'] ?? null,
            'port' => (int) ($connection['options']['port'] ?? 8080),
            'scheme' => $connection['options']['scheme'] ?? 'https',
        ]];
    }
}
