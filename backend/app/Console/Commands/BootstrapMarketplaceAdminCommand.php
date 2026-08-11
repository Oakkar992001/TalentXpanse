<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BootstrapMarketplaceAdminCommand extends Command
{
    protected $signature = 'marketplace:bootstrap-admin';

    protected $description = 'Create the initial marketplace administrator from one-time deployment environment variables';

    public function handle(): int
    {
        $email = strtolower(trim((string) env('OPEN_BETA_ADMIN_EMAIL')));
        $password = (string) env('OPEN_BETA_ADMIN_PASSWORD');
        $name = trim((string) env('OPEN_BETA_ADMIN_NAME', 'TalentXpanse Administrator'));

        if ($email === '' && $password === '') {
            return self::SUCCESS;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
            $this->error('The bootstrap administrator configuration is invalid. Provide a valid email and a password of at least 12 characters.');

            return self::FAILURE;
        }

        [$user, $created] = DB::transaction(function () use ($email, $password, $name): array {
            $user = User::where('email', $email)->first();
            $created = false;

            if (! $user) {
                $user = User::create([
                    'name' => $name !== '' ? $name : 'TalentXpanse Administrator',
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make($password),
                    'active_role' => 'admin',
                    'status' => 'active',
                ]);
                $created = true;
            }

            $role = Role::firstOrCreate(['name' => 'admin']);
            $user->roles()->syncWithoutDetaching([$role->id]);

            return [$user, $created];
        });

        $this->info($created
            ? "Administrator account created for {$user->email}."
            : "Administrator access confirmed for {$user->email}."
        );

        if ($created) {
            $this->warn('Remove OPEN_BETA_ADMIN_PASSWORD from the hosting environment after this deployment has started successfully.');
        }

        return self::SUCCESS;
    }
}
