<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MilestoneSubmission extends Model
{
    use HasFactory;

    protected $fillable = ['contract_milestone_id', 'submitted_by', 'version', 'note', 'status', 'review_note', 'reviewed_by', 'submitted_at', 'reviewed_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function milestone()
    {
        return $this->belongsTo(ContractMilestone::class, 'contract_milestone_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function files()
    {
        return $this->hasMany(MilestoneSubmissionFile::class);
    }
}
