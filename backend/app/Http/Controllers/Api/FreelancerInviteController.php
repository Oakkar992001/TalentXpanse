<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\MarketplaceFreelancerInvite;
use App\Models\User;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FreelancerInviteController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('freelancer'), 403, 'Add the Freelancer role to view invitations.');

        return ['data' => MarketplaceFreelancerInvite::query()
            ->where('freelancer_id', $request->user()->id)
            ->with(['job.client.clientProfile', 'client'])
            ->latest()
            ->get()];
    }

    public function store(Request $request, Job $job, MarketplaceNotificationService $notifications)
    {
        abort_unless($job->client_id === $request->user()->id, 403, 'Only the job owner can invite a freelancer.');
        abort_unless($job->status === 'open', 422, 'Only an open job can send invitations.');
        $data = $request->validate([
            'freelancer_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
        $freelancer = User::query()->with('freelancerProfile')->findOrFail($data['freelancer_id']);
        abort_unless($freelancer->hasRole('freelancer') && $freelancer->freelancerProfile, 422, 'This person does not have an available freelancer profile.');
        abort_if($freelancer->id === $request->user()->id, 422, 'You cannot invite yourself.');

        $invite = MarketplaceFreelancerInvite::firstOrNew(['job_id' => $job->id, 'freelancer_id' => $freelancer->id]);
        abort_if($invite->exists && $invite->status === 'accepted', 422, 'This freelancer has already accepted the invitation.');
        $invite->fill([
            'client_id' => $request->user()->id,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
            'responded_at' => null,
        ])->save();

        $notifications->send($freelancer, 'freelancer_invited', 'You were invited to apply', "{$request->user()->name} invited you to apply for {$job->title}.", "/search/jobs/{$job->id}");

        return response()->json(['data' => $invite->fresh(['job', 'client', 'freelancer'])], $invite->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, MarketplaceFreelancerInvite $invite, MarketplaceNotificationService $notifications)
    {
        abort_unless($invite->freelancer_id === $request->user()->id, 403, 'Only the invited freelancer can respond.');
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'declined'])]]);
        abort_unless($invite->status === 'pending', 422, 'This invitation has already been handled.');

        $invite->update($data + ['responded_at' => now()]);
        $verb = $data['status'] === 'accepted' ? 'accepted' : 'declined';
        $notifications->send($invite->client_id, 'freelancer_invite_updated', 'Invitation updated', "{$request->user()->name} {$verb} your invitation for {$invite->job->title}.", "/search/jobs/{$invite->job_id}");

        return ['data' => $invite->fresh(['job', 'client', 'freelancer'])];
    }
}
