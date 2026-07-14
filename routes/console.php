<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:reset', function (): int {
    $environment = config('app.env');

    if (! is_string($environment)) {
        $this->error('Refusing to reset Atlas demo data because APP_ENV is invalid.');

        return Command::FAILURE;
    }

    if (! in_array($environment, ['local', 'development'], true)) {
        $this->error(sprintf(
            'Refusing to reset Atlas demo data in the [%s] environment.',
            $environment,
        ));

        return Command::FAILURE;
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

    return Command::SUCCESS;
})->purpose('Recreate the local/development Atlas demo environment');
