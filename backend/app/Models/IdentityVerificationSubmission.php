<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdentityVerificationSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nrc_front_path',
        'nrc_back_path',
        'submitted_note',
        'status',
        'review_note',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
        'documents_purged_at',
    ];

    protected $hidden = ['nrc_front_path', 'nrc_back_path'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'documents_purged_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
