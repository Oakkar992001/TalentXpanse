<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TrustSummaryService;

class PublicFreelancerController extends Controller
{
    public function show(User $user, TrustSummaryService $trust)
    {
        abort_unless($user->freelancerProfile()->exists(), 404, 'Freelancer profile not found.');
        $user->load('freelancerProfile', 'portfolioItems');
        $data = $user->toArray();
        $data['trust_summary'] = $trust->for($user);

        return ['data' => $data];
    }
}
