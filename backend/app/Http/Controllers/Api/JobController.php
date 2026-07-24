<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
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

        if (! $request->boolean('include_closed')) {
            $query->where('status', 'open');
        }

        return ['data' => $query->paginate(12)];
    }

    public function show(Job $job)
    {
        return ['data' => $job->load(['client.clientProfile'])->loadCount('proposals')];
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
        $job->update($this->validated($request, false));

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
            'status' => ['sometimes', 'in:draft,open,paused,closed'],
        ]);
    }
}
