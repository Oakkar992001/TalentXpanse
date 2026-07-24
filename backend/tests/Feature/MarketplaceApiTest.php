<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_with_a_client_role_and_post_a_job(): void
    {
        $registration = $this->postJson('/api/auth/register', [
            'name' => 'May Zin',
            'email' => 'mayzin@example.test',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'client',
        ])->assertCreated()->assertJsonPath('user.roles.0', 'client');

        $this->withToken($registration->json('token'))
            ->postJson('/api/jobs', [
                'title' => 'Laravel developer for a local commerce app',
                'description' => 'Build the next phase of a local commerce app with clear documentation and a responsive customer experience.',
                'category' => 'Development & IT',
                'skills' => ['Laravel', 'MySQL'],
                'budget_min' => 300000,
                'budget_max' => 550000,
                'budget_type' => 'fixed',
                'duration' => '1 to 3 months',
                'experience_level' => 'intermediate',
            ])
            ->assertCreated()
            ->assertJsonPath('data.budget_max', 550000);

        $this->assertDatabaseHas('marketplace_jobs', ['title' => 'Laravel developer for a local commerce app']);
    }

    public function test_a_freelancer_can_submit_one_proposal_but_not_duplicate_it(): void
    {
        $clientRole = Role::create(['name' => 'client']);
        $freelancerRole = Role::create(['name' => 'freelancer']);
        $client = User::factory()->create();
        $client->roles()->attach($clientRole);
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Create a Myanmar onboarding flow',
            'description' => 'Design and implement a simple accessible onboarding experience for new customers in Myanmar.',
            'category' => 'Design & Creative',
            'budget_min' => 200000,
            'budget_max' => 400000,
            'status' => 'open',
        ]);
        $freelancer = User::factory()->create();
        $freelancer->roles()->attach($freelancerRole);

        $payload = [
            'cover_letter' => 'I have designed onboarding flows for mobile-first products and can deliver a friendly bilingual experience with a clear prototype.',
            'bid_amount' => 350000,
            'delivery_days' => 14,
        ];

        $this->actingAs($freelancer, 'sanctum')->postJson("/api/jobs/{$job->id}/proposals", $payload)->assertCreated();
        $this->actingAs($freelancer, 'sanctum')->postJson("/api/jobs/{$job->id}/proposals", $payload)->assertUnprocessable();
    }
}
