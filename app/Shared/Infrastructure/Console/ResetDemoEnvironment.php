<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Console\Command;

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

        $this->warn('Resetting the local Atlas demo database.');

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

        $this->info('Atlas demo environment has been reset.');

        return self::SUCCESS;
    }
}
