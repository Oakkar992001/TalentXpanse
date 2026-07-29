<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreelancerResume extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'original_name', 'storage_path', 'file_size'];

    protected $hidden = ['storage_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
