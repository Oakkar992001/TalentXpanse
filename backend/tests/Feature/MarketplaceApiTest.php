<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Conversation;
use App\Models\FreelancerResume;
use App\Models\Job;
use App\Models\PortfolioItem;
use App\Models\Proposal;
use App\Models\ProposalOffer;
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
use Laravel\Sanctum\Sanctum;
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

    public function test_a_freelancer_can_track_and_withdraw_an_active_proposal(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $client->roles()->attach(Role::firstOrCreate(['name' => 'client']));
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        $job = Job::create(['client_id' => $client->id, 'title' => 'Build a job tracker', 'description' => 'Create a clear job-tracking experience with practical proposal status information for freelancers.', 'category' => 'Development & IT', 'budget_min' => 200000, 'budget_max' => 300000, 'status' => 'open']);
        $proposal = Proposal::create(['job_id' => $job->id, 'freelancer_id' => $freelancer->id, 'cover_letter' => 'I can build a practical proposal tracker with clear statuses, accessible controls, and careful attention to the freelancer workflow.', 'bid_amount' => 250000, 'delivery_days' => 12, 'status' => 'submitted', 'credit_cost' => 2]);

        $this->actingAs($freelancer, 'sanctum')->getJson('/api/proposals/mine')->assertOk()->assertJsonPath('data.0.id', $proposal->id);
        $this->actingAs($client, 'sanctum')->patchJson("/api/proposals/{$proposal->id}", ['status' => 'withdrawn'])->assertForbidden();
        $this->actingAs($freelancer, 'sanctum')->patchJson("/api/proposals/{$proposal->id}", ['status' => 'withdrawn'])->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $client->id, 'type' => 'proposal_withdrawn']);
        $this->actingAs($freelancer, 'sanctum')->patchJson("/api/proposals/{$proposal->id}", ['status' => 'withdrawn'])->assertUnprocessable();
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
        Storage::fake('local');
        $message = $this->actingAs($client, 'sanctum')->post("/api/conversations/{$conversation['id']}/messages", [
            'body' => 'Thanks for applying. Can you share your availability this week?',
            'files' => [UploadedFile::fake()->create('project-brief.pdf', 80, 'application/pdf')],
        ])->assertCreated()->assertJsonPath('data.files.0.original_name', 'project-brief.pdf')->json('data');
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1);
        $this->actingAs($freelancer, 'sanctum')->getJson("/api/conversations/{$conversation['id']}")->assertOk()->assertJsonCount(1, 'data.messages')->assertJsonPath('data.messages.0.files.0.original_name', 'project-brief.pdf');
        $this->actingAs($freelancer, 'sanctum')->get("/api/conversation-message-files/{$message['files'][0]['id']}/download")->assertOk();
        $this->actingAs(User::factory()->create(), 'sanctum')->get("/api/conversation-message-files/{$message['files'][0]['id']}/download")->assertForbidden();
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

        $milestone = $this->actingAs($client, 'sanctum')->postJson("/api/contracts/{$contract->id}/milestones", ['title' => 'Dashboard design and build', 'description' => 'Deliver the completed dashboard and source files.', 'amount' => 500000, 'due_date' => now()->addWeeks(2)->toDateString()])->assertCreated()->assertJsonPath('data.client_fee_amount', 50000)->assertJsonPath('data.client_total_amount', 550000)->json('data');
        $this->actingAs($client, 'sanctum')->getJson("/api/contracts/{$contract->id}")->assertOk()->assertJsonPath('data.payment_policy.platform_fee_percent', 10)->assertJsonPath('data.milestones.0.payment_summary.client_total_amount', 550000)->assertJsonPath('data.milestones.0.payment_summary.freelancer_payout_amount', 500000);
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $freelancer->id, 'type' => 'milestone_created']);
        $this->actingAs($freelancer, 'sanctum')->patchJson("/api/milestones/{$milestone['id']}", ['action' => 'start'])->assertOk();
        Storage::fake('local');
        $firstSubmission = $this->actingAs($freelancer, 'sanctum')->post("/api/milestones/{$milestone['id']}/submissions", [
            'note' => 'The dashboard build, handover notes, and source archive are ready for your review.',
            'files' => [UploadedFile::fake()->create('dashboard-handover.pdf', 120, 'application/pdf')],
        ])->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.files.0.original_name', 'dashboard-handover.pdf')
            ->json('data');
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $client->id, 'type' => 'milestone_submitted']);
        $this->actingAs($client, 'sanctum')->get("/api/milestone-submission-files/{$firstSubmission['files'][0]['id']}/download")->assertOk();
        $this->actingAs(User::factory()->create(), 'sanctum')->get("/api/milestone-submission-files/{$firstSubmission['files'][0]['id']}/download")->assertForbidden();
        $this->actingAs($client, 'sanctum')->patchJson("/api/milestones/{$milestone['id']}", [
            'action' => 'request_revision',
            'revision_note' => 'Please make the dashboard filters work comfortably on smaller mobile screens before final approval.',
        ])->assertOk()->assertJsonPath('data.status', 'revision_requested');
        $this->assertDatabaseHas('milestone_submissions', ['id' => $firstSubmission['id'], 'status' => 'revision_requested']);
        $this->actingAs($freelancer, 'sanctum')->postJson("/api/milestones/{$milestone['id']}/submissions", [
            'note' => 'The mobile filter layout has been updated and tested. This is the final delivery version.',
        ])->assertCreated()->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'submitted');
        $this->actingAs($client, 'sanctum')->getJson("/api/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.milestones.0.submissions')
            ->assertJsonPath('data.milestones.0.submissions.0.version', 2)
            ->assertJsonPath('data.milestones.0.submissions.1.status', 'revision_requested');
        $this->actingAs($client, 'sanctum')->patchJson("/api/milestones/{$milestone['id']}", ['action' => 'approve'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->actingAs($freelancer, 'sanctum')->postJson("/api/contracts/{$contract->id}/request-completion", ['note' => 'The final files, source code, and handover notes are all included in the approved delivery.'])
            ->assertOk()
            ->assertJsonPath('data.id', $contract->id);
        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'freelancer_completion_note' => 'The final files, source code, and handover notes are all included in the approved delivery.']);
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
        Sanctum::actingAs($admin, ['admin']);
        $this->getJson('/api/admin/support-requests')->assertOk()->assertJsonPath('data.data.0.contract.title', 'Resolve a project concern');
        $this->patchJson('/api/admin/support-requests/1', ['status' => 'under_review'])->assertOk()->assertJsonPath('data.status', 'under_review');
        $this->patchJson('/api/admin/support-requests/1', ['status' => 'resolved', 'resolution_note' => 'Please agree the next review date in the project chat.'])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $freelancer->id, 'type' => 'project_support_updated']);
    }

    public function test_a_payment_safety_request_places_a_hold_until_an_administrator_clears_it(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin']));
        $job = Job::create(['client_id' => $client->id, 'title' => 'Build a payment-safe client portal', 'description' => 'Build a careful client portal with an auditable milestone and delivery process for a local business team.', 'category' => 'Development & IT', 'budget_min' => 300000, 'budget_max' => 400000, 'status' => 'in_progress']);
        $proposal = Proposal::create(['job_id' => $job->id, 'freelancer_id' => $freelancer->id, 'cover_letter' => 'I will build the portal with clear delivery notes and reliable project communication at each milestone.', 'bid_amount' => 350000, 'delivery_days' => 18, 'status' => 'hired']);
        $contract = Contract::create(['job_id' => $job->id, 'proposal_id' => $proposal->id, 'client_id' => $client->id, 'freelancer_id' => $freelancer->id, 'title' => $job->title, 'scope' => $job->description, 'agreed_amount' => 350000, 'status' => 'active', 'started_at' => now()]);

        $paymentRequest = $this->actingAs($client, 'sanctum')->postJson("/api/contracts/{$contract->id}/support-requests", ['reason' => 'payment_issue', 'details' => 'Please pause any future payment release while TalentXpanse reviews the milestone funding concern and delivery records.'])->assertCreated()->assertJsonPath('data.reason', 'payment_issue')->json('data');
        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'payment_hold_status' => 'on_hold']);
        $this->actingAs($freelancer, 'sanctum')->getJson("/api/contracts/{$contract->id}")->assertOk()->assertJsonPath('data.payment_safety.payment_hold_status', 'on_hold')->assertJsonPath('data.payment_safety.release_allowed', false);
        $this->actingAs($client, 'sanctum')->postJson("/api/contracts/{$contract->id}/complete")->assertUnprocessable();
        $freelancerToken = $freelancer->createToken('talentxpanse-web', ['web'])->plainTextToken;
        $this->withToken($freelancerToken)->getJson('/api/admin/payment-records')->assertUnauthorized();
        Sanctum::actingAs($admin, ['admin']);
        $this->getJson('/api/admin/payment-records')->assertOk()->assertJsonPath('data.payments_enabled', false)->assertJsonCount(1, 'data.on_hold_contracts');
        $this->patchJson("/api/admin/contracts/{$contract->id}/payment-hold", ['status' => 'clear', 'note' => 'The funding concern was reviewed. There is no payment provider transaction to reconcile at this stage.'])->assertUnprocessable();
        $this->patchJson("/api/admin/support-requests/{$paymentRequest['id']}", ['status' => 'under_review'])->assertOk();
        $this->patchJson("/api/admin/support-requests/{$paymentRequest['id']}", ['status' => 'resolved', 'resolution_note' => 'The payment concern was reviewed. There is no provider transaction to reconcile at this stage.'])->assertOk();
        $this->patchJson("/api/admin/contracts/{$contract->id}/payment-hold", ['status' => 'clear', 'note' => 'The funding concern was reviewed. There is no payment provider transaction to reconcile at this stage.'])->assertOk()->assertJsonPath('data.payment_hold_status', 'clear');
        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'payment_hold_status' => 'clear']);
        $this->getJson('/api/admin/audit-logs')->assertOk()->assertJsonPath('data.data.0.action', 'payment_hold.clear');
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

    public function test_freelancer_profile_readiness_is_actionable_and_consistent_in_settings(): void
    {
        $freelancer = User::factory()->create(['active_role' => 'freelancer']);
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));

        $this->actingAs($freelancer, 'sanctum')->putJson('/api/freelancer-profile', [
            'title' => 'Laravel developer',
            'bio' => 'I build reliable marketplace and business tools for local teams.',
            'hourly_rate' => 50000,
            'skills' => ['Laravel', 'React'],
            'location' => 'Yangon, Myanmar',
        ])->assertOk()
            ->assertJsonPath('data.profile_completeness', 70)
            ->assertJsonPath('data.profile_checklist.0.key', 'photo')
            ->assertJsonPath('data.profile_checklist.1.completed', true);

        $this->actingAs($freelancer, 'sanctum')->getJson('/api/account-settings')
            ->assertOk()
            ->assertJsonPath('data.freelancer_profile.profile_completeness', 70);
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

    public function test_a_client_can_use_an_individual_profile_without_a_company_name(): void
    {
        $client = User::factory()->create();
        $client->roles()->attach(Role::firstOrCreate(['name' => 'client']));

        $this->actingAs($client, 'sanctum')->putJson('/api/client-profile', [
            'company_name' => null,
            'location' => 'Yangon, Myanmar',
            'company_description' => 'Hiring an independent freelancer to help with a small product project.',
        ])->assertOk()->assertJsonPath('data.client_profile.company_name', null);

        $this->assertDatabaseHas('client_profiles', ['user_id' => $client->id, 'company_name' => null]);
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
        $memberToken = $member->createToken('talentxpanse-web', ['web'])->plainTextToken;
        $job = Job::create(['client_id' => $member->id, 'title' => 'Moderate this job', 'description' => 'This job has enough detail to be tested by an administrator in the marketplace.', 'category' => 'Development & IT', 'budget_min' => 200000, 'budget_max' => 300000, 'status' => 'open']);

        $this->postJson('/api/admin/auth/login', ['email' => $member->email, 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/admin/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertOk()->assertJsonPath('user.roles.0', 'admin');
        $this->withToken($memberToken)->getJson('/api/admin/dashboard')->assertForbidden();
        Sanctum::actingAs($admin, ['admin']);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('data.open_jobs', 1);
        $this->actingAs($reporter, 'sanctum')->postJson('/api/reports', ['target_type' => 'job', 'target_id' => $job->id, 'reason' => 'spam'])->assertCreated();
        Sanctum::actingAs($admin, ['admin']);
        $this->getJson('/api/admin/reports')->assertOk()->assertJsonPath('data.data.0.target_preview.title', 'Moderate this job');
        $this->patchJson("/api/admin/jobs/{$job->id}", ['status' => 'paused'])->assertOk()->assertJsonPath('data.status', 'paused');
        $this->patchJson("/api/admin/users/{$member->id}", ['status' => 'suspended'])->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $member->id]);
        $member->refresh();
        $this->actingAs($member, 'sanctum')->getJson('/api/dashboard')->assertForbidden()->assertJsonPath('message', 'This account is not currently allowed to access TalentXpanse.');
        $this->postJson('/api/auth/login', ['email' => $member->email, 'password' => 'password'])->assertForbidden();
    }

    public function test_client_can_send_a_formal_offer_and_freelancer_can_accept_it(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $client->roles()->attach(Role::firstOrCreate(['name' => 'client']));
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        $freelancer->freelancerProfile()->create(['title' => 'Laravel developer', 'skills' => ['Laravel']]);
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Build a membership portal',
            'description' => 'Build a secure membership portal with account access, a polished dashboard, and a documented handover process.',
            'category' => 'Development & IT',
            'budget_min' => 300000,
            'budget_max' => 500000,
            'status' => 'open',
        ]);
        $proposal = Proposal::create([
            'job_id' => $job->id,
            'freelancer_id' => $freelancer->id,
            'cover_letter' => 'I can build this portal in clear milestones and provide regular progress updates throughout the project.',
            'bid_amount' => 420000,
            'delivery_days' => 21,
            'status' => 'shortlisted',
        ]);

        $offer = $this->actingAs($client, 'sanctum')->postJson("/api/proposals/{$proposal->id}/offers", [
            'offered_amount' => 450000,
            'delivery_days' => 24,
            'start_date' => now()->addDay()->toDateString(),
            'message' => 'Please begin with the account and membership experience, then deliver the client dashboard.',
            'milestones' => [
                ['title' => 'Account and membership flow', 'description' => 'Sign-in, account management, and membership states.', 'amount' => 225000, 'due_date' => now()->addDays(10)->toDateString()],
                ['title' => 'Dashboard and handover', 'description' => 'Client dashboard, testing, and handover notes.', 'amount' => 225000, 'due_date' => now()->addDays(24)->toDateString()],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');

        $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'status' => 'offered']);
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/proposals/mine')
            ->assertOk()
            ->assertJsonPath('data.0.latest_offer.id', $offer['id'])
            ->assertJsonPath('data.0.latest_offer.status', 'pending');

        $response = $this->actingAs($freelancer, 'sanctum')->patchJson("/api/proposal-offers/{$offer['id']}", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('contract.agreed_amount', 450000)
            ->json();

        $this->assertDatabaseHas('marketplace_jobs', ['id' => $job->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'status' => 'hired']);
        $this->assertDatabaseHas('contract_milestones', ['contract_id' => $response['contract']['id'], 'title' => 'Account and membership flow', 'amount' => 225000]);
        $this->assertDatabaseHas('contract_milestones', ['contract_id' => $response['contract']['id'], 'title' => 'Dashboard and handover', 'amount' => 225000]);
    }

    public function test_an_expired_offer_cannot_start_a_contract(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Refine a customer portal',
            'description' => 'Refine the customer portal with a clearer milestone plan, accessible screens, and documented deployment notes.',
            'category' => 'Development & IT',
            'budget_min' => 200000,
            'budget_max' => 300000,
            'status' => 'open',
        ]);
        $proposal = Proposal::create([
            'job_id' => $job->id,
            'freelancer_id' => $freelancer->id,
            'cover_letter' => 'I can refine the portal with a reliable project plan and practical delivery notes for the client team.',
            'bid_amount' => 250000,
            'delivery_days' => 14,
            'status' => 'offered',
        ]);
        $offer = ProposalOffer::create([
            'proposal_id' => $proposal->id,
            'client_id' => $client->id,
            'freelancer_id' => $freelancer->id,
            'offered_amount' => 250000,
            'milestones' => [['title' => 'Portal refinement', 'amount' => 250000]],
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($freelancer, 'sanctum')->patchJson("/api/proposal-offers/{$offer->id}", ['status' => 'accepted'])->assertUnprocessable();

        $this->assertDatabaseHas('proposal_offers', ['id' => $offer->id, 'status' => 'expired']);
        $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'status' => 'shortlisted']);
        $this->assertDatabaseMissing('contracts', ['proposal_id' => $proposal->id]);
    }

    public function test_hiring_pipeline_contract_changes_and_manual_verification_are_authorized(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $admin = User::factory()->create();
        $client->roles()->attach(Role::firstOrCreate(['name' => 'client']));
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin']));
        $freelancer->freelancerProfile()->create(['title' => 'Laravel developer', 'skills' => ['Laravel']]);
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Build a verified client portal',
            'description' => 'Build a secure client portal with a clear project timeline and a small change-management process.',
            'category' => 'Development & IT',
            'budget_min' => 250000,
            'budget_max' => 400000,
            'status' => 'open',
        ]);

        $invite = $this->actingAs($client, 'sanctum')->postJson("/api/jobs/{$job->id}/invites", [
            'freelancer_id' => $freelancer->id,
            'message' => 'Your Laravel work looks like a good match. Please let us know if you are available.',
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/freelancer-invites')->assertOk()->assertJsonPath('data.0.id', $invite['id']);
        $this->actingAs($freelancer, 'sanctum')->patchJson("/api/freelancer-invites/{$invite['id']}", ['status' => 'accepted'])->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->actingAs($freelancer, 'sanctum')->postJson('/api/marketplace-saved-searches', [
            'name' => 'Laravel opportunities',
            'scope' => 'jobs',
            'filters' => ['q' => 'Laravel', 'category' => 'Development & IT'],
            'alerts_enabled' => true,
        ])->assertCreated()->assertJsonPath('data.alert_frequency', 'daily');

        $proposal = Proposal::create([
            'job_id' => $job->id,
            'freelancer_id' => $freelancer->id,
            'cover_letter' => 'I can deliver this client portal with a careful project plan and clear, written communication.',
            'bid_amount' => 300000,
            'delivery_days' => 14,
            'status' => 'hired',
        ]);
        $job->update(['status' => 'in_progress']);
        $contract = Contract::create([
            'job_id' => $job->id,
            'proposal_id' => $proposal->id,
            'client_id' => $client->id,
            'freelancer_id' => $freelancer->id,
            'title' => $job->title,
            'scope' => $job->description,
            'agreed_amount' => 300000,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $change = $this->actingAs($freelancer, 'sanctum')->postJson("/api/contracts/{$contract->id}/scope-changes", [
            'title' => 'Add an activity export',
            'description' => 'Add a small CSV export so the client can retain the project activity history after handover.',
            'amount_delta' => 50000,
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');
        $this->actingAs($client, 'sanctum')->patchJson("/api/contract-scope-changes/{$change['id']}", ['status' => 'accepted'])->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'agreed_amount' => 350000]);

        $this->actingAs($freelancer, 'sanctum')->postJson('/api/verification-requests', ['type' => 'identity'])
            ->assertOk()->assertJsonPath('data.status', 'pending');
        Sanctum::actingAs($admin, ['admin']);
        $this->getJson('/api/admin/verifications')->assertOk()->assertJsonPath('data.identity.0.id', $freelancer->id);
        $this->patchJson("/api/admin/users/{$freelancer->id}/identity-verification", ['status' => 'verified'])
            ->assertOk()->assertJsonPath('data.identity_verification_status', 'verified');
    }

    public function test_public_marketplace_responses_only_expose_open_jobs_and_safe_profile_fields(): void
    {
        $client = User::factory()->create(['email' => 'private-client@example.test']);
        $client->roles()->attach(Role::firstOrCreate(['name' => 'client']));
        $client->clientProfile()->create([
            'company_name' => 'Private Client Ltd.',
            'company_verification_note' => 'Internal verification detail',
        ]);
        $openJob = Job::create([
            'client_id' => $client->id,
            'title' => 'Open Laravel dashboard project',
            'description' => 'Build a polished Laravel dashboard with a clear project handover and responsive interface.',
            'category' => 'Development & IT',
            'budget_min' => 300000,
            'budget_max' => 500000,
            'status' => 'open',
        ]);
        $draftJob = Job::create([
            'client_id' => $client->id,
            'title' => 'Private draft project',
            'description' => 'This draft contains private planning details and must not be visible in the public catalogue.',
            'category' => 'Development & IT',
            'budget_min' => 300000,
            'budget_max' => 500000,
            'status' => 'draft',
        ]);
        $freelancer = User::factory()->create(['email' => 'private-freelancer@example.test']);
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        $freelancer->freelancerProfile()->create(['title' => 'Laravel developer', 'skills' => ['Laravel']]);

        $this->getJson('/api/jobs?include_closed=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $openJob->id)
            ->assertJsonMissing(['email' => $client->email])
            ->assertJsonMissing(['company_verification_note' => 'Internal verification detail']);
        $this->getJson("/api/jobs/{$draftJob->id}")->assertNotFound();
        $this->getJson("/api/freelancers/{$freelancer->id}")
            ->assertOk()
            ->assertJsonMissing(['email' => $freelancer->email]);
        $this->actingAs($client, 'sanctum')->patchJson("/api/jobs/{$openJob->id}", ['status' => 'completed'])->assertUnprocessable();
    }

    public function test_resumes_are_stored_privately_and_replacing_one_removes_the_old_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $freelancer = User::factory()->create();
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));

        $this->actingAs($freelancer, 'sanctum')->post('/api/freelancer-resume', [
            'resume' => UploadedFile::fake()->create('first-cv.pdf', 120, 'application/pdf'),
        ])->assertCreated();
        $firstPath = FreelancerResume::where('user_id', $freelancer->id)->value('storage_path');
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('public')->assertMissing($firstPath);

        $this->actingAs($freelancer, 'sanctum')->post('/api/freelancer-resume', [
            'resume' => UploadedFile::fake()->create('updated-cv.pdf', 120, 'application/pdf'),
        ])->assertCreated();
        $secondPath = FreelancerResume::where('user_id', $freelancer->id)->value('storage_path');
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);
    }
}
