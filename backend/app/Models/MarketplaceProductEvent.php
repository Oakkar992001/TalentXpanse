<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceProductEvent extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'event', 'properties', 'occurred_at'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'occurred_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
