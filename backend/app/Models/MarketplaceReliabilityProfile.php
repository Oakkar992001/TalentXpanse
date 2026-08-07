<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceReliabilityProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'score',
        'tier',
        'search_visibility',
        'completed_projects_count',
        'positive_reviews_count',
        'average_rating',
        'active_penalty_points',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return ['average_rating' => 'decimal:2', 'last_synced_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
