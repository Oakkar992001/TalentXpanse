<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use App\Models\Contract;
use App\Models\ContractSupportRequest;
use App\Models\ConversationMessage;
use App\Models\FreelancerProfile;
use App\Models\IdentityVerificationSubmission;
use App\Models\Job;
use App\Models\MarketplaceAdminAuditLog;
use App\Models\MarketplaceFeedback;
use App\Models\MarketplacePaymentRecord;
use App\Models\MarketplaceProductEvent;
use App\Models\MarketplaceReliabilityAppeal;
use App\Models\MarketplaceReliabilityEvent;
use App\Models\MarketplaceReport;
use App\Models\Proposal;
use App\Models\User;
use App\Services\MarketplaceAdminAuditService;
use App\Services\MarketplaceEscrowService;
use App\Services\MarketplaceNotificationService;
use App\Services\MarketplacePaymentSafetyService;
use App\Services\MarketplacePaymentService;
use App\Services\MarketplaceReliabilityService;
use App\Services\ProposalCreditService;
use App\Support\MarketplaceStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->ensureAdmin($request);

        return ['data' => [
            'users' => User::count(),
            'suspended_users' => User::where('status', 'suspended')->count(),
            'pending_identity_verifications' => IdentityVerificationSubmission::where('status', 'pending')->count(),
            'pending_company_verifications' => ClientProfile::where('company_verification_status', 'pending')->count(),
            'open_jobs' => Job::where('status', 'open')->count(),
            'proposals' => Proposal::count(),
            'active_contracts' => Contract::where('status', 'active')->count(),
            'open_support_requests' => ContractSupportRequest::whereIn('status', ['open', 'under_review'])->count(),
            'open_reports' => MarketplaceReport::where('status', 'open')->count(),
            'payment_holds' => Contract::where('payment_hold_status', 'on_hold')->count(),
            'pending_reliability_cases' => MarketplaceReliabilityEvent::where('status', 'pending')->count(),
            'audit_entries' => MarketplaceAdminAuditLog::count(),
            'new_feedback' => MarketplaceFeedback::where('status', 'new')->count(),
            'open_appeals' => MarketplaceReliabilityAppeal::whereIn('status', ['open', 'under_review'])->count(),
            'funnel' => $this->funnelMetrics(),
        ]];
    }

    public function users(Request $request)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'suspended'])]]);
        $users = User::query()->with('roles')->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->latest()->paginate(20);
        $users->getCollection()->each(fn (User $user) => $user->makeVisible(['email', 'status']));

        return ['data' => $users];
    }

    public function updateUser(Request $request, User $user, MarketplaceAdminAuditService $audit)
    {
        $this->ensureAdmin($request);
        abort_if($user->id === $request->user()->id, 422, 'You cannot suspend your own administrator account.');
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $user->update($data);

        if ($user->status === 'suspended') {
            $user->tokens()->delete();
        }
        $audit->log($request->user(), 'user.status_updated', $user, "User status changed to {$user->status}.", ['status' => $user->status]);

        return ['data' => $user->fresh('roles')->makeVisible(['email', 'status'])];
    }

    public function jobs(Request $request)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:40']]);
        $jobs = Job::query()->with('client.clientProfile')->withCount('proposals')->when($data['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->latest()->paginate(20);

        return ['data' => $jobs];
    }

    public function updateJob(Request $request, Job $job, MarketplaceAdminAuditService $audit, ProposalCreditService $credits)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'paused', 'closed'])]]);
        abort_if(in_array($job->status, ['in_progress', 'completed'], true), 422, 'Contract jobs cannot be moderated from this action.');
        $shouldRefundCredits = $data['status'] === 'closed' && $job->status !== 'closed';
        $job->update($data);
        if ($shouldRefundCredits) {
            $credits->refundForCancelledJob($job);
        }
        $audit->log($request->user(), 'job.status_updated', $job, "Job status changed to {$job->status}.", ['status' => $job->status]);

        return ['data' => $job->fresh('client.clientProfile')];
    }

    public function reports(Request $request)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['status' => ['nullable', Rule::in(['open', 'reviewed', 'resolved', 'dismissed'])]]);
        $reports = MarketplaceReport::query()->with(['reporter', 'reviewer'])->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->latest()->paginate(20);
        $reports->getCollection()->each(fn (MarketplaceReport $report) => $report->setAttribute('target_preview', $this->targetPreview($report)));

        return ['data' => $reports];
    }

    public function updateReport(Request $request, MarketplaceReport $report, MarketplaceAdminAuditService $audit, MarketplaceReliabilityService $reliability)
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['reviewed', 'resolved', 'dismissed'])],
            'reliability_action' => ['nullable', Rule::in(['none', 'warning', 'serious_violation'])],
            'reliability_note' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_if(($data['reliability_action'] ?? 'none') !== 'none' && $data['status'] !== 'resolved', 422, 'Resolve the report before applying a reliability action.');
        $report->update(['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $metadata = ['status' => $report->status];
        if (($data['reliability_action'] ?? 'none') !== 'none') {
            [$user, $role] = $this->reliabilityTarget($report);
            abort_unless($user && $role, 422, 'The reported account is no longer available for a reliability decision.');
            $event = $reliability->recordReportAction($user, $role, $data['reliability_action'], $report->id, $data['reliability_note'] ?? $report->details, $request->user());
            $reliability->sync($user, $role);
            $metadata['reliability_event_id'] = $event->id;
            $metadata['reliability_action'] = $data['reliability_action'];
        }
        $audit->log($request->user(), 'report.status_updated', $report, "Report status changed to {$report->status}.", $metadata);

        return ['data' => $report->fresh(['reporter', 'reviewer'])];
    }

    public function supportRequests(Request $request)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['status' => ['nullable', Rule::in(['open', 'under_review', 'resolved', 'dismissed'])]]);
        $requests = ContractSupportRequest::query()
            ->with(['contract.client.clientProfile', 'contract.freelancer', 'opener', 'handler'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return ['data' => $requests];
    }

    public function updateSupportRequest(Request $request, ContractSupportRequest $supportRequest, MarketplaceNotificationService $notifications, MarketplaceAdminAuditService $audit)
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'resolved', 'dismissed'])],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_if(in_array($data['status'], ['resolved', 'dismissed'], true) && blank($data['resolution_note'] ?? null), 422, 'Add a short resolution note before closing a support request.');

        $supportRequest->update($data + ['handled_by' => $request->user()->id, 'handled_at' => now()]);
        $audit->log($request->user(), 'support_request.status_updated', $supportRequest, "Support request status changed to {$supportRequest->status}.", ['status' => $supportRequest->status]);
        $status = str_replace('_', ' ', $supportRequest->status);
        $notifications->send($supportRequest->opened_by, 'project_support_updated', 'Project support request updated', "Your support request for {$supportRequest->contract->title} is now {$status}.", "/projects/{$supportRequest->contract_id}");

        return ['data' => $supportRequest->fresh(['contract', 'opener', 'handler'])];
    }

    public function paymentRecords(Request $request)
    {
        $this->ensureAdmin($request);

        return ['data' => [
            'payments_enabled' => config('marketplace_payments.enabled'),
            'gateway_configured' => app(MarketplacePaymentService::class)->gatewayConfigured(),
            'records' => MarketplacePaymentRecord::query()->with(['contract', 'milestone', 'client', 'freelancer', 'ledgerEntries'])->latest()->paginate(20),
            'on_hold_contracts' => Contract::query()
                ->where('payment_hold_status', 'on_hold')
                ->with(['client.clientProfile', 'freelancer', 'paymentHoldHandler'])
                ->latest('payment_hold_at')
                ->get(),
        ]];
    }

    public function auditLogs(Request $request)
    {
        $this->ensureAdmin($request);

        $logs = MarketplaceAdminAuditLog::query()->with('administrator')->latest('created_at')->latest('id')->paginate(30);
        $logs->getCollection()->each(fn (MarketplaceAdminAuditLog $log) => $log->administrator?->makeVisible(['email']));

        return ['data' => $logs];
    }

    public function verifications(Request $request)
    {
        $this->ensureAdmin($request);

        $identity = IdentityVerificationSubmission::query()->where('status', 'pending')->with('user.roles')->latest('submitted_at')->get();
        $identity->each(fn (IdentityVerificationSubmission $submission) => $submission->user?->makeVisible(['email']));
        $companies = ClientProfile::query()->where('company_verification_status', 'pending')->with('user')->latest('company_verification_requested_at')->get();
        $companies->each(fn (ClientProfile $profile) => $profile->user?->makeVisible(['email']));

        return ['data' => [
            'identity' => $identity,
            'companies' => $companies,
        ]];
    }

    public function updateIdentityVerification(Request $request, User $user, MarketplaceNotificationService $notifications, MarketplaceAdminAuditService $audit, MarketplaceReliabilityService $reliability)
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $submission = $user->identityVerificationSubmissions()->where('status', 'pending')->latest('submitted_at')->first();
        abort_unless($submission, 422, 'This identity verification is not awaiting review.');
        abort_if($data['status'] === 'rejected' && blank($data['note'] ?? null), 422, 'Add a clear reason when rejecting a verification request.');
        $user->update([
            'identity_verification_status' => $data['status'],
            'identity_verification_note' => $data['note'] ?? null,
            'identity_verified_at' => $data['status'] === 'verified' ? now() : null,
            'identity_verified_by' => $request->user()->id,
        ]);
        $submission->update([
            'status' => $data['status'],
            'review_note' => $data['note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        $this->purgeIdentityDocuments($submission);
        if ($data['status'] === 'verified') {
            $user->roles()->pluck('name')->filter(fn (string $role) => in_array($role, ['client', 'freelancer'], true))->each(fn (string $role) => $reliability->recordVerification($user, $role, 'identity', $user->id));
        }
        $audit->log($request->user(), "identity_verification.{$data['status']}", $user, $data['note'] ?? "Identity verification {$data['status']}.", ['status' => $data['status'], 'identity_verification_submission_id' => $submission->id]);
        $notifications->send($user, 'identity_verification_updated', 'Identity verification updated', $data['status'] === 'verified' ? 'Your identity verification is complete.' : 'Your identity verification request needs attention. Review the note in Settings.', '/settings');

        return ['data' => $user->fresh('roles')->makeVisible([
            'email',
            'identity_verification_status',
            'identity_verification_note',
            'identity_verification_requested_at',
            'identity_verified_at',
            'identity_verified_by',
        ])];
    }

    public function downloadIdentityVerificationDocument(Request $request, IdentityVerificationSubmission $identityVerificationSubmission, string $side, MarketplaceAdminAuditService $audit)
    {
        $this->ensureAdmin($request);
        abort_unless(in_array($side, ['front', 'back'], true), 404);
        abort_unless($identityVerificationSubmission->status === 'pending' && ! $identityVerificationSubmission->documents_purged_at, 404, 'This identity document is no longer available.');

        $path = $side === 'front' ? $identityVerificationSubmission->nrc_front_path : $identityVerificationSubmission->nrc_back_path;
        $disk = Storage::disk(MarketplaceStorage::privateDisk());
        abort_unless($path && $disk->exists($path), 404, 'This identity document is no longer available.');

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $audit->log($request->user(), 'identity_verification.document_accessed', $identityVerificationSubmission, "Opened identity document {$side} for review.", ['side' => $side]);

        return $disk->response($path, "identity-document-{$side}.{$extension}", [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateCompanyVerification(Request $request, ClientProfile $clientProfile, MarketplaceNotificationService $notifications, MarketplaceAdminAuditService $audit, MarketplaceReliabilityService $reliability)
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($clientProfile->company_verification_status === 'pending', 422, 'This company verification is not awaiting review.');
        abort_if($data['status'] === 'rejected' && blank($data['note'] ?? null), 422, 'Add a clear reason when rejecting a verification request.');
        $clientProfile->update([
            'company_verification_status' => $data['status'],
            'company_verification_note' => $data['note'] ?? null,
            'company_verified_at' => $data['status'] === 'verified' ? now() : null,
            'company_verified_by' => $request->user()->id,
        ]);
        if ($data['status'] === 'verified') {
            $reliability->recordVerification($clientProfile->user, 'client', 'company', $clientProfile->id);
        }
        $audit->log($request->user(), "company_verification.{$data['status']}", $clientProfile, $data['note'] ?? "Company verification {$data['status']}.", ['status' => $data['status']]);
        $notifications->send($clientProfile->user_id, 'company_verification_updated', 'Company verification updated', $data['status'] === 'verified' ? 'Your company verification is complete.' : 'Your company verification request needs attention. Review the note in Settings.', '/settings');

        $profile = $clientProfile->fresh('user')->makeVisible([
            'billing_verified',
            'company_verification_note',
            'company_verification_requested_at',
            'company_verified_at',
            'company_verified_by',
        ]);
        $profile->user?->makeVisible(['email']);

        return ['data' => $profile];
    }

    public function updatePaymentHold(Request $request, Contract $contract, MarketplacePaymentSafetyService $paymentSafety, MarketplaceEscrowService $escrow, MarketplaceNotificationService $notifications, MarketplaceAdminAuditService $audit)
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['on_hold', 'clear'])],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        abort_if($data['status'] === 'clear' && $contract->supportRequests()->where('reason', 'payment_issue')->whereIn('status', ['open', 'under_review'])->exists(), 422, 'Resolve or dismiss the active payment support request before clearing this hold.');
        $updated = $data['status'] === 'on_hold'
            ? $paymentSafety->placeHold($contract, $request->user(), $data['note'])
            : $paymentSafety->clearHold($contract, $request->user(), $data['note']);
        if ($updated->payment_hold_status === 'clear') {
            $escrow->resumeDisputedFunds($updated, $request->user(), $data['note']);
        }
        $title = $updated->payment_hold_status === 'on_hold' ? 'Payment safety hold active' : 'Payment safety hold cleared';
        $message = $updated->payment_hold_status === 'on_hold'
            ? "TalentXpanse placed a payment safety hold on {$updated->title}."
            : "TalentXpanse cleared the payment safety hold on {$updated->title}.";
        $notifications->send($updated->client_id, 'payment_hold_updated', $title, $message, "/projects/{$updated->id}");
        $notifications->send($updated->freelancer_id, 'payment_hold_updated', $title, $message, "/projects/{$updated->id}");
        $audit->log($request->user(), "payment_hold.{$updated->payment_hold_status}", $updated, $data['note'], ['status' => $updated->payment_hold_status]);

        return ['data' => $updated->load(['client', 'freelancer', 'paymentHoldHandler'])];
    }

    public function reliability(Request $request)
    {
        $this->ensureAdmin($request);

        return ['data' => [
            'pending' => MarketplaceReliabilityEvent::query()->where('status', 'pending')->with(['user.roles'])->latest()->take(50)->get(),
            'recent' => MarketplaceReliabilityEvent::query()->where('status', 'confirmed')->with(['user.roles', 'reviewer'])->latest()->take(50)->get(),
            'metrics' => [
                'pending' => MarketplaceReliabilityEvent::where('status', 'pending')->count(),
                'reduced_reach' => \App\Models\MarketplaceReliabilityProfile::where('search_visibility', 'reduced')->count(),
                'limited_reach' => \App\Models\MarketplaceReliabilityProfile::where('search_visibility', 'limited')->count(),
            ],
        ]];
    }

    public function feedback(Request $request)
    {
        $this->ensureAdmin($request);

        return ['data' => MarketplaceFeedback::query()->with(['user', 'reviewer'])->latest()->paginate(30)];
    }

    public function updateFeedback(Request $request, MarketplaceFeedback $feedback, MarketplaceAdminAuditService $audit)
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'reviewed', 'planned', 'resolved'])],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $feedback->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $audit->log($request->user(), "feedback.{$feedback->status}", $feedback, $data['resolution_note'] ?? 'Feedback status updated.', ['status' => $feedback->status]);

        return ['data' => $feedback->fresh(['user', 'reviewer'])];
    }

    public function appeals(Request $request)
    {
        $this->ensureAdmin($request);

        return ['data' => MarketplaceReliabilityAppeal::query()->with(['user.roles', 'reliabilityEvent', 'reviewer'])->latest()->paginate(30)];
    }

    public function updateAppeal(Request $request, MarketplaceReliabilityAppeal $appeal, MarketplaceNotificationService $notifications, MarketplaceAdminAuditService $audit)
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'upheld', 'adjusted', 'dismissed'])],
            'resolution_note' => ['required_if:status,upheld,adjusted,dismissed', 'nullable', 'string', 'max:2000'],
        ]);
        $appeal->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $audit->log($request->user(), "reliability_appeal.{$appeal->status}", $appeal, $data['resolution_note'] ?? 'Appeal status updated.', ['status' => $appeal->status]);
        if (in_array($appeal->status, ['upheld', 'adjusted', 'dismissed'], true)) {
            $notifications->send($appeal->user, 'reliability_appeal_reviewed', 'Reliability appeal reviewed', $data['resolution_note'] ?: 'TalentXpanse completed the appeal review.', '/settings/reliability');
        }

        return ['data' => $appeal->fresh(['user', 'reliabilityEvent', 'reviewer'])];
    }

    public function updateReliabilityEvent(Request $request, MarketplaceReliabilityEvent $reliabilityEvent, MarketplaceReliabilityService $reliability, MarketplaceNotificationService $notifications, MarketplaceAdminAuditService $audit)
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'dismissed'])],
            'resolution_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        abort_unless($reliabilityEvent->status === 'pending', 422, 'This reliability case has already been reviewed.');

        $updated = $reliability->resolve($reliabilityEvent, $data['status'], $request->user(), $data['resolution_note']);
        $audit->log($request->user(), "reliability_event.{$updated->status}", $updated, $data['resolution_note'], ['points' => $updated->points, 'event_type' => $updated->event_type]);
        $notifications->send($updated->user, 'reliability_reviewed', 'Reliability review completed', $updated->status === 'confirmed' ? 'TalentXpanse completed a reliability review. Check Settings to understand your current marketplace status and recovery path.' : 'TalentXpanse reviewed and dismissed a reliability concern. Your marketplace reach was not changed.', '/settings/reliability');

        return ['data' => $updated];
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole('admin'), 403, 'Administrator access is required.');
    }

    private function funnelMetrics(): array
    {
        $since = now()->subDays(30);
        $events = MarketplaceProductEvent::query()->where('occurred_at', '>=', $since);

        return [
            'period_label' => 'Last 30 days',
            'registered' => User::where('created_at', '>=', $since)->count(),
            'profiles_updated' => (clone $events)->whereIn('event', ['freelancer_profile_updated', 'client_profile_updated'])->count(),
            'jobs_posted' => (clone $events)->where('event', 'job_posted')->count(),
            'proposals_submitted' => (clone $events)->where('event', 'proposal_submitted')->count(),
            'contracts_started' => (clone $events)->where('event', 'contract_started')->count(),
        ];
    }

    private function purgeIdentityDocuments(IdentityVerificationSubmission $submission): void
    {
        $disk = Storage::disk(MarketplaceStorage::privateDisk());
        foreach ([$submission->nrc_front_path, $submission->nrc_back_path] as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $submission->update([
            'nrc_front_path' => null,
            'nrc_back_path' => null,
            'documents_purged_at' => now(),
        ]);
    }

    private function reliabilityTarget(MarketplaceReport $report): array
    {
        if ($report->target_type === 'job') {
            $job = Job::find($report->target_id);

            return [$job?->client, 'client'];
        }
        if ($report->target_type === 'freelancer') {
            $profile = FreelancerProfile::with('user')->find($report->target_id);

            return [$profile?->user, 'freelancer'];
        }

        $message = ConversationMessage::with('conversation')->find($report->target_id);
        $role = $message?->conversation?->client_id === $message?->sender_id ? 'client' : 'freelancer';

        return [$message?->sender, $role];
    }

    private function targetPreview(MarketplaceReport $report): array
    {
        return match ($report->target_type) {
            'job' => $this->jobPreview($report->target_id),
            'freelancer' => $this->freelancerPreview($report->target_id),
            'message' => $this->messagePreview($report->target_id),
            default => ['available' => false, 'title' => 'Unknown item'],
        };
    }

    private function jobPreview(int $id): array
    {
        $job = Job::with('client.clientProfile')->find($id);

        return $job ? ['available' => true, 'title' => $job->title, 'subtitle' => $job->client?->clientProfile?->company_name ?: $job->client?->name, 'excerpt' => Str::limit($job->description, 180), 'status' => $job->status] : ['available' => false, 'title' => 'Removed job'];
    }

    private function freelancerPreview(int $id): array
    {
        $profile = FreelancerProfile::with('user')->find($id);

        return $profile ? ['available' => true, 'title' => $profile->user?->name ?: 'Freelancer', 'subtitle' => $profile->title ?: 'Freelancer profile', 'excerpt' => Str::limit($profile->bio ?: 'No profile introduction.', 180), 'status' => $profile->availability ? 'available' : 'unavailable'] : ['available' => false, 'title' => 'Removed freelancer profile'];
    }

    private function messagePreview(int $id): array
    {
        $message = ConversationMessage::with(['sender', 'conversation.job'])->find($id);

        return $message ? ['available' => true, 'title' => "Message from {$message->sender?->name}", 'subtitle' => $message->conversation?->job?->title ?: 'Marketplace conversation', 'excerpt' => Str::limit($message->body, 180), 'status' => 'sent'] : ['available' => false, 'title' => 'Removed message'];
    }
}
