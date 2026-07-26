<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Job;
use App\Models\PortfolioItem;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TalentXpanseResetPassword;
use App\Services\MarketplaceNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Auth\Notifications\VerifyEmail;
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
        ])->assertCreated()->assertJsonPath('user.roles.0', 'client')->assertJsonPath('user.active_role', 'client');

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

    public function test_users_can_verify_their_email_address_from_a_signed_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/email/verification-notification')->assertOk()->assertJsonPath('message', 'Verification email sent.');
        Notification::assertSentTo($user, VerifyEmail::class);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_users_can_request_and_complete_a_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk()->assertJsonPath('message', 'If an account matches that email, a password-reset link has been sent.');
        Notification::assertSentTo($user, TalentXpanseResetPassword::class);

        $token = PasswordBroker::createToken($user);
        $this->postJson('/api/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'another-secure-password', 'password_confirmation' => 'another-secure-password'])->assertOk()->assertJsonPath('message', 'Your password has been reset. You can now sign in.');
        $this->assertTrue(Hash::check('another-secure-password', $user->fresh()->password));
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'another-secure-password'])->assertOk();
    }

    public function test_users_can_change_their_password_after_confirming_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->putJson('/api/account/password', [
            'current_password' => 'not-the-right-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->actingAs($user, 'sanctum')->putJson('/api/account/password', [
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertOk()->assertJsonPath('message', 'Password updated. Other active sessions have been signed out.');
        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'new-secure-password'])->assertOk();
    }

    public function test_an_active_role_is_stored_and_must_belong_to_the_user(): void
    {
        $registration = $this->postJson('/api/auth/register', [
            'name' => 'Active Role User',
            'email' => 'active-role@example.test',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'client',
        ])->assertCreated();

        $this->withToken($registration->json('token'))->patchJson('/api/auth/active-role', ['role' => 'freelancer'])->assertForbidden();
        $this->withToken($registration->json('token'))->postJson('/api/auth/roles', ['role' => 'freelancer'])->assertOk()->assertJsonPath('user.active_role', 'freelancer');
        $this->withToken($registration->json('token'))->patchJson('/api/auth/active-role', ['role' => 'client'])->assertOk()->assertJsonPath('user.active_role', 'client');
        $this->withToken($registration->json('token'))->putJson('/api/account-settings', ['name' => 'Updated User'])->assertOk()->assertJsonPath('data.name', 'Updated User')->assertJsonPath('data.active_role', 'client');
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

    public function test_project_participants_can_open_a_support_request_for_an_active_contract(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin']));
        $job = Job::create(['client_id' => $client->id, 'title' => 'Resolve a project concern', 'description' => 'Build a careful feature with a clear delivery plan and a practical project support path for both partners.', 'category' => 'Development & IT', 'budget_min' => 200000, 'budget_max' => 300000, 'status' => 'in_progress']);
        $proposal = Proposal::create(['job_id' => $job->id, 'freelancer_id' => $freelancer->id, 'cover_letter' => 'I will deliver the agreed scope with clear updates and structured milestones throughout the project work.', 'bid_amount' => 250000, 'delivery_days' => 14, 'status' => 'hired']);
        $contract = Contract::create(['job_id' => $job->id, 'proposal_id' => $proposal->id, 'client_id' => $client->id, 'freelancer_id' => $freelancer->id, 'title' => $job->title, 'scope' => $job->description, 'agreed_amount' => 250000, 'status' => 'active', 'started_at' => now()]);

        $this->actingAs($freelancer, 'sanctum')->postJson("/api/contracts/{$contract->id}/support-requests", ['reason' => 'communication_issue', 'details' => 'I have sent several clear project updates but have not received a response about the requested delivery direction.'])->assertCreated()->assertJsonPath('data.reason', 'communication_issue');
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $client->id, 'type' => 'project_support_opened']);
        $this->actingAs($freelancer, 'sanctum')->postJson("/api/contracts/{$contract->id}/support-requests", ['reason' => 'other', 'details' => 'This second request should be rejected because the first request is still open for this active project.'])->assertUnprocessable();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/support-requests')->assertOk()->assertJsonPath('data.data.0.contract.title', 'Resolve a project concern');
        $this->actingAs($admin, 'sanctum')->patchJson('/api/admin/support-requests/1', ['status' => 'under_review'])->assertOk()->assertJsonPath('data.status', 'under_review');
        $this->actingAs($admin, 'sanctum')->patchJson('/api/admin/support-requests/1', ['status' => 'resolved', 'resolution_note' => 'Please agree the next review date in the project chat.'])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $freelancer->id, 'type' => 'project_support_updated']);
    }

    public function test_any_authenticated_marketplace_user_can_search_open_jobs_and_available_freelancers(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create(['name' => 'Mya Thiri']);
        $clientRole = Role::firstOrCreate(['name' => 'client']);
        $freelancerRole = Role::firstOrCreate(['name' => 'freelancer']);
        $client->roles()->attach($clientRole);
        $freelancer->roles()->attach($freelancerRole);
        $freelancer->freelancerProfile()->create(['title' => 'React developer', 'skills' => ['React', 'Laravel'], 'location' => 'Yangon', 'availability' => true]);
        Job::create(['client_id' => $client->id, 'title' => 'React storefront', 'description' => 'Build a responsive React storefront for a growing local retailer.', 'category' => 'Development & IT', 'skills' => ['React'], 'budget_min' => 300000, 'budget_max' => 500000, 'status' => 'open']);

        $this->actingAs($client, 'sanctum')->getJson('/api/search?q=React&scope=all')->assertOk()->assertJsonCount(1, 'data.jobs')->assertJsonCount(1, 'data.talent')->assertJsonPath('data.talent.0.user.name', 'Mya Thiri');
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/search?q=React&scope=all')->assertOk()->assertJsonCount(1, 'data.jobs')->assertJsonCount(1, 'data.talent')->assertJsonPath('data.talent.0.user.name', 'Mya Thiri');
        $this->actingAs($client, 'sanctum')->getJson('/api/search?scope=jobs&category=Development%20%26%20IT&skill=React&min_budget=200000&max_budget=600000')->assertOk()->assertJsonCount(1, 'data.jobs')->assertJsonPath('data.pagination.jobs.total', 1);
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/search?scope=talent&skill=React&location=Yang&availability=available')->assertOk()->assertJsonCount(1, 'data.talent')->assertJsonPath('data.pagination.talent.total', 1);
        $this->getJson("/api/freelancers/{$freelancer->id}")->assertOk()->assertJsonPath('data.freelancer_profile.title', 'React developer');
    }

    public function test_users_can_manage_notification_preferences_that_control_in_app_alerts(): void
    {
        $user = User::factory()->create(['notification_preferences' => ['messages' => false, 'proposals' => true, 'projects' => true]]);
        $notifications = app(MarketplaceNotificationService::class);

        $notifications->send($user, 'message_received', 'New message', 'A message that should be suppressed.');
        $this->assertDatabaseMissing('marketplace_notifications', ['user_id' => $user->id, 'type' => 'message_received']);
        $notifications->send($user, 'milestone_created', 'New milestone', 'A project alert that should be kept.');
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $user->id, 'type' => 'milestone_created']);
        $this->actingAs($user, 'sanctum')->getJson('/api/notification-preferences')->assertOk()->assertJsonPath('data.messages', false);
        $this->actingAs($user, 'sanctum')->putJson('/api/notification-preferences', ['messages' => true, 'proposals' => false, 'projects' => true])->assertOk()->assertJsonPath('data.proposals', false);
    }

    public function test_users_can_only_manage_their_own_role_appropriate_marketplace_saves(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $clientRole = Role::firstOrCreate(['name' => 'client']);
        $freelancerRole = Role::firstOrCreate(['name' => 'freelancer']);
        $client->roles()->attach($clientRole);
        $freelancer->roles()->attach($freelancerRole);
        $profile = $freelancer->freelancerProfile()->create(['title' => 'Laravel developer']);
        $job = Job::create(['client_id' => $client->id, 'title' => 'Build a Laravel dashboard', 'description' => 'Build a polished Laravel dashboard with secure API integration and responsive pages.', 'category' => 'Development & IT', 'budget_min' => 300000, 'budget_max' => 500000, 'status' => 'open']);

        $this->actingAs($freelancer, 'sanctum')->putJson("/api/saved-jobs/{$job->id}")->assertOk()->assertJsonPath('data.saved', true);
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/marketplace-saves')->assertOk()->assertJsonPath('data.job_ids.0', $job->id);
        $this->actingAs($freelancer, 'sanctum')->putJson("/api/saved-talent/{$profile->id}")->assertForbidden();
        $this->actingAs($freelancer, 'sanctum')->deleteJson("/api/saved-jobs/{$job->id}")->assertOk()->assertJsonPath('data.saved', false);

        $this->actingAs($client, 'sanctum')->putJson("/api/saved-talent/{$profile->id}")->assertOk()->assertJsonPath('data.saved', true);
        $this->actingAs($client, 'sanctum')->getJson('/api/marketplace-saves')->assertOk()->assertJsonPath('data.talent_ids.0', $profile->id);
        $this->actingAs($client, 'sanctum')->putJson("/api/saved-jobs/{$job->id}")->assertForbidden();
    }

    public function test_administrator_login_and_moderation_are_protected(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        $member = User::factory()->create();
        $reporter = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $clientRole = Role::firstOrCreate(['name' => 'client']);
        $admin->roles()->attach($adminRole);
        $member->roles()->attach($clientRole);
        $job = Job::create(['client_id' => $member->id, 'title' => 'Moderate this job', 'description' => 'This job has enough detail to be tested by an administrator in the marketplace.', 'category' => 'Development & IT', 'budget_min' => 200000, 'budget_max' => 300000, 'status' => 'open']);

        $this->postJson('/api/admin/auth/login', ['email' => $member->email, 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/admin/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertOk()->assertJsonPath('user.roles.0', 'admin');
        $this->actingAs($member, 'sanctum')->getJson('/api/admin/dashboard')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('data.open_jobs', 1);
        $this->actingAs($reporter, 'sanctum')->postJson('/api/reports', ['target_type' => 'job', 'target_id' => $job->id, 'reason' => 'spam'])->assertCreated();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/reports')->assertOk()->assertJsonPath('data.data.0.target_preview.title', 'Moderate this job');
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/jobs/{$job->id}", ['status' => 'paused'])->assertOk()->assertJsonPath('data.status', 'paused');
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$member->id}", ['status' => 'suspended'])->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->postJson('/api/auth/login', ['email' => $member->email, 'password' => 'password'])->assertForbidden();
    }
}
