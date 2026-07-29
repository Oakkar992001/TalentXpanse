<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractScopeChangeRequest;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContractScopeChangeController extends Controller
{
    public function store(Request $request, Contract $contract, MarketplaceNotificationService $notifications)
    {
        $this->authorizeParticipant($request, $contract);
        abort_unless($contract->status === 'active', 422, 'Scope changes are only available for active projects.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'min:20', 'max:4000'],
            'amount_delta' => ['required', 'integer', 'min:-100000000', 'max:100000000'],
            'proposed_due_date' => ['nullable', 'date'],
        ]);
        abort_if($data['amount_delta'] === 0 && blank($data['proposed_due_date']), 422, 'Propose a budget change, a due date, or both.');

        $change = $contract->scopeChangeRequests()->create($data + ['requested_by' => $request->user()->id]);
        $this->event($contract, 'scope_change_requested', "Scope change requested: {$change->title}");
        $notifications->send($this->partnerId($contract, $request->user()->id), 'scope_change_requested', 'Scope change needs your review', "{$request->user()->name} requested a scope change for {$contract->title}.", "/projects/{$contract->id}");

        return response()->json(['data' => $change->fresh(['requester'])], 201);
    }

    public function update(Request $request, ContractScopeChangeRequest $scopeChange, MarketplaceNotificationService $notifications)
    {
        $contract = $scopeChange->contract;
        $this->authorizeParticipant($request, $contract);
        $data = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'declined', 'withdrawn'])],
            'response_note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless($scopeChange->status === 'pending', 422, 'This scope change has already been handled.');

        if ($data['status'] === 'withdrawn') {
            abort_unless($scopeChange->requested_by === $request->user()->id, 403, 'Only the requester can withdraw this scope change.');
            $scopeChange->update(['status' => 'withdrawn', 'responded_by' => $request->user()->id, 'responded_at' => now()]);
            $this->event($contract, 'scope_change_withdrawn', "Scope change withdrawn: {$scopeChange->title}");

            return ['data' => $scopeChange->fresh(['requester', 'responder'])];
        }

        abort_unless($scopeChange->requested_by !== $request->user()->id, 403, 'The other project partner must review this scope change.');
        $updated = DB::transaction(function () use ($scopeChange, $contract, $request, $data) {
            if ($data['status'] === 'accepted') {
                $lockedContract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
                $newAmount = $lockedContract->agreed_amount + $scopeChange->amount_delta;
                $allocated = $lockedContract->milestones()->sum('amount');
                abort_if($newAmount < $allocated || $newAmount < 1000, 422, 'The revised agreement cannot be lower than the approved or planned milestone total.');
                $lockedContract->update(['agreed_amount' => $newAmount]);
            }
            $scopeChange->update($data + ['responded_by' => $request->user()->id, 'responded_at' => now()]);

            return $scopeChange->fresh(['requester', 'responder']);
        });
        $this->event($contract, "scope_change_{$data['status']}", "Scope change {$data['status']}: {$scopeChange->title}");
        $notifications->send($scopeChange->requested_by, "scope_change_{$data['status']}", 'Scope change updated', "Your scope change for {$contract->title} was {$data['status']}.", "/projects/{$contract->id}");

        return ['data' => $updated];
    }

    private function event(Contract $contract, string $type, string $body): void
    {
        $conversation = Conversation::where('proposal_id', $contract->proposal_id)->first();
        if (! $conversation) return;

        ConversationEvent::create(['conversation_id' => $conversation->id, 'contract_id' => $contract->id, 'type' => $type, 'body' => $body]);
        $conversation->update(['last_message_at' => now()]);
    }

    private function partnerId(Contract $contract, int $userId): int
    {
        return $contract->client_id === $userId ? $contract->freelancer_id : $contract->client_id;
    }

    private function authorizeParticipant(Request $request, Contract $contract): void
    {
        abort_unless(in_array($request->user()->id, [$contract->client_id, $contract->freelancer_id], true), 403, 'You are not part of this contract.');
    }
}
