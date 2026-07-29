<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationalReadinessService
{
    public function check(): array
    {
        $components = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        return [
            'ready' => collect($components)->every(fn (string $status) => $status === 'ok'),
            'components' => $components,
        ];
    }

    private function checkDatabase(): string
    {
        return $this->component(fn () => DB::connection()->getPdo());
    }

    private function checkCache(): string
    {
        return $this->component(fn () => Cache::store()->get('talentxpanse:readiness:probe'));
    }

    private function checkQueue(): string
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");

        if ($driver !== 'database') {
            return $driver ? 'ok' : 'degraded';
        }

        $databaseConnection = config("queue.connections.{$connection}.connection");
        $table = config("queue.connections.{$connection}.table", 'jobs');

        return $this->component(function () use ($databaseConnection, $table) {
            $schema = $databaseConnection ? Schema::connection($databaseConnection) : Schema::getFacadeRoot();

            if (! $schema->hasTable($table)) {
                throw new \RuntimeException('The queue table is unavailable.');
            }
        });
    }

    private function checkStorage(): string
    {
        return is_writable(storage_path()) ? 'ok' : 'degraded';
    }

    private function component(callable $callback): string
    {
        try {
            $callback();

            return 'ok';
        } catch (Throwable $exception) {
            report($exception);

            return 'degraded';
        }
    }
}
