<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use App\Services\TrustSummaryService;
use Illuminate\Http\Request;

class ClientProfileController extends Controller
{
    public function show(Request $request, TrustSummaryService $trust)
    {
        $this->ensureClient($request);

        return ['data' => $this->payload($request, $trust)];
    }

    public function update(Request $request, TrustSummaryService $trust)
    {
        $this->ensureClient($request);
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:160'],
            'company_description' => ['nullable', 'string', 'max:2500'],
            'website' => ['nullable', 'url', 'max:500'],
            'industry' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        $data['company_name'] = filled($data['company_name'] ?? null) ? $data['company_name'] : null;
        ClientProfile::firstOrCreate(['user_id' => $request->user()->id])->update($data);

        return ['data' => $this->payload($request, $trust)];
    }

    private function payload(Request $request, TrustSummaryService $trust): array
    {
        $user = $request->user()->fresh()->load('clientProfile');

        return [...$user->toArray(), 'trust_summary' => $trust->for($user)];
    }

    private function ensureClient(Request $request): void
    {
        abort_unless($request->user()->hasRole('client'), 403, 'Add the Client workspace before editing company details.');
    }
}
