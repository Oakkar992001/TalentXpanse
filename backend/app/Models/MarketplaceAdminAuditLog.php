<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceAdminAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['admin_user_id', 'action', 'subject_type', 'subject_id', 'summary', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected $hidden = ['metadata'];

    public function administrator()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
