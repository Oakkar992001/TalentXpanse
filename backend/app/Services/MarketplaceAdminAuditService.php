<?php

namespace App\Services;

use App\Models\MarketplaceAdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MarketplaceAdminAuditService
{
    public function log(User $administrator, string $action, Model $subject, string $summary, array $metadata = []): void
    {
        MarketplaceAdminAuditLog::create([
            'admin_user_id' => $administrator->id,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'summary' => $summary,
            'metadata' => $metadata ?: null,
        ]);
    }
}
