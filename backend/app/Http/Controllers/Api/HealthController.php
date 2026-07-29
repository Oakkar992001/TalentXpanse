<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OperationalReadinessService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function show(OperationalReadinessService $readiness): JsonResponse
    {
        $result = $readiness->check();

        return response()->json([
            'status' => $result['ready'] ? 'ok' : 'degraded',
            'application' => config('app.name'),
            ...$result['components'],
        ], $result['ready'] ? 200 : 503);
    }
}
