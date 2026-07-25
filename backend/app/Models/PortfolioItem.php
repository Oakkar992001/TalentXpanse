<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioItem extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'description', 'project_url', 'image_url', 'sort_order'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
