<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceReliabilityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'event_type',
        'points',
        'status',
        'reason_code',
        'source_type',
        'source_id',
        'details',
        'metadata',
        'reviewed_by',
        'reviewed_at',
        'effective_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
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
