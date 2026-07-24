<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreelancerProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'bio', 'hourly_rate', 'availability', 'profile_completeness', 'skills', 'location'];

    protected function casts(): array
    {
        return ['skills' => 'array', 'availability' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
