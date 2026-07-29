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
            'messages' => ['sometimes', 'boolean'],
            'proposals' => ['sometimes', 'boolean'],
            'projects' => ['sometimes', 'boolean'],
            'job_alerts' => ['sometimes', 'boolean'],
            'email_updates' => ['sometimes', 'boolean'],
        ]);
        $data = array_replace($this->preferences($request->user()->notification_preferences), $data);
        $request->user()->update(['notification_preferences' => $data]);

        return ['data' => $this->preferences($data)];
    }

    private function preferences(?array $preferences): array
    {
        return array_replace(['messages' => true, 'proposals' => true, 'projects' => true, 'job_alerts' => true, 'email_updates' => false], $preferences ?: []);
    }
}
