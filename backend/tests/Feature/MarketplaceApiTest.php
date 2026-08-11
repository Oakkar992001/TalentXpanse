<?php

namespace Tests\Feature;

use App\Events\MarketplaceMessageCreated;
use App\Events\MarketplaceNotificationCreated;
use App\Models\Contract;
use App\Models\ContractSupportRequest;
use App\Models\Conversation;
use App\Models\FreelancerResume;
use App\Models\IdentityVerificationSubmission;
use App\Models\Job;
use App\Models\MarketplaceNotification;
use App\Models\MarketplaceReliabilityEvent;
use App\Models\PortfolioItem;
use App\Models\Proposal;
use App\Models\ProposalCreditAccount;
use App\Models\ProposalCreditAllocation;
use App\Models\ProposalCreditGrant;
use App\Models\ProposalOffer;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TalentXpanseResetPassword;
use App\Notifications\TalentXpanseVerifyEmail;
use App\Services\MarketplaceEscrowService;
use App\Services\MarketplaceNotificationService;
use App\Services\ProposalCreditService;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_validation_uses_myanmar_when_requested(): void
    {
        $this->withHeader('Accept-Language', 'my-MM,my;q=0.9')
            ->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'အီးမေးလ် လိုအပ်ပါသည်။');
    }

    public function test_member_sessions_have_a_server_enforced_expiry(): void
    {
        $this->travelTo(Carbon::parse('2026-08-07 10:00:00'));
        $member = User::factory()->create(['password' => Hash::make('member-session-password')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $member->email,
            'password' => 'member-session-password',
        ])->assertOk()->assertJsonStructure(['token', 'expires_at', 'user']);

        $token = $response->json('token');
        $accessToken = PersonalAccessToken::findToken($token);
        $this->assertTrue($accessToken->expires_at->equalTo(now()->addMinutes(480)));

        $this->travel(481)->minutes();
        $this->withToken($token)->getJson('/api/auth/user')->assertUnauthorized();
        $this->travelBack();
    }

    public function test_admin_sessions_expire_sooner_than_member_sessions(): void
    {
        $this->travelTo(Carbon::parse('2026-08-07 10:00:00'));
        $admin = User::factory()->create(['password' => Hash::make('admin-session-password')]);
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin']));

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => $admin->email,
            'password' => 'admin-session-password',
        ])->assertOk()->assertJsonStructure(['token', 'expires_at', 'user']);

        $token = $response->json('token');
        $accessToken = PersonalAccessToken::findToken($token);
        $this->assertTrue($accessToken->expires_at->equalTo(now()->addMinutes(30)));

        $this->travel(31)->minutes();
        $this->withToken($token)->getJson('/api/admin/dashboard')->assertUnauthorized();
        $this->travelBack();
    }

    public function test_free_credits_expire_after_sixty_days_and_never_roll_over_above_forty(): void
    {
        $freelancer = User::factory()->create();
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));

        $this->travelTo(Carbon::parse('2026-01-10 10:00:00'));
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/proposal-credits')
            ->assertOk()
            ->assertJsonPath('data.balance', 20)
            ->assertJsonPath('data.balance_cap', 40)
            ->assertJsonPath('data.credit_expiry_days', 60);

        $this->travelTo(Carbon::parse('2026-02-10 10:00:00'));
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/proposal-credits')
            ->assertOk()
            ->assertJsonPath('data.balance', 40);

        $this->travelTo(Carbon::parse('2026-03-05 10:00:00'));
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/proposal-credits')
            ->assertOk()
            ->assertJsonPath('data.balance', 40);

        $this->travelTo(Carbon::parse('2026-03-12 10:00:00'));
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/proposal-credits')
            ->assertOk()
            ->assertJsonPath('data.balance', 40);

        $this->assertDatabaseHas('proposal_credit_transactions', [
            'user_id' => $freelancer->id,
            'type' => 'credit_expired',
            'amount' => -20,
        ]);
        $this->travelBack();
    }

    public function test_proposals_spend_the_soonest_expiring_credits_first(): void
    {
        $freelancer = User::factory()->create();
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        $client = User::factory()->create();
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Build a high-value customer portal',
            'description' => 'Build a complete customer portal with dashboards, reliable account access, and a documented delivery handover.',
            'category' => 'Development & IT',
            'budget_min' => 500000,
            'budget_max' => 600000,
            'status' => 'open',
        ]);

        $this->travelTo(Carbon::parse('2026-01-10 10:00:00'));
        app(ProposalCreditService::class)->summaryFor($freelancer);
        $this->travelTo(Carbon::parse('2026-02-10 10:00:00'));
        app(ProposalCreditService::class)->summaryFor($freelancer);

        app(ProposalCreditService::class)->deductForProposal($freelancer, $job);

        $grants = ProposalCreditGrant::query()
            ->where('user_id', $freelancer->id)
            ->where('source', 'free_monthly')
            ->orderBy('granted_at')
            ->get();
        $this->assertSame(16, $grants->first()->remaining_amount);
        $this->assertSame(20, $grants->last()->remaining_amount);
        $this->travelBack();
    }

    public function test_client_closing_an_unhired_job_returns_its_proposal_credits_once(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $client->roles()->attach(Role::firstOrCreate(['name' => 'client']));
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Build a responsive member dashboard',
            'description' => 'Build a responsive member dashboard with practical reporting, polished navigation, and maintainable Laravel code.',
            'category' => 'Development & IT',
            'budget_min' => 200000,
            'budget_max' => 300000,
            'status' => 'open',
        ]);
        $proposal = Proposal::create([
            'job_id' => $job->id,
            'freelancer_id' => $freelancer->id,
            'cover_letter' => 'I can deliver the responsive dashboard with a careful project plan, clear progress updates, and documented handover notes.',
            'bid_amount' => 250000,
            'delivery_days' => 14,
            'status' => 'submitted',
            'credit_cost' => 2,
        ]);

        app(ProposalCreditService::class)->deductForProposal($freelancer, $job, $proposal);
        $this->assertDatabaseHas('proposal_credit_accounts', ['user_id' => $freelancer->id, 'balance' => 18]);
        $spentGrant = ProposalCreditAllocation::where('proposal_id', $proposal->id)->firstOrFail()->proposalCreditGrant;

        $this->actingAs($client, 'sanctum')->patchJson("/api/jobs/{$job->id}", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
        $this->actingAs($client, 'sanctum')->patchJson("/api/jobs/{$job->id}", ['status' => 'closed'])->assertOk();

        $this->assertDatabaseHas('proposal_credit_grants', [
            'proposal_id' => $proposal->id,
            'source' => 'proposal_refund',
            'remaining_amount' => 2,
        ]);
        $this->assertDatabaseHas('proposal_credit_accounts', ['user_id' => $freelancer->id, 'balance' => 20]);
        $this->assertDatabaseCount('proposal_credit_grants', 2);
        $refundGrant = ProposalCreditGrant::where('proposal_id', $proposal->id)->where('source', 'proposal_refund')->firstOrFail();
        $this->assertTrue($refundGrant->expires_at->equalTo($spentGrant->expires_at));
    }

    public function test_premium_credit_policy_is_ready_for_a_verified_membership_event(): void
    {
        $freelancer = User::factory()->create();
        ProposalCreditAccount::create([
            'user_id' => $freelancer->id,
            'membership_tier' => 'premium',
            'membership_expires_at' => Carbon::parse('2026-12-31'),
        ]);

        $this->travelTo(Carbon::parse('2026-01-10 10:00:00'));
        $summary = app(ProposalCreditService::class)->summaryFor($freelancer);

        $this->assertSame('premium', $summary['membership_tier']);
        $this->assertSame(60, $summary['monthly_allowance']);
        $this->assertSame(180, $summary['balance_cap']);
        $this->assertSame(180, $summary['credit_expiry_days']);
        $this->assertSame(60, $summary['balance']);
        $this->travelBack();
    }

    public function test_a_user_can_register_with_a_client_role_and_post_a_job(): void
    {
        $registration = $this->postJson('/api/auth/register', [
            'name' => 'May Zin',
            'email' => 'mayzin@example.test',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'client',
            'terms_accepted' => true,
            'privacy_accepted' => true,
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

    public function test_registration_requires_and_records_policy_acceptance(): void
    {
        $payload = [
            'name' => 'Policy Test User',
            'email' => 'policy-test@example.test',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'freelancer',
        ];

        $this->postJson('/api/auth/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['terms_accepted', 'privacy_accepted']);

        $this->postJson('/api/auth/register', [...$payload, 'terms_accepted' => true, 'privacy_accepted' => true])
            ->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'policy-test@example.test',
            'terms_version' => '2026-07-30',
        ]);
    }

    public function test_readiness_health_check_reports_application_and_database_status_without_authentication(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('application', config('app.name'))
            ->assertJsonPath('database', 'ok')
            ->assertJsonPath('cache', 'ok')
            ->assertJsonPath('queue', 'ok')
            ->assertJsonPath('storage', 'ok');
    }

    public function test_operations_check_reports_the_current_runtime_as_ready(): void
    {
        $this->assertSame(0, Artisan::call('marketplace:operations-check'));
    }

    public function test_client_and_freelancer_can_complete_the_core_marketplace_journey(): void
    {
        Notification::fake();

        $clientRegistration = $this->postJson('/api/auth/register', [
            'name' => 'May Thiri',
            'email' => 'may.thiri@example.test',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'client',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ])->assertCreated();
        $client = $clientRegistration->json('user');
        $clientToken = $clientRegistration->json('token');

        $freelancerRegistration = $this->postJson('/api/auth/register', [
            'name' => 'Ko Min',
            'email' => 'ko.min@example.test',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'freelancer',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ])->assertCreated();
        $freelancer = $freelancerRegistration->json('user');
        $freelancerToken = $freelancerRegistration->json('token');
        $asClient = function () use ($clientToken) {
            app('auth')->forgetGuards();

            return $this->flushHeaders()->withToken($clientToken);
        };
        $asFreelancer = function () use ($freelancerToken) {
            app('auth')->forgetGuards();

            return $this->flushHeaders()->withToken($freelancerToken);
        };

        $asClient()->getJson('/api/auth/user')->assertOk()->assertJsonPath('user.id', $client['id']);
        $asFreelancer()->getJson('/api/auth/user')->assertOk()->assertJsonPath('user.id', $freelancer['id']);

        $job = $asClient()->postJson('/api/jobs', [
            'title' => 'Build a bilingual membership dashboard',
            'description' => 'Build a responsive English and Myanmar membership dashboard with clear navigation, account states, and maintainable Laravel integration.',
            'category' => 'Development & IT',
            'skills' => ['Laravel', 'React', 'MySQL'],
            'budget_min' => 450000,
            'budget_max' => 600000,
            'budget_type' => 'fixed',
            'duration' => '2 to 4 weeks',
            'experience_level' => 'intermediate',
        ])->assertCreated()->json('data');

        $asFreelancer()->getJson('/api/search?scope=jobs&q=bilingual')
            ->assertOk()
            ->assertJsonPath('data.jobs.0.id', $job['id']);

        $proposal = $asFreelancer()->postJson("/api/jobs/{$job['id']}/proposals", [
            'cover_letter' => 'I build responsive Laravel and React dashboards with bilingual interface considerations, clear handover notes, and practical quality checks for every milestone.',
            'bid_amount' => 550000,
            'delivery_days' => 21,
        ])->assertCreated()
            ->assertJsonPath('proposal_credits.balance', 16)
            ->json('data');

        $conversation = $asClient()->postJson("/api/proposals/{$proposal['id']}/conversation")
            ->assertCreated()
            ->assertJsonPath('data.type', 'proposal')
            ->json('data');
        $asFreelancer()->postJson("/api/conversations/{$conversation['id']}/messages", [
            'body' => 'I can start this week and will share a tested mobile-first delivery plan before implementation begins.',
        ])->assertCreated();

        $asClient()->patchJson("/api/proposals/{$proposal['id']}", ['status' => 'hired'])
            ->assertOk()
            ->assertJsonPath('data.status', 'hired');

        $contract = Contract::where('proposal_id', $proposal['id'])->firstOrFail();
        $this->assertDatabaseHas('conversations', ['id' => $conversation['id'], 'type' => 'project']);
        $asClient()->getJson("/api/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation['id']);

        $milestone = $asClient()->postJson("/api/contracts/{$contract->id}/milestones", [
            'title' => 'Dashboard implementation and handover',
            'description' => 'Deliver the responsive dashboard, bilingual interface states, source code, and concise handover documentation.',
            'amount' => 550000,
            'due_date' => now()->addWeeks(3)->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.client_total_amount', 605000)
            ->json('data');

        $asFreelancer()->patchJson("/api/milestones/{$milestone['id']}", ['action' => 'start'])->assertOk();
        $asFreelancer()->postJson("/api/milestones/{$milestone['id']}/submissions", [
            'note' => 'The bilingual dashboard, setup notes, and source handover are ready for your review.',
        ])->assertCreated()->assertJsonPath('data.status', 'submitted');
        $asClient()->patchJson("/api/milestones/{$milestone['id']}", ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $asFreelancer()->postJson("/api/contracts/{$contract->id}/request-completion", [
            'note' => 'The final source files and bilingual handover notes are included in the approved delivery.',
        ])->assertOk();
        $asClient()->postJson("/api/contracts/{$contract->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $asClient()->postJson("/api/contracts/{$contract->id}/reviews", [
            'rating' => 5,
            'comment' => 'Clear communication, thoughtful work, and a complete handover.',
        ])->assertCreated();
        $asFreelancer()->postJson("/api/contracts/{$contract->id}/reviews", [
            'rating' => 5,
            'comment' => 'A well-prepared client with fast and practical feedback.',
        ])->assertCreated();

        $asFreelancer()->getJson('/api/proposal-credits')
            ->assertOk()
            ->assertJsonPath('data.balance', 16);
        $asFreelancer()->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonFragment(['type' => 'proposal_hired'])
            ->assertJsonFragment(['type' => 'milestone_approved', 'url' => "/projects/{$contract->id}?milestone={$milestone['id']}&focus=milestone"])
            ->assertJsonFragment(['type' => 'contract_completed', 'url' => "/projects/{$contract->id}?focus=completion"]);
        $asClient()->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonFragment(['type' => 'proposal_received'])
            ->assertJsonFragment(['type' => 'message_received', 'url' => "/messages?conversation={$conversation['id']}"])
            ->assertJsonFragment(['type' => 'milestone_submitted', 'url' => "/projects/{$contract->id}?milestone={$milestone['id']}&focus=milestone"]);

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'status' => 'completed']);
        $this->assertDatabaseHas('marketplace_jobs', ['id' => $job['id'], 'status' => 'completed']);
        $this->assertDatabaseCount('contract_reviews', 2);
        $this->assertSame($client['id'], $contract->client_id);
        $this->assertSame($freelancer['id'], $contract->freelancer_id);
    }

    public function test_a_user_can_mark_only_their_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = MarketplaceNotification::create([
            'user_id' => $user->id,
            'type' => 'message_received',
            'title' => 'New message',
            'body' => 'A project message is waiting.',
            'url' => '/messages',
        ]);
        $otherNotification = MarketplaceNotification::create([
            'user_id' => $otherUser->id,
            'type' => 'message_received',
            'title' => 'Private message',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonStructure(['data' => ['id', 'read_at']]);

        $this->assertNotNull($notification->fresh()->read_at);
        $this->patchJson("/api/notifications/{$otherNotification->id}/read")->assertForbidden();
    }

    public function test_marketplace_updates_are_broadcast_only_to_the_recipient_private_channel(): void
    {
        Event::fake([MarketplaceNotificationCreated::class, MarketplaceMessageCreated::class]);
        $recipient = User::factory()->create();

        app(MarketplaceNotificationService::class)->send($recipient, 'message_received', 'New message', 'A message is waiting.');
        MarketplaceMessageCreated::dispatch(42, $recipient->id);

        Event::assertDispatched(MarketplaceNotificationCreated::class, function (MarketplaceNotificationCreated $event) use ($recipient) {
            return $event->notification->user_id === $recipient->id
                && $event->broadcastOn()[0]->name === "private-marketplace.user.{$recipient->id}";
        });
        Event::assertDispatched(MarketplaceMessageCreated::class, function (MarketplaceMessageCreated $event) use ($recipient) {
            return $event->recipientId === $recipient->id
                && $event->broadcastOn()[0]->name === "private-marketplace.user.{$recipient->id}";
        });
    }

    public function test_users_can_only_authorize_their_own_realtime_channel(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $broadcaster = app(BroadcastManager::class);

        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'test-reverb-key',
            'secret' => 'test-reverb-secret',
            'app_id' => 'test-reverb-app',
            'options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
            'client_options' => [],
        ]);
        $broadcaster->forgetDrivers();
        require base_path('routes/channels.php');

        try {
            $this->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-marketplace.user.{$user->id}",
            ])->assertOk();

            $this->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-marketplace.user.{$otherUser->id}",
            ])->assertForbidden();
        } finally {
            config()->set('broadcasting.default', 'null');
            $broadcaster->forgetDrivers();
        }
    }

    public function test_users_can_verify_their_email_address_from_a_signed_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/email/verification-notification')->assertOk()->assertJsonPath('message', 'Verification email sent.');
        Notification::assertSentTo($user, TalentXpanseVerifyEmail::class);

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
            'terms_accepted' => true,
            'privacy_accepted' => true,
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

        $this->actingAs($freelancer, 'sanctum')->postJson("/api/jobs/{$job->id}/proposals", [...$payload, 'bid_amount' => 199000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bid_amount');
        $this->actingAs($freelancer, 'sanctum')->postJson("/api/jobs/{$job->id}/proposals", [...$payload, 'bid_amount' => 401000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bid_amount');

        $this->actingAs($freelancer, 'sanctum')->postJson("/api/jobs/{$job->id}/proposals", $payload)
            ->assertCreated()
            ->assertJsonPath('data.credit_cost', 2)
            ->assertJsonPath('proposal_credits.balance', 18);
        $this->actingAs($freelancer, 'sanctum')->postJson("/api/jobs/{$job->id}/proposals", $payload)->assertUnprocessable();
        $this->actingAs($freelancer, 'sanctum')->getJson("/api/jobs/{$job->id}/my-proposal")
            ->assertOk()
            ->assertJsonPath('data.bid_amount', 350000);

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
        $proposal = Proposal::create(['job_id' => $job->id, 'freelancer_id' => $freelancer->id, 'cover_letter' => 'I can build a practical proposal tracker with clear statuses, accessible controls, and careful attention to the freelancer workflow.', 'bid_amount' => 250000, 'delivery_days' => 12, 'status' => 'interviewing', 'credit_cost' => 2]);

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
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $secondFreelancer->id, 'type' => 'proposal_not_selected']);
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

    public function test_a_freelancer_can_attach_a_proposal_specific_cv_without_replacing_their_profile_cv(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $client->roles()->attach(Role::firstOrCreate(['name' => 'client']));
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Build a mobile marketplace experience',
            'description' => 'Create a polished mobile marketplace experience with a simple proposal journey and accessible visual design.',
            'category' => 'Development & IT',
            'budget_min' => 200000,
            'budget_max' => 360000,
            'status' => 'open',
        ]);
        $profileResume = FreelancerResume::create([
            'user_id' => $freelancer->id,
            'original_name' => 'profile-cv.pdf',
            'storage_path' => "resumes/{$freelancer->id}/profile-cv.pdf",
            'file_size' => 120,
        ]);
        Storage::disk('local')->put($profileResume->storage_path, 'profile CV');

        $response = $this->actingAs($freelancer, 'sanctum')->post("/api/jobs/{$job->id}/proposals", [
            'cover_letter' => 'I can build a focused mobile marketplace experience with clear proposal flows, accessible controls, and thoughtful visual hierarchy.',
            'bid_amount' => 300000,
            'delivery_days' => 14,
            'attach_resume' => false,
            'proposal_resume' => UploadedFile::fake()->create('tailored-cv.pdf', 300, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.resume_name', 'tailored-cv.pdf');

        $proposal = Proposal::findOrFail($response->json('data.id'));
        $this->assertNotSame($profileResume->storage_path, $proposal->resume_path);
        Storage::disk('local')->assertExists($profileResume->storage_path);
        Storage::disk('local')->assertExists($proposal->resume_path);
    }

    public function test_a_saved_cv_is_copied_to_a_proposal_before_it_is_removed_from_the_profile(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $client->roles()->attach(Role::firstOrCreate(['name' => 'client']));
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Create a responsive business portal',
            'description' => 'Build a responsive business portal with clear user flows, a maintainable Laravel backend, and a polished dashboard interface.',
            'category' => 'Development & IT',
            'budget_min' => 250000,
            'budget_max' => 450000,
            'status' => 'open',
        ]);
        $resume = FreelancerResume::create([
            'user_id' => $freelancer->id,
            'original_name' => 'saved-cv.pdf',
            'storage_path' => "resumes/{$freelancer->id}/saved-cv.pdf",
            'file_size' => 120,
        ]);
        Storage::disk('local')->put($resume->storage_path, 'saved CV');

        $response = $this->actingAs($freelancer, 'sanctum')->postJson("/api/jobs/{$job->id}/proposals", [
            'cover_letter' => 'I can create a reliable responsive portal with thoughtful user flows, a maintainable backend, and a clear delivery plan for your team.',
            'bid_amount' => 350000,
            'delivery_days' => 18,
            'attach_resume' => true,
        ])->assertCreated()->assertJsonPath('data.resume_name', 'saved-cv.pdf');

        $proposal = Proposal::findOrFail($response->json('data.id'));
        $this->assertNotSame($resume->storage_path, $proposal->resume_path);
        Storage::disk('local')->assertExists($proposal->resume_path);

        $this->actingAs($freelancer, 'sanctum')->delete('/api/freelancer-resume')->assertNoContent();
        Storage::disk('local')->assertMissing($resume->storage_path);
        Storage::disk('local')->assertExists($proposal->resume_path);
        $this->actingAs($client, 'sanctum')->get("/api/proposals/{$proposal->id}/resume")->assertOk();
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
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/reliability?role=freelancer')->assertOk()->assertJsonPath('data.freelancer.score', 56)->assertJsonPath('data.freelancer.tier', 'building');
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

    public function test_escrow_ledger_records_funding_release_and_disputed_refund_without_a_provider_integration(): void
    {
        $paymentConfig = config('marketplace_payments');
        config()->set('marketplace_payments.enabled', true);
        config()->set('marketplace_payments.provider', 'sandbox-ledger');

        try {
            $client = User::factory()->create();
            $freelancer = User::factory()->create();
            $admin = User::factory()->create();
            $job = Job::create(['client_id' => $client->id, 'title' => 'Create an escrow-ready marketplace flow', 'description' => 'Create a milestone workflow that keeps an auditable internal ledger ready for a future payment gateway integration.', 'category' => 'Development & IT', 'budget_min' => 100000, 'budget_max' => 200000, 'status' => 'in_progress']);
            $proposal = Proposal::create(['job_id' => $job->id, 'freelancer_id' => $freelancer->id, 'cover_letter' => 'I can deliver a reliable financial workflow with clear audit records, careful validation, and safe handling of payment state changes.', 'bid_amount' => 100000, 'delivery_days' => 10, 'status' => 'hired']);
            $contract = Contract::create(['job_id' => $job->id, 'proposal_id' => $proposal->id, 'client_id' => $client->id, 'freelancer_id' => $freelancer->id, 'title' => $job->title, 'scope' => $job->description, 'agreed_amount' => 200000, 'status' => 'active', 'started_at' => now()]);
            $firstMilestone = $contract->milestones()->create(['title' => 'Escrow release milestone', 'amount' => 100000, 'platform_fee_basis_points' => 1000, 'client_fee_amount' => 10000, 'client_total_amount' => 110000, 'status' => 'planned', 'funding_status' => 'awaiting_funding']);
            $escrow = app(MarketplaceEscrowService::class);

            $funding = $escrow->recordFunding($firstMilestone, 'sandbox-ledger', 'funding-1001');
            $this->assertSame(110000, $funding->ledgerEntries->where('entry_type', 'debit')->sum('amount'));
            $this->assertSame(110000, $funding->ledgerEntries->where('entry_type', 'credit')->sum('amount'));
            $this->assertCount(3, $funding->ledgerEntries);
            $this->assertSame($funding->id, $escrow->recordFunding($firstMilestone, 'sandbox-ledger', 'funding-1001')->id);
            $this->assertDatabaseHas('contract_milestones', ['id' => $firstMilestone->id, 'funding_status' => 'funded']);

            $firstMilestone->update(['status' => 'approved']);
            $release = $escrow->recordRelease($firstMilestone, 'sandbox-ledger', 'release-1001');
            $this->assertSame(110000, $release->ledgerEntries->where('entry_type', 'debit')->sum('amount'));
            $this->assertSame(110000, $release->ledgerEntries->where('entry_type', 'credit')->sum('amount'));
            $this->assertCount(4, $release->ledgerEntries);
            $this->assertDatabaseHas('contract_milestones', ['id' => $firstMilestone->id, 'funding_status' => 'released']);

            $secondMilestone = $contract->milestones()->create(['title' => 'Disputed escrow milestone', 'amount' => 100000, 'platform_fee_basis_points' => 1000, 'client_fee_amount' => 10000, 'client_total_amount' => 110000, 'status' => 'planned', 'funding_status' => 'awaiting_funding']);
            $escrow->recordFunding($secondMilestone, 'sandbox-ledger', 'funding-1002');
            $supportRequest = ContractSupportRequest::create(['contract_id' => $contract->id, 'opened_by' => $client->id, 'reason' => 'payment_issue', 'details' => 'The milestone funding needs to remain protected while the project partners and TalentXpanse review the agreed delivery outcome.', 'status' => 'open']);
            $contract->update(['payment_hold_status' => 'on_hold', 'payment_hold_note' => 'Payment dispute is under review.', 'payment_hold_at' => now()]);
            $dispute = $escrow->openDispute($contract, $supportRequest);
            $this->assertNotNull($dispute);
            $this->assertDatabaseHas('contract_milestones', ['id' => $secondMilestone->id, 'funding_status' => 'disputed']);

            $refund = $escrow->recordRefund($secondMilestone, 'sandbox-ledger', 'refund-1002');
            $this->assertSame(110000, $refund->ledgerEntries->where('entry_type', 'debit')->sum('amount'));
            $this->assertSame(110000, $refund->ledgerEntries->where('entry_type', 'credit')->sum('amount'));
            $this->assertCount(3, $refund->ledgerEntries);
            $this->assertDatabaseHas('contract_milestones', ['id' => $secondMilestone->id, 'funding_status' => 'refunded']);
            $contract->update(['payment_hold_status' => 'clear']);
            $escrow->resumeDisputedFunds($contract, $admin, 'The disputed milestone was refunded and the remaining project balance can continue.');
            $this->assertDatabaseHas('marketplace_payment_disputes', ['id' => $dispute->id, 'status' => 'resolved', 'resolution' => 'refund']);
            $this->assertDatabaseCount('marketplace_escrow_ledger_entries', 13);

            $entry = $funding->ledgerEntries->firstWhere('account', 'escrow_cash');
            try {
                $entry->update(['amount' => 1]);
                $this->fail('Expected escrow ledger updates to be rejected.');
            } catch (LogicException) {
                $this->assertDatabaseHas('marketplace_escrow_ledger_entries', ['id' => $entry->id, 'amount' => 110000]);
            }

            try {
                $entry->delete();
                $this->fail('Expected escrow ledger deletions to be rejected.');
            } catch (LogicException) {
                $this->assertDatabaseHas('marketplace_escrow_ledger_entries', ['id' => $entry->id]);
            }
        } finally {
            config()->set('marketplace_payments', $paymentConfig);
        }
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
        $higherBudgetJob = Job::create(['client_id' => $client->id, 'title' => 'React checkout rebuild', 'description' => 'Build a reliable React checkout experience for a growing local retailer.', 'category' => 'Development & IT', 'skills' => ['React'], 'budget_min' => 750000, 'budget_max' => 900000, 'status' => 'open']);
        $higherRateFreelancer = User::factory()->create(['name' => 'Thiri Win']);
        $higherRateFreelancer->roles()->attach($freelancerRole);
        $higherRateFreelancer->freelancerProfile()->create(['title' => 'Senior React developer', 'skills' => ['React'], 'location' => 'Mandalay', 'hourly_rate' => 45000, 'availability' => true]);

        $this->actingAs($client, 'sanctum')->getJson('/api/search?q=React&scope=all')->assertOk()->assertJsonCount(2, 'data.jobs')->assertJsonCount(2, 'data.talent')->assertJsonFragment(['name' => 'Mya Thiri']);
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/search?q=React&scope=all')->assertOk()->assertJsonCount(2, 'data.jobs')->assertJsonCount(2, 'data.talent')->assertJsonFragment(['name' => 'Mya Thiri']);
        $this->actingAs($client, 'sanctum')->getJson('/api/search?scope=jobs&category=Development%20%26%20IT&skill=React&min_budget=200000&max_budget=600000')->assertOk()->assertJsonCount(1, 'data.jobs')->assertJsonPath('data.pagination.jobs.total', 1);
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/search?scope=talent&skill=React&location=Yang&availability=available')->assertOk()->assertJsonCount(1, 'data.talent')->assertJsonPath('data.pagination.talent.total', 1);
        $this->actingAs($client, 'sanctum')->getJson('/api/search?scope=jobs&skill=React&sort=budget_high')->assertOk()->assertJsonPath('data.jobs.0.id', $higherBudgetJob->id);
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/search?scope=talent&skill=React&sort=rate_high')->assertOk()->assertJsonPath('data.talent.0.user_id', $higherRateFreelancer->id);
        $this->getJson("/api/freelancers/{$freelancer->id}")->assertOk()->assertJsonPath('data.freelancer_profile.title', 'React developer');
    }

    public function test_freelancer_profile_readiness_is_actionable_and_consistent_in_settings(): void
    {
        $freelancer = User::factory()->create(['active_role' => 'freelancer']);
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));

        $this->actingAs($freelancer, 'sanctum')->putJson('/api/freelancer-profile', [
            'title' => 'Laravel developer',
            'experience_level' => 'intermediate',
            'bio' => 'I build reliable marketplace and business tools for local teams.',
            'hourly_rate' => 50000,
            'skills' => ['Laravel', 'React'],
            'location' => 'Yangon, Myanmar',
        ])->assertOk()
            ->assertJsonPath('data.profile_completeness', 70)
            ->assertJsonPath('data.freelancer_profile.experience_level', 'intermediate')
            ->assertJsonPath('data.profile_checklist.0.key', 'photo')
            ->assertJsonPath('data.profile_checklist.1.completed', true);

        $this->actingAs($freelancer, 'sanctum')->getJson('/api/account-settings')
            ->assertOk()
            ->assertJsonPath('data.freelancer_profile.profile_completeness', 70);
        $this->getJson("/api/freelancers/{$freelancer->id}")
            ->assertOk()
            ->assertJsonPath('data.freelancer_profile.experience_level', 'intermediate');
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

        Storage::fake('local');
        $this->actingAs($freelancer, 'sanctum')->post('/api/verification-requests', [
            'type' => 'identity',
            'nrc_front' => $this->nrcImage('nrc-front.png'),
            'nrc_back' => $this->nrcImage('nrc-back.png'),
        ])->assertCreated()->assertJsonPath('data.status', 'pending');
        Sanctum::actingAs($admin, ['admin']);
        $this->getJson('/api/admin/verifications')->assertOk()->assertJsonPath('data.identity.0.user.id', $freelancer->id);
        $this->patchJson("/api/admin/users/{$freelancer->id}/identity-verification", ['status' => 'verified'])
            ->assertOk()->assertJsonPath('data.identity_verification_status', 'verified');
    }

    public function test_identity_documents_are_private_audited_and_purged_after_review(): void
    {
        Storage::fake('local');

        $member = User::factory()->create();
        $administrator = User::factory()->create();
        $member->roles()->attach([
            Role::firstOrCreate(['name' => 'client'])->id,
            Role::firstOrCreate(['name' => 'freelancer'])->id,
        ]);
        $administrator->roles()->attach(Role::firstOrCreate(['name' => 'admin']));

        $this->actingAs($member, 'sanctum')->post('/api/verification-requests', [
            'type' => 'identity',
            'note' => 'My name is abbreviated in my profile.',
            'nrc_front' => $this->nrcImage('nrc-front.png'),
            'nrc_back' => $this->nrcImage('nrc-back.png'),
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $submission = IdentityVerificationSubmission::query()->where('user_id', $member->id)->firstOrFail();
        $frontPath = $submission->nrc_front_path;
        $backPath = $submission->nrc_back_path;
        Storage::disk('local')->assertExists($frontPath);
        Storage::disk('local')->assertExists($backPath);
        $this->actingAs($member, 'sanctum')->getJson('/api/account-settings')
            ->assertOk()
            ->assertJsonPath('data.identity_verification_submission_pending', true);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/admin/identity-verification-submissions/{$submission->id}/documents/front")
            ->assertUnauthorized();

        Sanctum::actingAs($administrator, ['admin']);
        $this->getJson('/api/admin/verifications')
            ->assertOk()
            ->assertJsonPath('data.identity.0.id', $submission->id)
            ->assertJsonMissing(['nrc_front_path' => $submission->nrc_front_path])
            ->assertJsonMissing(['nrc_back_path' => $submission->nrc_back_path]);
        $this->getJson("/api/admin/identity-verification-submissions/{$submission->id}/documents/front")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('marketplace_admin_audit_logs', [
            'admin_user_id' => $administrator->id,
            'action' => 'identity_verification.document_accessed',
            'subject_type' => 'IdentityVerificationSubmission',
            'subject_id' => $submission->id,
        ]);

        $this->patchJson("/api/admin/users/{$member->id}/identity-verification", ['status' => 'verified'])
            ->assertOk()
            ->assertJsonPath('data.identity_verification_status', 'verified');

        $submission->refresh();
        $this->assertSame('verified', $submission->status);
        $this->assertNull($submission->nrc_front_path);
        $this->assertNull($submission->nrc_back_path);
        $this->assertNotNull($submission->documents_purged_at);
        Storage::disk('local')->assertMissing([$frontPath, $backPath]);
        $this->getJson("/api/admin/identity-verification-submissions/{$submission->id}/documents/front")->assertNotFound();
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

        $this->actingAs($freelancer, 'sanctum')->delete('/api/freelancer-resume')->assertNoContent();
        $this->assertDatabaseMissing('freelancer_resumes', ['user_id' => $freelancer->id]);
        Storage::disk('local')->assertMissing($secondPath);
    }

    public function test_a_cancellation_concern_stays_neutral_until_an_administrator_confirms_it(): void
    {
        $client = User::factory()->create(['active_role' => 'client']);
        $freelancer = User::factory()->create(['active_role' => 'freelancer']);
        $admin = User::factory()->create(['active_role' => 'admin']);
        $client->roles()->attach(Role::firstOrCreate(['name' => 'client']));
        $freelancer->roles()->attach(Role::firstOrCreate(['name' => 'freelancer']));
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin']));
        $job = Job::create([
            'client_id' => $client->id,
            'title' => 'Build a reliable customer portal',
            'description' => 'Build a customer portal with clear milestones, responsive pages, and maintainable delivery documentation.',
            'category' => 'Development & IT',
            'budget_min' => 250000,
            'budget_max' => 350000,
            'status' => 'in_progress',
        ]);
        $proposal = Proposal::create([
            'job_id' => $job->id,
            'freelancer_id' => $freelancer->id,
            'cover_letter' => 'I will provide clear status updates, structured delivery notes, and a careful handover for this customer portal.',
            'bid_amount' => 300000,
            'delivery_days' => 21,
            'status' => 'hired',
        ]);
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

        $this->actingAs($client, 'sanctum')->postJson("/api/contracts/{$contract->id}/close", [
            'reason_code' => 'freelancer_no_show',
            'reason' => 'The freelancer has not responded to the agreed project check-ins or provided a workable delivery plan.',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $event = MarketplaceReliabilityEvent::where('user_id', $freelancer->id)->firstOrFail();
        $this->assertSame('pending', $event->status);
        $this->actingAs($freelancer, 'sanctum')->getJson('/api/reliability?role=freelancer')
            ->assertOk()
            ->assertJsonPath('data.freelancer.score', 50)
            ->assertJsonPath('data.freelancer.search_visibility', 'standard')
            ->assertJsonPath('data.freelancer.recent_events.0.status', 'pending');

        Sanctum::actingAs($admin, ['admin']);
        $this->getJson('/api/admin/reliability')->assertOk()->assertJsonPath('data.metrics.pending', 1);
        $this->patchJson("/api/admin/reliability-events/{$event->id}", [
            'status' => 'confirmed',
            'resolution_note' => 'The project record and communication history support a confirmed no-show concern.',
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->actingAs($freelancer, 'sanctum')->getJson('/api/reliability?role=freelancer')
            ->assertOk()
            ->assertJsonPath('data.freelancer.score', 42)
            ->assertJsonPath('data.freelancer.search_visibility', 'reduced')
            ->assertJsonPath('data.freelancer.recent_events.0.resolution_note', 'The project record and communication history support a confirmed no-show concern.');
    }

    private function nrcImage(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9n1i8AAAAASUVORK5CYII='));
    }
}
