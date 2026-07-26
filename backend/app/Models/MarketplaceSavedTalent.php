<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceSavedTalent extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'freelancer_profile_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function freelancerProfile()
    {
        return $this->belongsTo(FreelancerProfile::class);
    }
}
