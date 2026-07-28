<?php

namespace App\Services;

use App\Models\User;

class ProfileReadinessService
{
    public function freelancerChecklist(User $user): array
    {
        $user->loadMissing('freelancerProfile', 'portfolioItems', 'freelancerResume');
        $profile = $user->freelancerProfile;

        return [
            ['key' => 'photo', 'label' => 'Add a profile photo', 'completed' => filled($user->profile_photo_path), 'weight' => 15],
            ['key' => 'title', 'label' => 'Add a professional title', 'completed' => filled($profile?->title), 'weight' => 15],
            ['key' => 'bio', 'label' => 'Write an introduction', 'completed' => filled($profile?->bio), 'weight' => 20],
            ['key' => 'rate', 'label' => 'Set an hourly rate', 'completed' => filled($profile?->hourly_rate), 'weight' => 10],
            ['key' => 'skills', 'label' => 'Add your key skills', 'completed' => ! empty($profile?->skills), 'weight' => 15],
            ['key' => 'location', 'label' => 'Add your location', 'completed' => filled($profile?->location), 'weight' => 10],
            ['key' => 'portfolio', 'label' => 'Add a work sample', 'completed' => $user->portfolioItems->isNotEmpty(), 'weight' => 10],
            ['key' => 'resume', 'label' => 'Upload a CV', 'completed' => filled($user->freelancerResume?->storage_path), 'weight' => 5],
        ];
    }

    public function completion(array $checklist): int
    {
        return collect($checklist)->where('completed', true)->sum('weight');
    }
}
