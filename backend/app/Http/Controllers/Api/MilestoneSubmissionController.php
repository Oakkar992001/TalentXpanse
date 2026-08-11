<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\MilestoneSubmissionFile;
use App\Services\MarketplaceNotificationService;
use App\Services\MilestoneSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MilestoneSubmissionController extends Controller
{
    public function store(Request $request, ContractMilestone $milestone, MilestoneSubmissionService $submissions, MarketplaceNotificationService $notifications)
    {
        $contract = $milestone->contract;
        abort_unless($contract->freelancer_id === $request->user()->id, 403, 'Only the freelancer can submit delivery work.');
        abort_unless($contract->status === 'active', 422, 'This contract is no longer active.');
        abort_unless(in_array($milestone->status, ['planned', 'in_progress', 'revision_requested'], true), 422, 'This milestone cannot be submitted now.');
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:4000'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,txt,csv,jpg,jpeg,png,webp'],
        ]);
        abort_if(blank($data['note'] ?? null) && ! $request->hasFile('files'), 422, 'Add delivery notes or at least one delivery file.');

        $submission = $submissions->submit($milestone, $request->user(), $data['note'] ?? null, $request->file('files', []));
        $this->event($contract, "Delivery submitted for review: {$milestone->title} (version {$submission->version})");
        $notifications->send($contract->client_id, 'milestone_submitted', 'Milestone ready for review', "{$milestone->title} delivery version {$submission->version} was submitted for your review.", "/projects/{$contract->id}?milestone={$milestone->id}&focus=milestone");

        return response()->json(['data' => $submission], 201);
    }

    public function download(Request $request, MilestoneSubmissionFile $file)
    {
        $file->load('submission.milestone.contract');
        $contract = $file->submission?->milestone?->contract;
        abort_unless($contract && in_array($request->user()->id, [$contract->client_id, $contract->freelancer_id], true), 403, 'You are not part of this project.');
        abort_unless(Storage::disk('local')->exists($file->storage_path), 404, 'This delivery file is no longer available.');

        return Storage::disk('local')->download($file->storage_path, $file->original_name);
    }

    private function event(Contract $contract, string $body): void
    {
        $conversation = Conversation::where('proposal_id', $contract->proposal_id)->first();
        if (! $conversation) {
            return;
        }

        ConversationEvent::create(['conversation_id' => $conversation->id, 'contract_id' => $contract->id, 'type' => 'milestone_delivery_submitted', 'body' => $body]);
        $conversation->update(['last_message_at' => now()]);
    }
}
