<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceFeedback;
use App\Services\MarketplaceProductAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceFeedbackController extends Controller
{
    public function store(Request $request, MarketplaceProductAnalyticsService $analytics)
    {
        $data = $request->validate([
            'area' => ['required', Rule::in(['general', 'marketplace', 'hiring', 'projects', 'safety'])],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'page_url' => ['nullable', 'string', 'max:500'],
        ]);
        $feedback = MarketplaceFeedback::create($data + ['user_id' => $request->user()->id]);
        $analytics->track($request->user(), 'feedback_submitted', ['area' => $feedback->area, 'rating' => $feedback->rating]);

        return response()->json(['data' => $feedback, 'message' => 'Thanks — your beta feedback was sent to the TalentXpanse team.'], 201);
    }
}
