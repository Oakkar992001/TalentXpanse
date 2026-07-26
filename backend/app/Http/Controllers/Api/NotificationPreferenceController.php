<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request)
    {
        return ['data' => $this->preferences($request->user()->notification_preferences)];
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'messages' => ['required', 'boolean'],
            'proposals' => ['required', 'boolean'],
            'projects' => ['required', 'boolean'],
        ]);
        $request->user()->update(['notification_preferences' => $data]);

        return ['data' => $this->preferences($data)];
    }

    private function preferences(?array $preferences): array
    {
        return array_replace(['messages' => true, 'proposals' => true, 'projects' => true], $preferences ?: []);
    }
}
