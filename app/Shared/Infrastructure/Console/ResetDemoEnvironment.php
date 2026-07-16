<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class ResetDemoEnvironment extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Recreate the local/development Atlas demo environment.';

    public function handle(): int
    {
        $environment = config('app.env');

        if (! is_string($environment)) {
            $this->error('Refusing to reset Atlas demo data because APP_ENV is invalid.');

            return self::FAILURE;
        }

        if (! in_array($environment, ['local', 'development'], true)) {
            $this->error(sprintf(
                'Refusing to reset Atlas demo data in the [%s] environment.',
                $environment,
            ));

            return self::FAILURE;
        }

        $this->warn('Resetting the local Atlas demo environment.');

        $this->clearRuntimeState();
        $this->clearSessionState();

        $this->call('migrate:fresh', [
            '--force' => true,
        ]);

        $this->call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);

        $this->call('db:seed', [
            '--class' => DevelopmentDemoSeeder::class,
            '--force' => true,
        ]);

        $this->clearRuntimeState();
        $this->clearSessionState();

        $this->info('Atlas demo environment has been reset.');

        return self::SUCCESS;
    }

    private function clearRuntimeState(): void
    {
        $this->line('Clearing cached application state.');

        $this->call('optimize:clear');
        $this->call('cache:clear');
        $this->call('event:clear');

        if ($this->laravel->bound('cache')) {
            $this->laravel->make('cache')->forget(config()->string('permission.cache.key'));
        }
    }

    private function clearSessionState(): void
    {
        $this->line('Clearing session state.');

        if (is_dir(storage_path('framework/sessions'))) {
            File::cleanDirectory(storage_path('framework/sessions'));
        }

        $sessionDriver = config()->string('session.driver');

        if ($sessionDriver !== 'redis') {
            return;
        }

        $connection = config('session.connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : 'default';

        try {
            Redis::connection($connection)->flushdb();
        } catch (Throwable $exception) {
            $this->warn(sprintf(
                'Could not flush Redis session connection [%s]: %s',
                $connection,
                $exception->getMessage(),
            ));
        }
    }
}
