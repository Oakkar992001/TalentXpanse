<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractSupportRequest;
use App\Models\ConversationMessage;
use App\Models\FreelancerProfile;
use App\Models\Job;
use App\Models\MarketplaceReport;
use App\Models\Proposal;
use App\Models\User;
use App\Services\MarketplaceNotificationService;
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
            'open_jobs' => Job::where('status', 'open')->count(),
            'proposals' => Proposal::count(),
            'active_contracts' => Contract::where('status', 'active')->count(),
            'open_support_requests' => ContractSupportRequest::whereIn('status', ['open', 'under_review'])->count(),
            'open_reports' => MarketplaceReport::where('status', 'open')->count(),
        ]];
    }

    public function users(Request $request)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'suspended'])]]);
        $users = User::query()->with('roles')->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->latest()->paginate(20);

        return ['data' => $users];
    }

    public function updateUser(Request $request, User $user)
    {
        $this->ensureAdmin($request);
        abort_if($user->id === $request->user()->id, 422, 'You cannot suspend your own administrator account.');
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $user->update($data);

        return ['data' => $user->fresh('roles')];
    }

    public function jobs(Request $request)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:40']]);
        $jobs = Job::query()->with('client.clientProfile')->withCount('proposals')->when($data['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->latest()->paginate(20);

        return ['data' => $jobs];
    }

    public function updateJob(Request $request, Job $job)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'paused', 'closed'])]]);
        abort_if(in_array($job->status, ['in_progress', 'completed'], true), 422, 'Contract jobs cannot be moderated from this action.');
        $job->update($data);

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

    public function updateReport(Request $request, MarketplaceReport $report)
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['reviewed', 'resolved', 'dismissed'])]]);
        $report->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

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

    public function updateSupportRequest(Request $request, ContractSupportRequest $supportRequest, MarketplaceNotificationService $notifications)
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'resolved', 'dismissed'])],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_if(in_array($data['status'], ['resolved', 'dismissed'], true) && blank($data['resolution_note'] ?? null), 422, 'Add a short resolution note before closing a support request.');

        $supportRequest->update($data + ['handled_by' => $request->user()->id, 'handled_at' => now()]);
        $status = str_replace('_', ' ', $supportRequest->status);
        $notifications->send($supportRequest->opened_by, 'project_support_updated', 'Project support request updated', "Your support request for {$supportRequest->contract->title} is now {$status}.", "/projects/{$supportRequest->contract_id}");

        return ['data' => $supportRequest->fresh(['contract', 'opener', 'handler'])];
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
