<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\TrustSummaryService;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request, TrustSummaryService $trust)
    {
        $query = Job::query()->with(['client.clientProfile'])->withCount('proposals')->latest();

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($jobs) use ($term) {
                $jobs->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        $query->where('status', 'open');

        $jobs = $query->paginate(12);
        $jobs->getCollection()->each(fn (Job $job) => $job->client?->setAttribute('trust_summary', $trust->for($job->client)));

        return ['data' => $jobs];
    }

    public function show(Job $job, TrustSummaryService $trust)
    {
        abort_unless($job->status === 'open', 404, 'Job not found.');
        $job->load(['client.clientProfile'])->loadCount('proposals');
        $job->client?->setAttribute('trust_summary', $trust->for($job->client));

        return ['data' => $job];
    }

    public function store(Request $request)
    {
        $this->ensureClient($request);
        $job = $request->user()->clientJobs()->create($this->validated($request));

        return response()->json(['data' => $job->load('client.clientProfile')], 201);
    }

    public function update(Request $request, Job $job)
    {
        abort_unless($job->client_id === $request->user()->id, 403, 'Only the job owner can update this job.');
        abort_if(in_array($job->status, ['in_progress', 'completed', 'cancelled'], true), 422, 'This job is managed through its project and cannot be edited.');
        $data = $this->validated($request, false);
        $job->update($data);

        return ['data' => $job->fresh(['client.clientProfile'])];
    }

    public function mine(Request $request)
    {
        $this->ensureClient($request);

        return ['data' => $request->user()->clientJobs()->withCount('proposals')->latest()->get()];
    }

    private function ensureClient(Request $request): void
    {
        abort_unless($request->user()->hasRole('client'), 403, 'Add the Client role before posting a job.');
    }

    private function validated(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $allowedStatuses = $creating ? 'in:draft,open' : 'in:draft,open,paused,closed';

        return $request->validate([
            'title' => [$required, 'string', 'max:180'],
            'description' => [$required, 'string', 'min:30'],
            'category' => [$required, 'string', 'max:100'],
            'skills' => ['nullable', 'array', 'max:12'],
            'skills.*' => ['string', 'max:60'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'gte:budget_min'],
            'budget_type' => ['sometimes', 'in:fixed,hourly'],
            'duration' => ['nullable', 'string', 'max:80'],
            'experience_level' => ['sometimes', 'in:entry,intermediate,expert'],
            'status' => ['sometimes', $allowedStatuses],
        ]);
    }
}
