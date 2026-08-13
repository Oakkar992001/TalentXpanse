<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FreelancerResume;
use App\Models\Job;
use App\Models\PortfolioItem;
use App\Models\Proposal;
use App\Services\MarketplaceHiringService;
use App\Services\MarketplaceNotificationService;
use App\Services\MarketplacePaymentService;
use App\Services\MarketplaceProductAnalyticsService;
use App\Services\MarketplaceUploadSafetyService;
use App\Services\ProposalCreditService;
use App\Services\TrustSummaryService;
use App\Support\MarketplaceStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProposalController extends Controller
{
    public function store(Request $request, Job $job, ProposalCreditService $credits, MarketplaceNotificationService $notifications, MarketplaceUploadSafetyService $safety, MarketplaceProductAnalyticsService $analytics)
    {
        abort_unless($request->user()->hasRole('freelancer'), 403, 'Add the Freelancer role before submitting a proposal.');
        abort_unless($job->status === 'open', 422, 'This job is no longer accepting proposals.');

        $minimumBid = max(1000, (int) ($job->budget_min ?? 0));
        $bidRules = ['required', 'integer', "min:{$minimumBid}"];

        if ($job->budget_max !== null) {
            $bidRules[] = "max:{$job->budget_max}";
        }

        $data = $request->validate([
            'cover_letter' => ['required', 'string', 'min:40', 'max:4000'],
            'bid_amount' => $bidRules,
            'delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'portfolio_item_ids' => ['nullable', 'array', 'max:3'],
            'portfolio_item_ids.*' => ['integer', 'distinct'],
            'attach_resume' => ['nullable', 'boolean'],
            'proposal_resume' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $uploadedResume = $request->file('proposal_resume');
        if ($uploadedResume) {
            $safety->inspect($uploadedResume, 'proposal_resume');
        }
        $proposalResumePath = null;

        try {
            $proposal = DB::transaction(function () use ($request, $job, $data, $credits, $uploadedResume, &$proposalResumePath) {
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
                abort_if(($data['attach_resume'] ?? false) && ! $resume && ! $uploadedResume, 422, 'Upload a PDF CV before attaching it to a proposal.');

                if ($uploadedResume) {
                    $proposalResumePath = $uploadedResume->store("proposal-resumes/{$request->user()->id}", MarketplaceStorage::privateDisk());
                } elseif ($resume) {
                    $sourceDisk = Storage::disk(MarketplaceStorage::privateDisk())->exists($resume->storage_path)
                        ? MarketplaceStorage::privateDisk()
                        : MarketplaceStorage::publicDisk();
                    abort_unless(Storage::disk($sourceDisk)->exists($resume->storage_path), 422, 'Your saved CV is unavailable. Upload it again before attaching it to a proposal.');

                    $proposalResumePath = "proposal-resumes/{$request->user()->id}/".Str::uuid().'.pdf';
                    Storage::disk(MarketplaceStorage::privateDisk())->put($proposalResumePath, Storage::disk($sourceDisk)->get($resume->storage_path));
                }

                $proposal = Proposal::create(Arr::only($data, ['cover_letter', 'bid_amount', 'delivery_days']) + [
                    'job_id' => $job->id,
                    'freelancer_id' => $request->user()->id,
                    'resume_path' => $proposalResumePath,
                    'resume_name' => $uploadedResume ? $uploadedResume->getClientOriginalName() : $resume?->original_name,
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
        } catch (Throwable $exception) {
            if ($proposalResumePath) {
                Storage::disk(MarketplaceStorage::privateDisk())->delete($proposalResumePath);
            }

            throw $exception;
        }
        $notifications->send($job->client_id, 'proposal_received', 'New proposal received', "{$request->user()->name} applied for {$job->title}.", "/jobs/{$job->id}");
        $analytics->track($request->user(), 'proposal_submitted', ['job_id' => $job->id, 'credit_cost' => $proposal->credit_cost]);

        return response()->json([
            'data' => $proposal->fresh(['job', 'workSamples']),
            'proposal_credits' => $credits->summaryFor($request->user()),
        ], 201);
    }

    public function mine(Request $request)
    {
        abort_unless($request->user()->hasRole('freelancer'), 403);

        return ['data' => $request->user()->proposals()->with(['job.client.clientProfile', 'workSamples', 'latestOffer'])->latest()->get()];
    }

    public function mineForJob(Request $request, Job $job)
    {
        abort_unless($request->user()->hasRole('freelancer'), 403);

        return [
            'data' => $request->user()->proposals()
                ->where('job_id', $job->id)
                ->with(['job', 'workSamples', 'latestOffer'])
                ->first(),
        ];
    }

    public function forJob(Request $request, Job $job, TrustSummaryService $trust)
    {
        abort_unless($job->client_id === $request->user()->id, 403, 'Only the job owner can view proposals.');

        $proposals = $job->proposals()->with(['freelancer.freelancerProfile', 'workSamples', 'latestOffer'])->latest()->get();
        $proposals->each(fn (Proposal $proposal) => $proposal->freelancer?->setAttribute('trust_summary', $trust->for($proposal->freelancer)));

        return ['data' => $proposals];
    }

    public function updateStatus(Request $request, Proposal $proposal, MarketplaceNotificationService $notifications, MarketplaceHiringService $hiring, MarketplacePaymentService $payments)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['shortlisted', 'interviewing', 'declined', 'hired', 'withdrawn'])],
            'client_note' => ['nullable', 'string', 'max:2000'],
            'decline_reason' => ['nullable', 'string', 'max:180'],
            'interview_at' => ['nullable', 'date'],
        ]);

        if ($data['status'] === 'withdrawn') {
            abort_unless($proposal->freelancer_id === $request->user()->id, 403, 'Only the freelancer can withdraw this proposal.');
            abort_unless(in_array($proposal->status, ['submitted', 'shortlisted', 'interviewing'], true), 422, 'Only an active proposal can be withdrawn. Decline a pending offer instead.');
            $proposal->update(['status' => 'withdrawn']);
            $notifications->send($proposal->job->client_id, 'proposal_withdrawn', 'Proposal withdrawn', "{$request->user()->name} withdrew their proposal for {$proposal->job->title}.", "/jobs/{$proposal->job_id}");

            return ['data' => $proposal->fresh('job')];
        }

        abort_unless($proposal->job->client_id === $request->user()->id, 403, 'Only the job owner can manage proposals.');

        if ($data['status'] === 'hired') {
            return $this->hire($proposal, $notifications, $hiring, $payments);
        }

        abort_if($proposal->job->status !== 'open', 422, 'This job is no longer accepting proposal decisions.');
        abort_if($proposal->status === 'hired', 422, 'A hired proposal cannot be changed.');
        abort_unless(in_array($proposal->status, ['submitted', 'shortlisted', 'interviewing'], true), 422, 'Only an active proposal can be updated.');
        abort_if($data['status'] === 'declined' && blank($data['decline_reason'] ?? null), 422, 'Add a short reason when declining a proposal.');
        $proposal->update([
            'status' => $data['status'],
            'client_note' => $data['client_note'] ?? $proposal->client_note,
            'decline_reason' => $data['status'] === 'declined' ? $data['decline_reason'] : null,
            'interview_at' => $data['status'] === 'interviewing' ? ($data['interview_at'] ?? $proposal->interview_at) : null,
        ]);
        $statusLabel = str_replace('_', ' ', $data['status']);
        $detail = $data['status'] === 'declined' ? " Reason: {$data['decline_reason']}" : '';
        $notifications->send($proposal->freelancer_id, "proposal_{$data['status']}", "Proposal {$statusLabel}", "Your proposal for {$proposal->job->title} is now {$statusLabel}.{$detail}", "/jobs/{$proposal->job_id}");

        return ['data' => $proposal->fresh('job')];
    }

    private function hire(Proposal $proposal, MarketplaceNotificationService $notifications, MarketplaceHiringService $hiring, MarketplacePaymentService $payments): array
    {
        $hiring->startContract($proposal, $notifications, $payments);

        return ['data' => $proposal->fresh('job')];
    }
}
