<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceReliabilityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceReliabilityController extends Controller
{
    public function show(Request $request, MarketplaceReliabilityService $reliability)
    {
        $data = $request->validate(['role' => ['nullable', Rule::in(['client', 'freelancer'])]]);
        $roles = $request->user()->roles()->pluck('name')->filter(fn (string $role) => in_array($role, ['client', 'freelancer'], true))->values();
        if ($data['role'] ?? null) {
            abort_unless($roles->contains($data['role']), 403, 'That marketplace workspace is not enabled on this account.');
            $roles = collect([$data['role']]);
        }

        return ['data' => $roles->mapWithKeys(fn (string $role) => [$role => $reliability->summaryFor($request->user(), $role)])->all()];
    }
}
