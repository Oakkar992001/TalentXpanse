<?php

namespace Tests\Feature;

use App\Models\FreelancerProfile;
use App\Models\MarketplaceFeedback;
use App\Models\MarketplaceNotification;
use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_and_revoke_other_device_sessions(): void
    {
        $member = User::factory()->create();
        $current = $member->createToken('Current browser');
        $other = $member->createToken('Other browser');

        $this->withToken($current->plainTextToken)->getJson('/api/security')
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', false)
            ->assertJsonCount(2, 'data.sessions');

        $this->withToken($current->plainTextToken)->postJson('/api/security/sessions/revoke-others')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
    }

    public function test_member_can_send_feedback_and_see_onboarding_progress(): void
    {
        $freelancer = User::factory()->create();
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        FreelancerProfile::create(['user_id' => $freelancer->id]);
        Sanctum::actingAs($freelancer);

        $this->postJson('/api/feedback', ['area' => 'marketplace', 'rating' => 4, 'message' => 'The job search is useful, but I would like clearer skill filters.', 'page_url' => '/search'])
            ->assertCreated()
            ->assertJsonPath('data.area', 'marketplace');
        $this->assertDatabaseHas('marketplace_feedback', ['user_id' => $freelancer->id, 'rating' => 4]);

        $this->getJson('/api/onboarding')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.reward.amount', 3);
    }

    public function test_administrator_can_view_feedback_and_funnel_metrics(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin']));
        MarketplaceFeedback::create(['area' => 'general', 'message' => 'The home page explains the beta well.']);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.new_feedback', 1)
            ->assertJsonStructure(['data' => ['funnel' => ['registered', 'jobs_posted', 'proposals_submitted', 'contracts_started']]]);
        $this->getJson('/api/admin/feedback')->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_dashboard_returns_personalised_discovery_and_return_data(): void
    {
        $client = User::factory()->create();
        $client->roles()->attach(Role::firstOrCreate(['name' => 'client']));
        $freelancer = User::factory()->create();
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        FreelancerProfile::create([
            'user_id' => $freelancer->id,
            'title' => 'Laravel developer',
            'skills' => ['Laravel', 'React'],
            'availability' => true,
            'profile_completeness' => 90,
        ]);
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Build a Laravel client portal',
            'description' => 'Build a secure and responsive portal with a clear handover plan.',
            'category' => 'Development & IT',
            'skills' => ['Laravel', 'React'],
            'budget_min' => 300000,
            'budget_max' => 500000,
            'status' => 'open',
        ]);
        MarketplaceNotification::create(['user_id' => $client->id, 'type' => 'test', 'title' => 'New marketplace update', 'body' => 'This appears in your dashboard activity.']);

        Sanctum::actingAs($client);

        $this->getJson('/api/dashboard?role=client')
            ->assertOk()
            ->assertJsonPath('data.metrics.active_jobs', 1)
            ->assertJsonPath('data.recommended_talent.0.user_id', $freelancer->id)
            ->assertJsonPath('data.notifications.0.title', 'New marketplace update')
            ->assertJsonStructure(['data' => ['action_items', 'active_projects', 'saved_searches']]);

        Sanctum::actingAs($freelancer);

        $this->getJson('/api/dashboard?role=freelancer')
            ->assertOk()
            ->assertJsonPath('data.role', 'freelancer')
            ->assertJsonPath('data.recommended_jobs.0.id', $job->id)
            ->assertJsonPath('data.recommended_jobs.0.match.skills.0', 'laravel');
    }
}
