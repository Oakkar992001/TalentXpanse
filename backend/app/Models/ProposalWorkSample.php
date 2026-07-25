<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalWorkSample extends Model
{
    use HasFactory;

    protected $fillable = ['proposal_id', 'portfolio_item_id', 'title', 'description', 'project_url', 'image_url'];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }
}
