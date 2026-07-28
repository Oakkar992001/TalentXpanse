<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MilestoneSubmissionFile extends Model
{
    use HasFactory;

    protected $fillable = ['milestone_submission_id', 'uploaded_by', 'original_name', 'storage_path', 'mime_type', 'file_size'];

    protected $hidden = ['storage_path'];

    public function submission()
    {
        return $this->belongsTo(MilestoneSubmission::class, 'milestone_submission_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
