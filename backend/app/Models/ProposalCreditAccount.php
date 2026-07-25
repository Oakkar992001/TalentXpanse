<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalCreditAccount extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'balance', 'last_monthly_grant_at'];

    protected function casts(): array
    {
        return ['last_monthly_grant_at' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
