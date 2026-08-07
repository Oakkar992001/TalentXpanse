<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalCreditAccount extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'balance', 'membership_tier', 'membership_expires_at', 'last_monthly_grant_at'];

    protected function casts(): array
    {
        return [
            'last_monthly_grant_at' => 'date',
            'membership_expires_at' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
