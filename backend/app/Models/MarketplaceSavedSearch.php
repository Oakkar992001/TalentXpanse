<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceSavedSearch extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'scope', 'filters', 'alerts_enabled', 'alert_frequency', 'last_alerted_at'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'alerts_enabled' => 'boolean', 'last_alerted_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
