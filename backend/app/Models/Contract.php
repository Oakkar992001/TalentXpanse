<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = ['job_id', 'proposal_id', 'client_id', 'freelancer_id', 'title', 'scope', 'agreed_amount', 'status', 'payment_hold_status', 'payment_hold_note', 'payment_hold_at', 'payment_hold_by', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['payment_hold_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function freelancer()
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function milestones()
    {
        return $this->hasMany(ContractMilestone::class);
    }

    public function reviews()
    {
        return $this->hasMany(ContractReview::class);
    }

    public function supportRequests()
    {
        return $this->hasMany(ContractSupportRequest::class);
    }

    public function paymentRecords()
    {
        return $this->hasMany(MarketplacePaymentRecord::class);
    }

    public function paymentHoldHandler()
    {
        return $this->belongsTo(User::class, 'payment_hold_by');
    }
}
