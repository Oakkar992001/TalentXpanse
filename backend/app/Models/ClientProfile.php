<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'company_name', 'company_description', 'website', 'industry', 'location', 'billing_verified', 'company_verification_status', 'company_verification_note', 'company_verification_requested_at', 'company_verified_at', 'company_verified_by'];

    protected $hidden = ['billing_verified', 'company_verification_note', 'company_verification_requested_at', 'company_verified_at', 'company_verified_by'];

    protected function casts(): array
    {
        return ['billing_verified' => 'boolean', 'company_verification_requested_at' => 'datetime', 'company_verified_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function companyVerifier()
    {
        return $this->belongsTo(User::class, 'company_verified_by');
    }
}
