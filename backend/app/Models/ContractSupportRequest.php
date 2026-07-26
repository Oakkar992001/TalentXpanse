<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractSupportRequest extends Model
{
    use HasFactory;

    protected $fillable = ['contract_id', 'opened_by', 'reason', 'details', 'status', 'handled_by', 'resolution_note', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
