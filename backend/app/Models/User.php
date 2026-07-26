<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Notifications\TalentXpanseResetPassword;
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
        'notification_preferences',
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

    public function savedJobs()
    {
        return $this->hasMany(MarketplaceSavedJob::class);
    }

    public function savedTalent()
    {
        return $this->hasMany(MarketplaceSavedTalent::class);
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

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new TalentXpanseResetPassword($token));
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? url(Storage::disk('public')->url($this->profile_photo_path)) : null;
    }
}
