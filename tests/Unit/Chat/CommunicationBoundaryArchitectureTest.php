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
        self::assertSame(['reverb'], $chat->healthChecks(), 'RTC or Egress failure must not disable text Chat through ModuleGate.');
        self::assertSame(
            ['identity', 'files', 'calendar', 'teams', 'audit', 'notifications', 'managed_processes'],
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

    public function test_livekit_api_secret_has_no_browser_configuration_surface(): void
    {
        $root = dirname(__DIR__, 3);
        foreach ([$root.'/resources/js', $root.'/resources/views'] as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || ! in_array($file->getExtension(), ['php', 'ts', 'vue'], true)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                self::assertIsString($contents);
                self::assertStringNotContainsString('LIVEKIT_API_SECRET', $contents, $file->getPathname());
                self::assertStringNotContainsString('livekit.api_secret', $contents, $file->getPathname());
                self::assertStringNotContainsString('ATLAS_TRANSCRIPTION_PROVIDER_CREDENTIAL', $contents, $file->getPathname());
                self::assertStringNotContainsString('transcription.credential', $contents, $file->getPathname());
            }
        }

        self::assertStringNotContainsString('VITE_LIVEKIT_API_SECRET', (string) file_get_contents($root.'/.env.example'));
    }

    public function test_ad_hoc_calls_have_no_recording_or_egress_surface(): void
    {
        $root = dirname(__DIR__, 3);
        $manager = (string) file_get_contents($root.'/app/Modules/Optional/Chat/Application/CallManager.php');
        $controller = (string) file_get_contents($root.'/app/Modules/Optional/Chat/Presentation/Http/Controllers/ChatCallController.php');
        $overlay = (string) file_get_contents($root.'/resources/js/Components/Chat/CallOverlay.vue');

        foreach ([$manager, $controller, $overlay] as $surface) {
            self::assertStringNotContainsString('Egress', $surface);
            self::assertStringNotContainsString('recording', strtolower($surface));
        }
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
