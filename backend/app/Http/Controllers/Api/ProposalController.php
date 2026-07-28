<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\FreelancerResume;
use App\Models\Job;
use App\Models\PortfolioItem;
use App\Models\Proposal;
use App\Services\MarketplaceNotificationService;
use App\Services\ProposalCreditService;
use App\Services\TrustSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProposalController extends Controller
{
    public function store(Request $request, Job $job, ProposalCreditService $credits, MarketplaceNotificationService $notifications)
    {
        abort_unless($request->user()->hasRole('freelancer'), 403, 'Add the Freelancer role before submitting a proposal.');
        abort_unless($job->status === 'open', 422, 'This job is no longer accepting proposals.');

        $data = $request->validate([
            'cover_letter' => ['required', 'string', 'min:40', 'max:4000'],
            'bid_amount' => ['required', 'integer', 'min:1000'],
            'delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'portfolio_item_ids' => ['nullable', 'array', 'max:3'],
            'portfolio_item_ids.*' => ['integer', 'distinct'],
            'attach_resume' => ['nullable', 'boolean'],
        ]);

        $proposal = DB::transaction(function () use ($request, $job, $data, $credits) {
            $existing = Proposal::query()
                ->where('job_id', $job->id)
                ->where('freelancer_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages(['proposal' => 'You have already submitted a proposal for this job.']);
            }

            $portfolioItems = PortfolioItem::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('id', $data['portfolio_item_ids'] ?? [])
                ->get();
            abort_if($portfolioItems->count() !== count($data['portfolio_item_ids'] ?? []), 422, 'One or more selected portfolio items are unavailable.');
            $resume = ($data['attach_resume'] ?? false)
                ? FreelancerResume::where('user_id', $request->user()->id)->first()
                : null;
            abort_if(($data['attach_resume'] ?? false) && ! $resume, 422, 'Upload a PDF CV before attaching it to a proposal.');

            $proposal = Proposal::create(Arr::only($data, ['cover_letter', 'bid_amount', 'delivery_days']) + [
                'job_id' => $job->id,
                'freelancer_id' => $request->user()->id,
                'resume_path' => $resume?->storage_path,
                'resume_name' => $resume?->original_name,
            ]);
            $proposal->workSamples()->createMany($portfolioItems->map(fn (PortfolioItem $item) => [
                'portfolio_item_id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'project_url' => $item->project_url,
                'image_url' => $item->image_url,
            ])->all());
            $cost = $credits->deductForProposal($request->user(), $job, $proposal);
            $proposal->update(['credit_cost' => $cost]);

            return $proposal;
        });
        $notifications->send($job->client_id, 'proposal_received', 'New proposal received', "{$request->user()->name} applied for {$job->title}.", "/jobs/{$job->id}");

        return response()->json([
            'data' => $proposal->fresh(['job', 'workSamples']),
            'proposal_credits' => $credits->summaryFor($request->user()),
        ], 201);
    }

    public function mine(Request $request)
    {
        abort_unless($request->user()->hasRole('freelancer'), 403);

        return ['data' => $request->user()->proposals()->with(['job.client.clientProfile', 'workSamples'])->latest()->get()];
    }

    public function forJob(Request $request, Job $job, TrustSummaryService $trust)
    {
        abort_unless($job->client_id === $request->user()->id, 403, 'Only the job owner can view proposals.');

        $proposals = $job->proposals()->with(['freelancer.freelancerProfile', 'workSamples'])->latest()->get();
        $proposals->each(fn (Proposal $proposal) => $proposal->freelancer?->setAttribute('trust_summary', $trust->for($proposal->freelancer)));

        return ['data' => $proposals];
    }

    public function updateStatus(Request $request, Proposal $proposal, MarketplaceNotificationService $notifications)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['shortlisted', 'declined', 'hired', 'withdrawn'])]]);

        if ($data['status'] === 'withdrawn') {
            abort_unless($proposal->freelancer_id === $request->user()->id, 403, 'Only the freelancer can withdraw this proposal.');
            abort_unless(in_array($proposal->status, ['submitted', 'shortlisted'], true), 422, 'Only an active proposal can be withdrawn.');
            $proposal->update(['status' => 'withdrawn']);
            $notifications->send($proposal->job->client_id, 'proposal_withdrawn', 'Proposal withdrawn', "{$request->user()->name} withdrew their proposal for {$proposal->job->title}.", "/jobs/{$proposal->job_id}");

            return ['data' => $proposal->fresh('job')];
        }

        abort_unless($proposal->job->client_id === $request->user()->id, 403, 'Only the job owner can manage proposals.');

        if ($data['status'] === 'hired') {
            return $this->hire($proposal, $notifications);
        }

        abort_if($proposal->job->status !== 'open', 422, 'This job is no longer accepting proposal decisions.');
        abort_if($proposal->status === 'hired', 422, 'A hired proposal cannot be changed.');
        $proposal->update($data);
        $notifications->send($proposal->freelancer_id, "proposal_{$data['status']}", "Proposal {$data['status']}", "Your proposal for {$proposal->job->title} was {$data['status']}.", "/jobs/{$proposal->job_id}");

        return ['data' => $proposal->fresh('job')];
    }

    private function hire(Proposal $proposal, MarketplaceNotificationService $notifications): array
    {
        return DB::transaction(function () use ($proposal, $notifications) {
            $job = Job::query()->lockForUpdate()->findOrFail($proposal->job_id);
            abort_unless($job->status === 'open', 422, 'This job already has a hiring decision.');

            $selected = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless(in_array($selected->status, ['submitted', 'shortlisted'], true), 422, 'Only active proposals can be hired.');

            $job->update(['status' => 'in_progress']);
            $selected->update(['status' => 'hired']);
            $job->proposals()
                ->whereKeyNot($selected->id)
                ->whereIn('status', ['submitted', 'shortlisted'])
                ->update(['status' => 'declined']);
            $conversation = Conversation::updateOrCreate(['proposal_id' => $selected->id], [
                'job_id' => $job->id,
                'client_id' => $job->client_id,
                'freelancer_id' => $selected->freelancer_id,
                'type' => 'project',
            ]);
            $contract = Contract::updateOrCreate(['proposal_id' => $selected->id], [
                'job_id' => $job->id,
                'client_id' => $job->client_id,
                'freelancer_id' => $selected->freelancer_id,
                'title' => $job->title,
                'scope' => $job->description,
                'agreed_amount' => $selected->bid_amount,
                'status' => 'active',
                'started_at' => now(),
            ]);
            ConversationEvent::firstOrCreate([
                'conversation_id' => $conversation->id,
                'contract_id' => $contract->id,
                'type' => 'contract_started',
            ], ['body' => 'Contract started. The client can now create delivery milestones.']);
            $conversation->update(['last_message_at' => now()]);
            $notifications->send($selected->freelancer_id, 'proposal_hired', 'You were hired', "You were hired for {$job->title}. Set up the delivery milestones with your client.", "/projects/{$contract->id}");

            return ['data' => $selected->fresh('job')];
        });
    }
}
