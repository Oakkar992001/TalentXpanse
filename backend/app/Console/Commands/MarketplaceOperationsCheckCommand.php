<?php

namespace App\Console\Commands;

use App\Services\MarketplacePaymentService;
use App\Support\MarketplaceStorage;
use App\Services\OperationalReadinessService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MarketplaceOperationsCheckCommand extends Command
{
    protected $signature = 'marketplace:operations-check {--strict : Fail when production-only safeguards are not configured} {--json : Print machine-readable output}';

    protected $description = 'Check TalentXpanse runtime dependencies and production safeguards without exposing secrets';

    public function handle(OperationalReadinessService $readiness, MarketplacePaymentService $payments): int
    {
        $result = $readiness->check();
        $checks = collect($result['components'])
            ->map(fn (string $status, string $name) => ['name' => Str::headline($name), 'status' => $status])
            ->values();

        if ($this->option('strict')) {
            $checks = $checks->merge($this->productionChecks($payments));
        }

        $failed = $checks->filter(fn (array $check) => $check['status'] !== 'ok')->values();

        if ($this->option('json')) {
            $this->line(json_encode([
                'ready' => $failed->isEmpty(),
                'checks' => $checks,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Status'], $checks->map(fn (array $check) => [$check['name'], $check['status']])->all());
            $this->line($failed->isEmpty() ? 'TalentXpanse operational readiness check passed.' : 'TalentXpanse operational readiness check failed.');
        }

        return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    private function productionChecks(MarketplacePaymentService $payments): array
    {
        $appUrl = (string) config('app.url');
        $frontendUrl = (string) config('app.frontend_url');
        $mailMailer = (string) config('mail.default');
        $reverbEnabled = config('broadcasting.default') === 'reverb';
        $reverbApplication = config('reverb.apps.apps.0', []);

        return [
            $this->check('Production environment', app()->environment('production')),
            $this->check('Debug disabled', ! config('app.debug')),
            $this->check('Application URL uses HTTPS', Str::startsWith($appUrl, 'https://')),
            $this->check('Frontend URL uses HTTPS', Str::startsWith($frontendUrl, 'https://')),
            $this->check('Application key is configured', filled(config('app.key'))),
            $this->check('Persistent cache selected', ! in_array(config('cache.default'), ['array', 'null'], true)),
            $this->check('Persistent sessions selected', ! in_array(config('session.driver'), ['array', 'null'], true)),
            $this->check('Secure session cookie enabled', (bool) config('session.secure')),
            $this->check('Asynchronous queue selected', config('queue.default') !== 'sync'),
            $this->check('Email delivery configured', $this->mailConfigured($mailMailer) && filled(config('mail.from.address'))),
            $this->check('Private marketplace storage configured', $this->storageConfigured(MarketplaceStorage::privateDisk())),
            $this->check('Public profile storage configured', $this->storageConfigured(MarketplaceStorage::publicDisk())),
            $this->check('Reverb credentials configured', ! $reverbEnabled || (filled($reverbApplication['app_id'] ?? null) && filled($reverbApplication['key'] ?? null) && filled($reverbApplication['secret'] ?? null))),
            $this->check('Payment activation is safe', ! config('marketplace_payments.enabled') || $payments->gatewayConfigured()),
        ];
    }

    private function check(string $name, bool $passes): array
    {
        return ['name' => $name, 'status' => $passes ? 'ok' : 'degraded'];
    }

    private function mailConfigured(string $mailer): bool
    {
        return match ($mailer) {
            'smtp' => filled(config('mail.mailers.smtp.host')),
            'resend' => filled(config('services.resend.key')),
            default => ! in_array($mailer, ['array', 'log'], true),
        };
    }

    private function objectStorageConfigured(string $disk): bool
    {
        $config = config("filesystems.disks.{$disk}");

        return is_array($config)
            && $config['driver'] === 's3'
            && filled($config['key'] ?? null)
            && filled($config['secret'] ?? null)
            && filled($config['bucket'] ?? null)
            && filled($config['endpoint'] ?? null);
    }

    private function storageConfigured(string $disk): bool
    {
        if (config('marketplace_storage.external_storage_required')) {
            return $this->objectStorageConfigured($disk);
        }

        $config = config("filesystems.disks.{$disk}");

        return is_array($config)
            && filled($config['driver'] ?? null)
            && ($config['driver'] !== 'local' || filled($config['root'] ?? null));
    }
}
