<?php

namespace Database\Seeders;

use App\Models\ClientProfile;
use App\Models\FreelancerProfile;
use App\Models\Job;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $clientRole = Role::firstOrCreate(['name' => 'client']);
        $freelancerRole = Role::firstOrCreate(['name' => 'freelancer']);

        $client = User::firstOrCreate(
            ['email' => 'ayechan@talentxpanse.test'],
            ['name' => 'Aye Chan', 'password' => Hash::make('password')]
        );
        $client->roles()->syncWithoutDetaching([$clientRole->id]);
        ClientProfile::updateOrCreate(['user_id' => $client->id], [
            'company_name' => 'Mingalar Studio',
            'company_description' => 'A growing Yangon product studio building useful local services.',
            'billing_verified' => true,
        ]);

        $freelancers = collect([
            ['name' => 'Nandar Win', 'email' => 'nandar@talentxpanse.test', 'title' => 'Product Designer', 'hourly_rate' => 25000, 'skills' => ['Figma', 'UX Research', 'Design Systems']],
            ['name' => 'Ko Min Htet', 'email' => 'minhtet@talentxpanse.test', 'title' => 'Laravel & React Developer', 'hourly_rate' => 32000, 'skills' => ['Laravel', 'React', 'MySQL']],
            ['name' => 'Thiri Moe', 'email' => 'thiri@talentxpanse.test', 'title' => 'Burmese Content Writer', 'hourly_rate' => 18000, 'skills' => ['Copywriting', 'Translation', 'SEO']],
        ])->map(function (array $data) use ($freelancerRole) {
            $user = User::firstOrCreate(['email' => $data['email']], ['name' => $data['name'], 'password' => Hash::make('password')]);
            $user->roles()->syncWithoutDetaching([$freelancerRole->id]);
            FreelancerProfile::updateOrCreate(['user_id' => $user->id], [
                'title' => $data['title'], 'bio' => "Experienced {$data['title']} based in Myanmar.", 'hourly_rate' => $data['hourly_rate'],
                'availability' => true, 'profile_completeness' => 85, 'skills' => $data['skills'], 'location' => 'Yangon, Myanmar',
            ]);

            return $user;
        });

        $jobs = collect([
            ['title' => 'Build a bilingual marketplace dashboard', 'category' => 'Development & IT', 'budget_min' => 450000, 'budget_max' => 750000, 'skills' => ['Laravel', 'React', 'MySQL'], 'duration' => '1 to 3 months'],
            ['title' => 'Design a mobile wallet onboarding flow', 'category' => 'Design & Creative', 'budget_min' => 300000, 'budget_max' => 500000, 'skills' => ['Figma', 'Mobile Design', 'UX Research'], 'duration' => 'Less than 1 month'],
            ['title' => 'Translate product pages to Myanmar', 'category' => 'Writing & Translation', 'budget_min' => 120000, 'budget_max' => 220000, 'skills' => ['Translation', 'Copywriting'], 'duration' => 'Less than 1 month'],
        ])->map(function (array $data) use ($client) {
            return Job::updateOrCreate(['client_id' => $client->id, 'title' => $data['title']], $data + [
                'description' => 'We need a thoughtful freelancer who can communicate clearly, work independently, and deliver a polished result for a Myanmar-focused audience.',
                'budget_type' => 'fixed', 'experience_level' => 'intermediate', 'status' => 'open',
            ]);
        });

        Proposal::updateOrCreate(
            ['job_id' => $jobs->first()->id, 'freelancer_id' => $freelancers->get(1)->id],
            ['cover_letter' => 'I build Laravel and React products with maintainable APIs, careful responsive UI work, and clear weekly progress updates. I would be happy to help shape this marketplace dashboard.', 'bid_amount' => 650000, 'delivery_days' => 30, 'status' => 'shortlisted']
        );
    }
}
