<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PortfolioItem;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function store(Request $request)
    {
        $this->ensureFreelancer($request);
        $item = $request->user()->portfolioItems()->create($this->validated($request));

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, PortfolioItem $portfolioItem)
    {
        $this->authorizeOwner($request, $portfolioItem);
        $portfolioItem->update($this->validated($request));

        return ['data' => $portfolioItem->fresh()];
    }

    public function destroy(Request $request, PortfolioItem $portfolioItem)
    {
        $this->authorizeOwner($request, $portfolioItem);
        $portfolioItem->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1600'],
            'project_url' => ['nullable', 'url', 'max:500'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
    }

    private function ensureFreelancer(Request $request): void
    {
        abort_unless($request->user()->hasRole('freelancer'), 403, 'Add the Freelancer role to manage a portfolio.');
    }

    private function authorizeOwner(Request $request, PortfolioItem $portfolioItem): void
    {
        $this->ensureFreelancer($request);
        abort_unless($portfolioItem->user_id === $request->user()->id, 403);
    }
}
