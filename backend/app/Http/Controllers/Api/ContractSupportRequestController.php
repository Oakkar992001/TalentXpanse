<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractSupportRequest;
use App\Services\MarketplaceNotificationService;
use App\Services\MarketplacePaymentSafetyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractSupportRequestController extends Controller
{
    public function store(Request $request, Contract $contract, MarketplaceNotificationService $notifications, MarketplacePaymentSafetyService $paymentSafety)
    {
        abort_unless(in_array($request->user()->id, [$contract->client_id, $contract->freelancer_id], true), 403, 'You are not part of this contract.');
        abort_unless($contract->status === 'active', 422, 'Support requests are available while a project is active.');

        $data = $request->validate([
            'reason' => ['required', Rule::in(['delivery_issue', 'communication_issue', 'scope_issue', 'payment_issue', 'other'])],
            'details' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        abort_if($contract->supportRequests()->where('opened_by', $request->user()->id)->whereIn('status', ['open', 'under_review'])->exists(), 422, 'You already have an open support request for this project.');

        $supportRequest = $contract->supportRequests()->create($data + ['opened_by' => $request->user()->id]);
        $partnerId = $contract->client_id === $request->user()->id ? $contract->freelancer_id : $contract->client_id;
        $notifications->send($partnerId, 'project_support_opened', 'Project support request opened', "A project partner asked TalentXpanse to review {$contract->title}.", "/projects/{$contract->id}");
        if ($supportRequest->reason === 'payment_issue') {
            $wasClear = $contract->payment_hold_status !== 'on_hold';
            $paymentSafety->placeHold($contract, null, 'A project participant opened a payment safety request.');
            if ($wasClear) {
                $notifications->send($contract->client_id, 'payment_hold_placed', 'Payment safety hold active', "A payment safety hold is active for {$contract->title}. No release can happen until it is resolved.", "/projects/{$contract->id}");
                $notifications->send($contract->freelancer_id, 'payment_hold_placed', 'Payment safety hold active', "A payment safety hold is active for {$contract->title}. No release can happen until it is resolved.", "/projects/{$contract->id}");
            }
        }

        return response()->json(['data' => $supportRequest->load('opener')], 201);
    }
}
