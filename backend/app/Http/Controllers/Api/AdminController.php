<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractSupportRequest;
use App\Models\ClientProfile;
use App\Models\ConversationMessage;
use App\Models\FreelancerProfile;
use App\Models\Job;
use App\Models\MarketplaceReport;
use App\Models\MarketplacePaymentRecord;
use App\Models\MarketplaceAdminAuditLog;
use App\Models\Proposal;
use App\Models\User;
use App\Services\MarketplaceNotificationService;
use App\Services\MarketplacePaymentSafetyService;
use App\Services\MarketplaceEscrowService;
use App\Services\MarketplacePaymentService;
use App\Services\MarketplaceAdminAuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->ensureAdmin($request);

        return ['data' => [
            'users' => User::count(),
            'suspended_users' => User::where('status', 'suspended')->count(),
            'pending_identity_verifications' => User::where('identity_verification_status', 'pending')->count(),
            'pending_company_verifications' => ClientProfile::where('company_verification_status', 'pending')->count(),
            'open_jobs' => Job::where('status', 'open')->count(),
            'proposals' => Proposal::count(),
            'active_contracts' => Contract::where('status', 'active')->count(),
            'open_support_requests' => ContractSupportRequest::whereIn('status', ['open', 'under_review'])->count(),
            'open_reports' => MarketplaceReport::where('status', 'open')->count(),
            'payment_holds' => Contract::where('payment_hold_status', 'on_hold')->count(),
            'audit_entries' => MarketplaceAdminAuditLog::count(),
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

    public function updateJob(Request $request, Job $job, MarketplaceAdminAuditService $audit)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'paused', 'closed'])]]);
        abort_if(in_array($job->status, ['in_progress', 'completed'], true), 422, 'Contract jobs cannot be moderated from this action.');
        $job->update($data);
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

    public function updateReport(Request $request, MarketplaceReport $report, MarketplaceAdminAuditService $audit)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['reviewed', 'resolved', 'dismissed'])]]);
        $report->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $audit->log($request->user(), 'report.status_updated', $report, "Report status changed to {$report->status}.", ['status' => $report->status]);

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

        $identity = User::query()->where('identity_verification_status', 'pending')->with('roles')->latest('identity_verification_requested_at')->get();
        $identity->each(fn (User $user) => $user->makeVisible(['email']));
        $companies = ClientProfile::query()->where('company_verification_status', 'pending')->with('user')->latest('company_verification_requested_at')->get();
        $companies->each(fn (ClientProfile $profile) => $profile->user?->makeVisible(['email']));

        return ['data' => [
            'identity' => $identity,
            'companies' => $companies,
        ]];
    }

    public function updateIdentityVerification(Request $request, User $user, MarketplaceNotificationService $notifications, MarketplaceAdminAuditService $audit)
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($user->identity_verification_status === 'pending', 422, 'This identity verification is not awaiting review.');
        abort_if($data['status'] === 'rejected' && blank($data['note'] ?? null), 422, 'Add a clear reason when rejecting a verification request.');
        $user->update([
            'identity_verification_status' => $data['status'],
            'identity_verification_note' => $data['note'] ?? null,
            'identity_verified_at' => $data['status'] === 'verified' ? now() : null,
            'identity_verified_by' => $request->user()->id,
        ]);
        $audit->log($request->user(), "identity_verification.{$data['status']}", $user, $data['note'] ?? "Identity verification {$data['status']}.", ['status' => $data['status']]);
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

    public function updateCompanyVerification(Request $request, ClientProfile $clientProfile, MarketplaceNotificationService $notifications, MarketplaceAdminAuditService $audit)
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

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole('admin'), 403, 'Administrator access is required.');
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
