<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\ProposalController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'application' => config('app.name')]));

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'google']);

Route::get('/jobs', [JobController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/roles', [AuthController::class, 'addRole']);

    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::get('/jobs/mine', [JobController::class, 'mine']);
    Route::post('/jobs', [JobController::class, 'store']);
    Route::patch('/jobs/{job}', [JobController::class, 'update']);

    Route::post('/jobs/{job}/proposals', [ProposalController::class, 'store']);
    Route::get('/jobs/{job}/proposals', [ProposalController::class, 'forJob']);
    Route::get('/proposals/mine', [ProposalController::class, 'mine']);
    Route::patch('/proposals/{proposal}', [ProposalController::class, 'updateStatus']);
});

Route::get('/jobs/{job}', [JobController::class, 'show']);
