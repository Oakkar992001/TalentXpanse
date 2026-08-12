<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory;

    protected $table = 'marketplace_jobs';

    protected $fillable = ['client_id', 'title', 'description', 'category', 'skills', 'budget_min', 'budget_max', 'budget_type', 'duration', 'experience_level', 'status'];

    protected function casts(): array
    {
        return ['skills' => 'array'];
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }

    public function contract()
    {
        return $this->hasOne(Contract::class);
    }

    public function freelancerInvites()
    {
        return $this->hasMany(MarketplaceFreelancerInvite::class);
    }
}
