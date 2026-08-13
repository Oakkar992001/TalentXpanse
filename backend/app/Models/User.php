<?php

namespace App\Models;

use App\Notifications\TalentXpanseResetPassword;
use App\Notifications\TalentXpanseVerifyEmail;
use App\Support\MarketplaceStorage;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'profile_photo_path',
        'active_role',
        'password',
        'status',
        'identity_verification_status',
        'identity_verification_note',
        'identity_verification_requested_at',
        'identity_verified_at',
        'identity_verified_by',
        'notification_preferences',
        'terms_version',
        'terms_accepted_at',
        'privacy_accepted_at',
        'onboarding_rewarded_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $appends = ['profile_photo_url'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email',
        'email_verified_at',
        'profile_photo_path',
        'active_role',
        'status',
        'identity_verification_status',
        'identity_verification_note',
        'identity_verification_requested_at',
        'identity_verified_at',
        'identity_verified_by',
        'notification_preferences',
        'onboarding_rewarded_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'identity_verification_requested_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'onboarding_rewarded_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function freelancerProfile()
    {
        return $this->hasOne(FreelancerProfile::class);
    }

    public function clientProfile()
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function clientJobs()
    {
        return $this->hasMany(Job::class, 'client_id');
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class, 'freelancer_id');
    }

    public function proposalCreditAccount()
    {
        return $this->hasOne(ProposalCreditAccount::class);
    }

    public function proposalCreditTransactions()
    {
        return $this->hasMany(ProposalCreditTransaction::class);
    }

    public function proposalCreditGrants()
    {
        return $this->hasMany(ProposalCreditGrant::class);
    }

    public function reliabilityProfiles()
    {
        return $this->hasMany(MarketplaceReliabilityProfile::class);
    }

    public function reliabilityEvents()
    {
        return $this->hasMany(MarketplaceReliabilityEvent::class);
    }

    public function savedJobs()
    {
        return $this->hasMany(MarketplaceSavedJob::class);
    }

    public function savedTalent()
    {
        return $this->hasMany(MarketplaceSavedTalent::class);
    }

    public function savedSearches()
    {
        return $this->hasMany(MarketplaceSavedSearch::class);
    }

    public function freelancerInvites()
    {
        return $this->hasMany(MarketplaceFreelancerInvite::class, 'freelancer_id');
    }

    public function identityVerifier()
    {
        return $this->belongsTo(self::class, 'identity_verified_by');
    }

    public function identityVerificationSubmissions()
    {
        return $this->hasMany(IdentityVerificationSubmission::class);
    }

    public function portfolioItems()
    {
        return $this->hasMany(PortfolioItem::class)->orderBy('sort_order')->latest('id');
    }

    public function freelancerResume()
    {
        return $this->hasOne(FreelancerResume::class);
    }

    public function clientConversations()
    {
        return $this->hasMany(Conversation::class, 'client_id');
    }

    public function freelancerConversations()
    {
        return $this->hasMany(Conversation::class, 'freelancer_id');
    }

    public function marketplaceNotifications()
    {
        return $this->hasMany(MarketplaceNotification::class);
    }

    public function oauthIdentities()
    {
        return $this->hasMany(OauthIdentity::class);
    }

    public function marketplaceFeedback()
    {
        return $this->hasMany(MarketplaceFeedback::class);
    }

    public function marketplaceProductEvents()
    {
        return $this->hasMany(MarketplaceProductEvent::class);
    }

    public function reliabilityAppeals()
    {
        return $this->hasMany(MarketplaceReliabilityAppeal::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new TalentXpanseResetPassword($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new TalentXpanseVerifyEmail);
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? url(Storage::disk(MarketplaceStorage::publicDisk())->url($this->profile_photo_path)) : null;
    }
}
