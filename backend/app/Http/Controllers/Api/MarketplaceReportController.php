<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\FreelancerProfile;
use App\Models\Job;
use App\Models\MarketplaceReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceReportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['job', 'freelancer', 'message'])],
            'target_id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(['spam', 'fraud', 'abuse', 'inappropriate_content', 'other'])],
            'details' => ['nullable', 'string', 'max:1500'],
        ]);

        $this->targetExists($request, $data['target_type'], $data['target_id']);
        $report = MarketplaceReport::firstOrCreate([
            'reporter_id' => $request->user()->id,
            'target_type' => $data['target_type'],
            'target_id' => $data['target_id'],
        ], ['reason' => $data['reason'], 'details' => $data['details'] ?? null]);

        return response()->json(['data' => $report, 'created' => $report->wasRecentlyCreated], $report->wasRecentlyCreated ? 201 : 200);
    }

    private function targetExists(Request $request, string $type, int $id): void
    {
        $target = match ($type) {
            'job' => Job::find($id),
            'freelancer' => FreelancerProfile::find($id),
            'message' => ConversationMessage::with('conversation')->find($id),
        };
        abort_unless($target, 404, 'The item you are trying to report no longer exists.');
        $ownerId = match ($type) {
            'job' => $target->client_id,
            'freelancer' => $target->user_id,
            'message' => $target->sender_id,
        };
        abort_if($ownerId === $request->user()->id, 422, 'You cannot report your own content.');
        if ($type === 'message') {
            abort_unless(in_array($request->user()->id, [$target->conversation?->client_id, $target->conversation?->freelancer_id], true), 403, 'You can only report messages from your own conversations.');
        }
    }
}
