<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Console;

use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Modules\Core\Users\Application\Public\Commands\CreateUserAccountRequest;
use App\Modules\Core\Users\Application\Public\Contracts\UserAccountCreator;
use Illuminate\Console\Command;

final class BootstrapFirstAdministrator extends Command
{
    protected $signature = 'atlas:first-administrator
        {--name= : Administrator display name}
        {--email= : Administrator email address}
        {--team=Atlas : Initial team name}';

    protected $description = 'Create the first administrator account and send the standard first-password link.';

    public function handle(
        AdministratorAccessManager $administrators,
        BootstrapTeamProvider $teams,
        UserAccountCreator $users,
        SecurityAuditRecorder $audit,
    ): int {
        if ($administrators->administratorExists()) {
            $this->error('The first administrator bootstrap is unavailable because an administrator already exists.');

            return self::FAILURE;
        }

        $name = $this->stringOption('name');
        $email = $this->stringOption('email');
        $teamName = $this->stringOption('team') ?: 'Atlas';

        if ($name === '') {
            $name = $this->promptString('Administrator name');
        }

        if ($email === '') {
            $email = $this->promptString('Administrator email');
        }

        $team = $teams->provide($teamName);
        $created = $users->create(new CreateUserAccountRequest(
            name: $name,
            email: $email,
        ));

        $administrators->assignAdministrator($created->publicId, $team->publicId);

        $audit->record(new SecurityAuditEvent(
            module: 'authorization',
            action: 'authorization.first_administrator_bootstrap',
            result: 'succeeded',
            source: 'cli',
            actorPublicId: null,
            targetPublicId: $created->publicId,
            reason: 'First administrator bootstrap',
            category: SecurityAuditCategory::Authorization,
            metadata: [
                'team_public_id' => $team->publicId,
                'first_password_link_issued' => $created->firstPasswordLinkIssued,
            ],
        ));

        $this->info('First administrator account created.');
        $this->line('The standard first-password link was sent to the administrator email address.');

        return self::SUCCESS;
    }

    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? trim($value) : '';
    }

    private function promptString(string $question): string
    {
        $answer = $this->ask($question);

        return is_string($answer) ? trim($answer) : '';
    }
}
