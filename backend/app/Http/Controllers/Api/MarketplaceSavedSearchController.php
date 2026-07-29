<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceSavedSearch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceSavedSearchController extends Controller
{
    private const FILTERS = ['q', 'category', 'skill', 'budget_type', 'experience_level', 'min_budget', 'max_budget', 'location', 'min_rate', 'max_rate', 'availability'];

    public function index(Request $request)
    {
        return ['data' => $request->user()->savedSearches()->latest()->get()];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $savedSearch = $request->user()->savedSearches()->create($data);

        return response()->json(['data' => $savedSearch->fresh()], 201);
    }

    public function update(Request $request, MarketplaceSavedSearch $savedSearch)
    {
        $this->authorizeOwner($request, $savedSearch);
        $data = $this->validated($request, false);
        $savedSearch->update($data);

        return ['data' => $savedSearch->fresh()];
    }

    public function destroy(Request $request, MarketplaceSavedSearch $savedSearch)
    {
        $this->authorizeOwner($request, $savedSearch);
        $savedSearch->delete();

        return response()->noContent();
    }

    private function validated(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:100'],
            'scope' => [$required, Rule::in(['jobs', 'talent'])],
            'filters' => [$required, 'array'],
            'alerts_enabled' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('filters', $data)) {
            $data['filters'] = collect($data['filters'])
                ->only(self::FILTERS)
                ->filter(fn ($value) => filled($value))
                ->all();
        }

        return $data;
    }

    private function authorizeOwner(Request $request, MarketplaceSavedSearch $savedSearch): void
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 403, 'You can only manage your own saved searches.');
    }
}
