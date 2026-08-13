<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceReliabilityAppeal;
use App\Models\MarketplaceReliabilityEvent;
use Illuminate\Http\Request;

class MarketplaceAppealController extends Controller
{
    public function index(Request $request)
    {
        return ['data' => $request->user()->reliabilityAppeals()->with('reliabilityEvent')->latest()->get()];
    }

    public function store(Request $request, MarketplaceReliabilityEvent $reliabilityEvent)
    {
        abort_unless($reliabilityEvent->user_id === $request->user()->id, 403);
        abort_unless($reliabilityEvent->status === 'confirmed', 422, 'Only confirmed reliability decisions can be appealed.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:20', 'max:2000']]);
        abort_if(MarketplaceReliabilityAppeal::query()->where('user_id', $request->user()->id)->where('marketplace_reliability_event_id', $reliabilityEvent->id)->exists(), 422, 'You already submitted an appeal for this decision.');

        $appeal = MarketplaceReliabilityAppeal::create($data + [
            'user_id' => $request->user()->id,
            'marketplace_reliability_event_id' => $reliabilityEvent->id,
        ]);

        return response()->json(['data' => $appeal->load('reliabilityEvent'), 'message' => 'Your appeal was sent for an independent marketplace review.'], 201);
    }
}
