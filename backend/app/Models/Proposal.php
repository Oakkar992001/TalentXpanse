<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    use HasFactory;

    protected $fillable = ['job_id', 'freelancer_id', 'cover_letter', 'bid_amount', 'delivery_days', 'credit_cost', 'resume_path', 'resume_name', 'status', 'client_note', 'decline_reason', 'interview_at'];

    protected $hidden = ['resume_path'];

    protected function casts(): array
    {
        return ['interview_at' => 'datetime'];
    }

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function freelancer()
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function workSamples()
    {
        return $this->hasMany(ProposalWorkSample::class);
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    public function offers()
    {
        return $this->hasMany(ProposalOffer::class)->latest();
    }

    public function latestOffer()
    {
        return $this->hasOne(ProposalOffer::class)->latestOfMany();
    }

    public function creditAllocations()
    {
        return $this->hasMany(ProposalCreditAllocation::class);
    }
}
