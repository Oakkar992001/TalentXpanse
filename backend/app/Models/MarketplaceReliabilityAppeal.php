<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceReliabilityAppeal extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'marketplace_reliability_event_id', 'reason', 'status', 'reviewed_by', 'resolution_note', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reliabilityEvent()
    {
        return $this->belongsTo(MarketplaceReliabilityEvent::class, 'marketplace_reliability_event_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
