<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientProfileController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\ContractReviewController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FreelancerProfileController;
use App\Http\Controllers\Api\FreelancerResumeController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\MarketplaceNotificationController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\ProfilePhotoController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\ProposalCreditController;
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
    Route::get('/proposal-credits', [ProposalCreditController::class, 'show']);
    Route::get('/notifications', [MarketplaceNotificationController::class, 'index']);
    Route::get('/notifications/summary', [MarketplaceNotificationController::class, 'summary']);
    Route::patch('/notifications/read-all', [MarketplaceNotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}/read', [MarketplaceNotificationController::class, 'markRead']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/summary', [ConversationController::class, 'summary']);
    Route::get('/conversations/startable-proposals', [ConversationController::class, 'startableProposals']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage']);
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/{contract}', [ContractController::class, 'show']);
    Route::post('/contracts/{contract}/milestones', [ContractController::class, 'storeMilestone']);
    Route::patch('/milestones/{milestone}', [ContractController::class, 'updateMilestone']);
    Route::post('/contracts/{contract}/complete', [ContractController::class, 'complete']);
    Route::post('/contracts/{contract}/reviews', [ContractReviewController::class, 'store']);
    Route::get('/freelancer-profile', [FreelancerProfileController::class, 'show']);
    Route::put('/freelancer-profile', [FreelancerProfileController::class, 'update']);
    Route::get('/client-profile', [ClientProfileController::class, 'show']);
    Route::put('/client-profile', [ClientProfileController::class, 'update']);
    Route::post('/portfolio-items', [PortfolioController::class, 'store']);
    Route::patch('/portfolio-items/{portfolioItem}', [PortfolioController::class, 'update']);
    Route::delete('/portfolio-items/{portfolioItem}', [PortfolioController::class, 'destroy']);
    Route::post('/freelancer-resume', [FreelancerResumeController::class, 'store']);
    Route::post('/profile-photo', [ProfilePhotoController::class, 'store']);
    Route::get('/jobs/mine', [JobController::class, 'mine']);
    Route::post('/jobs', [JobController::class, 'store']);
    Route::patch('/jobs/{job}', [JobController::class, 'update']);

    Route::post('/jobs/{job}/proposals', [ProposalController::class, 'store']);
    Route::get('/jobs/{job}/proposals', [ProposalController::class, 'forJob']);
    Route::get('/proposals/mine', [ProposalController::class, 'mine']);
    Route::patch('/proposals/{proposal}', [ProposalController::class, 'updateStatus']);
    Route::post('/proposals/{proposal}/conversation', [ConversationController::class, 'startFromProposal']);
    Route::get('/proposals/{proposal}/resume', [FreelancerResumeController::class, 'downloadProposalResume']);
});

Route::get('/jobs/{job}', [JobController::class, 'show']);
