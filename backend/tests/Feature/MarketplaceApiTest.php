<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Job;
use App\Models\PortfolioItem;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $this->actingAs($freelancer, 'sanctum')->postJson("/api/jobs/{$job->id}/proposals", $payload)
            ->assertCreated()
            ->assertJsonPath('data.credit_cost', 2)
            ->assertJsonPath('proposal_credits.balance', 18);
        $this->actingAs($freelancer, 'sanctum')->postJson("/api/jobs/{$job->id}/proposals", $payload)->assertUnprocessable();

        $this->assertDatabaseHas('proposal_credit_accounts', ['user_id' => $freelancer->id, 'balance' => 18]);
        $this->assertDatabaseHas('proposal_credit_transactions', ['user_id' => $freelancer->id, 'type' => 'proposal_submission', 'amount' => -2]);
    }

    public function test_a_client_can_hire_one_freelancer_and_close_the_job(): void
    {
        $clientRole = Role::create(['name' => 'client']);
        $freelancerRole = Role::create(['name' => 'freelancer']);
        $client = User::factory()->create();
        $client->roles()->attach($clientRole);
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Build a Laravel marketplace API',
            'description' => 'Build a secure marketplace API with a thoughtful proposal workflow for a Myanmar-focused product.',
            'category' => 'Development & IT',
            'budget_min' => 450000,
            'budget_max' => 700000,
            'status' => 'open',
        ]);
        $firstFreelancer = User::factory()->create();
        $secondFreelancer = User::factory()->create();
        $firstFreelancer->roles()->attach($freelancerRole);
        $secondFreelancer->roles()->attach($freelancerRole);
        $selected = Proposal::create(['job_id' => $job->id, 'freelancer_id' => $firstFreelancer->id, 'cover_letter' => 'I can build a careful Laravel API with robust validation, clear documentation, and an approachable handover for your team.', 'bid_amount' => 600000, 'delivery_days' => 30]);
        $other = Proposal::create(['job_id' => $job->id, 'freelancer_id' => $secondFreelancer->id, 'cover_letter' => 'I have delivered reliable marketplace APIs and would bring clear communication with a pragmatic delivery plan to this project.', 'bid_amount' => 550000, 'delivery_days' => 25]);

        $this->actingAs($client, 'sanctum')->patchJson("/api/proposals/{$selected->id}", ['status' => 'hired'])
            ->assertOk()
            ->assertJsonPath('data.status', 'hired')
            ->assertJsonPath('data.job.status', 'in_progress');

        $this->assertDatabaseHas('proposals', ['id' => $selected->id, 'status' => 'hired']);
        $this->assertDatabaseHas('proposals', ['id' => $other->id, 'status' => 'declined']);
        $this->assertDatabaseHas('marketplace_jobs', ['id' => $job->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('contracts', ['proposal_id' => $selected->id, 'status' => 'active', 'agreed_amount' => 600000]);
        $this->actingAs($client, 'sanctum')->patchJson("/api/jobs/{$job->id}", ['status' => 'open'])->assertUnprocessable();
    }

    public function test_a_freelancer_can_attach_selected_portfolio_samples_to_a_proposal(): void
    {
        $clientRole = Role::create(['name' => 'client']);
        $freelancerRole = Role::create(['name' => 'freelancer']);
        $client = User::factory()->create();
        $client->roles()->attach($clientRole);
        $freelancer = User::factory()->create();
        $freelancer->roles()->attach($freelancerRole);
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Design a polished product onboarding flow',
            'description' => 'Create a clear onboarding experience with practical mobile-first design decisions and a reusable component plan.',
            'category' => 'Design & Creative',
            'budget_min' => 150000,
            'budget_max' => 280000,
            'status' => 'open',
        ]);
        $sample = PortfolioItem::create([
            'user_id' => $freelancer->id,
            'title' => 'Wallet onboarding redesign',
            'description' => 'Improved activation for a mobile wallet product.',
            'project_url' => 'https://example.test/case-study',
        ]);

        $this->actingAs($freelancer, 'sanctum')->postJson("/api/jobs/{$job->id}/proposals", [
            'cover_letter' => 'I have designed and tested mobile onboarding journeys that make account activation simple, friendly, and measurable for new customers.',
            'bid_amount' => 250000,
            'delivery_days' => 12,
            'portfolio_item_ids' => [$sample->id],
        ])->assertCreated()->assertJsonPath('data.work_samples.0.title', 'Wallet onboarding redesign');

        $this->assertDatabaseHas('proposal_work_samples', ['portfolio_item_id' => $sample->id, 'title' => 'Wallet onboarding redesign']);
    }

    public function test_a_user_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->post('/api/profile-photo', [
            'photo' => UploadedFile::fake()->createWithContent('portrait.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL+tAAAAABJRU5ErkJggg==')),
        ])->assertOk()->assertJsonStructure(['data' => ['profile_photo_url']]);
        $this->assertStringContainsString('/storage/profile-photos/'.$user->id.'/', $response->json('data.profile_photo_url'));

        $user->refresh();
        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
    }

    public function test_a_client_can_start_a_proposal_chat_and_hiring_upgrades_it_to_a_project_chat(): void
    {
        $clientRole = Role::create(['name' => 'client']);
        $freelancerRole = Role::create(['name' => 'freelancer']);
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $client->roles()->attach($clientRole);
        $freelancer->roles()->attach($freelancerRole);
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Create an accessible onboarding experience',
            'description' => 'Design a friendly accessible onboarding experience with clear documentation and mobile-first interaction details.',
            'category' => 'Design & Creative',
            'budget_min' => 250000,
            'budget_max' => 350000,
            'status' => 'open',
        ]);
        $proposal = Proposal::create(['job_id' => $job->id, 'freelancer_id' => $freelancer->id, 'cover_letter' => 'I would create an accessible onboarding journey with a thoughtful mobile-first structure, clear validation states, and practical testing notes.', 'bid_amount' => 300000, 'delivery_days' => 14]);

        $conversation = $this->actingAs($client, 'sanctum')->postJson("/api/proposals/{$proposal->id}/conversation")
            ->assertCreated()->assertJsonPath('data.type', 'proposal')->json('data');
        $this->actingAs($client, 'sanctum')->postJson("/api/conversations/{$conversation['id']}/messages", ['body' => 'Thanks for applying. Can you share your availability this week?'])->assertCreated();
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1);
        $this->actingAs($freelancer, 'sanctum')->getJson("/api/conversations/{$conversation['id']}")->assertOk()->assertJsonCount(1, 'data.messages');
        $this->actingAs($client, 'sanctum')->patchJson("/api/proposals/{$proposal->id}", ['status' => 'hired'])->assertOk();

        $this->assertDatabaseHas('conversations', ['id' => $conversation['id'], 'type' => 'project']);
        $this->assertSame('project', Conversation::find($conversation['id'])->type);
    }

    public function test_a_contract_milestone_can_be_delivered_approved_and_completed(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Create a responsive product dashboard',
            'description' => 'Create a responsive dashboard with a clear delivery plan and documented reusable interface components.',
            'category' => 'Development & IT',
            'budget_min' => 400000,
            'budget_max' => 600000,
            'status' => 'in_progress',
        ]);
        $proposal = Proposal::create(['job_id' => $job->id, 'freelancer_id' => $freelancer->id, 'cover_letter' => 'I can deliver a responsive dashboard with maintainable components, careful testing, and clear progress communication throughout the project.', 'bid_amount' => 500000, 'delivery_days' => 20, 'status' => 'hired']);
        $contract = Contract::create(['job_id' => $job->id, 'proposal_id' => $proposal->id, 'client_id' => $client->id, 'freelancer_id' => $freelancer->id, 'title' => $job->title, 'scope' => $job->description, 'agreed_amount' => 500000, 'status' => 'active', 'started_at' => now()]);

        $milestone = $this->actingAs($client, 'sanctum')->postJson("/api/contracts/{$contract->id}/milestones", ['title' => 'Dashboard design and build', 'description' => 'Deliver the completed dashboard and source files.', 'amount' => 500000, 'due_date' => now()->addWeeks(2)->toDateString()])->assertCreated()->json('data');
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $freelancer->id, 'type' => 'milestone_created']);
        $this->actingAs($freelancer, 'sanctum')->patchJson("/api/milestones/{$milestone['id']}", ['action' => 'start'])->assertOk();
        $this->actingAs($freelancer, 'sanctum')->patchJson("/api/milestones/{$milestone['id']}", ['action' => 'submit'])->assertOk();
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $client->id, 'type' => 'milestone_submitted']);
        $this->actingAs($client, 'sanctum')->patchJson("/api/milestones/{$milestone['id']}", ['action' => 'approve'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->actingAs($client, 'sanctum')->postJson("/api/contracts/{$contract->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

        $this->actingAs($client, 'sanctum')->postJson("/api/contracts/{$contract->id}/reviews", ['rating' => 5, 'comment' => 'Thoughtful work and clear communication.'])->assertCreated();
        $this->actingAs($freelancer, 'sanctum')->getJson("/api/contracts/{$contract->id}")->assertOk()->assertJsonPath('data.reviews.0.rating', null);
        $this->actingAs($freelancer, 'sanctum')->postJson("/api/contracts/{$contract->id}/reviews", ['rating' => 5, 'comment' => 'A well-organized and responsive client.'])->assertCreated();
        $this->actingAs($client, 'sanctum')->getJson("/api/contracts/{$contract->id}")->assertOk()->assertJsonPath('data.reviews.1.rating', 5);
        $this->actingAs($client, 'sanctum')->postJson("/api/contracts/{$contract->id}/reviews", ['rating' => 4])->assertUnprocessable();

        $freelancerRole = Role::firstOrCreate(['name' => 'freelancer']);
        $clientRole = Role::firstOrCreate(['name' => 'client']);
        $freelancer->roles()->syncWithoutDetaching([$freelancerRole->id]);
        $client->roles()->syncWithoutDetaching([$clientRole->id]);
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/freelancer-profile')->assertOk()->assertJsonPath('data.trust_summary.average_rating', 5)->assertJsonPath('data.trust_summary.completed_projects_count', 1);
        $this->actingAs($client, 'sanctum')->getJson('/api/client-profile')->assertOk()->assertJsonPath('data.trust_summary.review_count', 1)->assertJsonPath('data.trust_summary.completed_projects.0.title', 'Create a responsive product dashboard');
    }
}
