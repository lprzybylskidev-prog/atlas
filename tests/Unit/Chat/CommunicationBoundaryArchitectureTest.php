<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use App\Modules\Core\Calendar\CalendarModule;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\ChatModule;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CommunicationBoundaryArchitectureTest extends TestCase
{
    public function test_calendar_is_core_and_chat_is_globally_activated_optional_module(): void
    {
        $calendar = new CalendarModule;
        $chat = new ChatModule;

        self::assertSame(ModuleCategory::Core, $calendar->category());
        self::assertFalse($calendar->supportsGlobalActivation());
        self::assertSame(ModuleCategory::Optional, $chat->category());
        self::assertTrue($chat->supportsGlobalActivation());
        self::assertFalse($chat->supportsTeamActivation());
        self::assertSame(
            ['identity', 'files', 'calendar', 'teams', 'audit'],
            array_map(static fn ($key): string => $key->value, $chat->requiredDependencies()),
        );
    }

    public function test_calendar_and_chat_have_distinct_owned_schemas(): void
    {
        self::assertContains(DatabaseSchema::CORE_CALENDAR, DatabaseSchema::all());
        self::assertContains(DatabaseSchema::OPTIONAL_CHAT, DatabaseSchema::all());
        self::assertNotSame(DatabaseSchema::CORE_CALENDAR, DatabaseSchema::OPTIONAL_CHAT);
    }

    public function test_chat_imports_only_calendar_public_surface(): void
    {
        foreach ($this->chatPhpFiles() as $file) {
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);

            preg_match_all('/App\\\\Modules\\\\Core\\\\Calendar\\\\([^;\s]+)/', $contents, $matches);

            foreach ($matches[0] as $import) {
                self::assertStringContainsString('\\Application\\Public\\', $import, $file->getPathname());
            }
        }
    }

    public function test_operational_admin_permissions_do_not_grant_private_content_read_access(): void
    {
        $names = array_map(static fn ($permission): string => $permission->name, (new ChatPermissionCatalog)->permissions());

        self::assertNotContains('chat.admin.read', $names);
        self::assertNotContains('admin.chat.messages.show', $names);
        self::assertNotContains('admin.chat.conversations.show', $names);
    }

    /** @return iterable<SplFileInfo> */
    private function chatPhpFiles(): iterable
    {
        $root = dirname(__DIR__, 3).'/app/Modules/Optional/Chat';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $candidate) {
            if ($candidate instanceof SplFileInfo && $candidate->isFile() && $candidate->getExtension() === 'php') {
                yield $candidate;
            }
        }
    }
}
