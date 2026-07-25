<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Proposal;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function startFromProposal(Request $request, Proposal $proposal)
    {
        abort_unless($proposal->job->client_id === $request->user()->id, 403, 'Only the client can start a proposal conversation.');
        abort_if($proposal->status === 'declined', 422, 'This proposal is no longer active.');

        $conversation = Conversation::firstOrCreate(['proposal_id' => $proposal->id], [
            'job_id' => $proposal->job_id,
            'client_id' => $proposal->job->client_id,
            'freelancer_id' => $proposal->freelancer_id,
            'type' => $proposal->status === 'hired' ? 'project' : 'proposal',
        ]);

        return response()->json(['data' => $this->payload($conversation, $request->user())], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $conversations = Conversation::query()
            ->where(fn ($query) => $query->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->with(['job', 'client', 'freelancer', 'messages' => fn ($query) => $query->latest()->take(1)])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get();

        return ['data' => $conversations->map(fn (Conversation $conversation) => $this->payload($conversation, $user))->values()];
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($request, $conversation);
        $this->markRead($conversation, $request->user());

        $messages = $conversation->messages()->with('sender')->oldest()->get()->map(fn ($message) => $message->setAttribute('kind', 'message'));
        $events = ConversationEvent::where('conversation_id', $conversation->id)->oldest()->get()->map(fn (ConversationEvent $event) => [
            'id' => "event-{$event->id}",
            'sender_id' => null,
            'body' => $event->body,
            'created_at' => $event->created_at,
            'kind' => 'system',
        ]);

        return ['data' => $this->payload($conversation->fresh(['job', 'client', 'freelancer']), $request->user()) + [
            'messages' => $messages->concat($events)->sortBy('created_at')->values(),
        ]];
    }

    public function storeMessage(Request $request, Conversation $conversation, MarketplaceNotificationService $notifications)
    {
        $this->authorizeParticipant($request, $conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message = $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => $data['body']]);
        $conversation->update(['last_message_at' => now()]);
        $this->markRead($conversation, $request->user());
        $recipientId = $conversation->client_id === $request->user()->id ? $conversation->freelancer_id : $conversation->client_id;
        $notifications->send($recipientId, 'message_received', 'New message', "{$request->user()->name}: ".str($message->body)->limit(120), '/messages');

        return response()->json(['data' => $message->load('sender')], 201);
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $conversations = Conversation::query()->where(fn ($query) => $query->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))->get();

        return ['data' => ['unread_messages' => $conversations->sum(fn (Conversation $conversation) => $this->unreadCount($conversation, $user))]];
    }

    public function startableProposals(Request $request)
    {
        abort_unless($request->user()->hasRole('client'), 403);

        return ['data' => Proposal::query()
            ->whereHas('job', fn ($query) => $query->where('client_id', $request->user()->id))
            ->whereIn('status', ['submitted', 'shortlisted', 'hired'])
            ->with(['job', 'freelancer.freelancerProfile'])
            ->latest()
            ->take(20)
            ->get()];
    }

    private function payload(Conversation $conversation, $user): array
    {
        $other = $conversation->client_id === $user->id ? $conversation->freelancer : $conversation->client;
        $latest = $conversation->messages->first();

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'job' => $conversation->job,
            'other_user' => $other,
            'last_message' => $latest,
            'unread_count' => $this->unreadCount($conversation, $user),
            'last_message_at' => $conversation->last_message_at,
        ];
    }

    private function unreadCount(Conversation $conversation, $user): int
    {
        $readAt = $conversation->client_id === $user->id ? $conversation->client_last_read_at : $conversation->freelancer_last_read_at;

        return $conversation->messages()->where('sender_id', '!=', $user->id)->when($readAt, fn ($query) => $query->where('created_at', '>', $readAt))->count();
    }

    private function markRead(Conversation $conversation, $user): void
    {
        $conversation->update([$conversation->client_id === $user->id ? 'client_last_read_at' : 'freelancer_last_read_at' => now()]);
    }

    private function authorizeParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->involves($request->user()), 403, 'You are not part of this conversation.');
    }
}
