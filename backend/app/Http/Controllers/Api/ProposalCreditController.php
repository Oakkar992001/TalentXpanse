<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProposalCreditTransaction;
use App\Services\ProposalCreditService;
use Illuminate\Http\Request;

class ProposalCreditController extends Controller
{
    public function show(Request $request, ProposalCreditService $credits)
    {
        abort_unless($request->user()->hasRole('freelancer'), 403, 'Add the Freelancer role to use Proposal Credits.');

        return ['data' => $credits->summaryFor($request->user()) + [
            'transactions' => ProposalCreditTransaction::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->take(10)
                ->get(),
        ]];
    }
}
