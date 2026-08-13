<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Modules\Core\Calendar\Application\Permissions\CalendarPermissionCatalog;
use App\Modules\Core\Calendar\Application\Public\Contracts\FreeBusyLookup;
use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyQuery;
use App\Modules\Core\Calendar\Infrastructure\Persistence\TableNames\CalendarDatabaseTable;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class PersonalCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_private_recurring_event_reminders_and_preferences(): void
    {
        [$owner, $team] = $this->calendarUser();

        $this->asCalendarUser($owner, $team)->post('/calendar/events', [
            'title' => 'Private collection plan',
            'description' => 'Confidential notes',
            'starts_at' => '2026-08-17T09:00:00',
            'ends_at' => '2026-08-17T10:00:00',
            'all_day' => false,
            'location' => 'Room 2',
            'availability' => 'busy',
            'recurrence_frequency' => 'weekly',
            'recurrence_interval' => 1,
            'recurrence_weekdays' => [1, 3],
            'recurrence_count' => 4,
            'reminder_minutes' => [15, 60],
        ])->assertRedirect();

        $event = DB::table(CalendarDatabaseTable::PERSONAL_EVENTS)->sole();
        self::assertSame($owner->id, $event->user_id);
        self::assertSame(2, DB::table(CalendarDatabaseTable::REMINDERS)->count());

        $this->asCalendarUser($owner, $team)
            ->get('/calendar?view=agenda&date=2026-08-17')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Calendar/Index')
                ->where('events.0.title', 'Private collection plan')
                ->has('events', 4));

        $this->asCalendarUser($owner, $team)->patch('/calendar/preferences', [
            'default_reminder_minutes' => 45,
            'email_enabled' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas(CalendarDatabaseTable::USER_PREFERENCES, [
            'user_id' => $owner->id,
            'default_reminder_minutes' => 45,
            'email_enabled' => false,
        ]);
    }

    public function test_other_user_cannot_read_or_mutate_private_event(): void
    {
        [$owner, $team] = $this->calendarUser();
        [$other] = $this->calendarUser($team);
        $this->asCalendarUser($owner, $team)->post('/calendar/events', $this->eventPayload())->assertRedirect();
        $event = DB::table(CalendarDatabaseTable::PERSONAL_EVENTS)->sole();
        self::assertIsString($event->public_id);

        $this->asCalendarUser($other, $team)
            ->get('/calendar?view=day&date=2026-08-17')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('events', 0));

        $this->asCalendarUser($other, $team)
            ->patch('/calendar/events/'.$event->public_id, [
                ...$this->eventPayload(),
                'title' => 'Privacy breach',
                'occurrence_date' => '2026-08-17',
                'mutation_scope' => 'series',
                'version' => 1,
            ])->assertNotFound();

        $this->assertDatabaseMissing(CalendarDatabaseTable::PERSONAL_EVENTS, ['title' => 'Privacy breach']);
    }

    public function test_free_busy_returns_windows_without_private_event_content_and_does_not_block_creation(): void
    {
        [$owner, $team] = $this->calendarUser();
        $this->asCalendarUser($owner, $team)->post('/calendar/events', $this->eventPayload())->assertRedirect();

        $lookup = $this->app->make(FreeBusyLookup::class);
        $query = new FreeBusyQuery(
            [$owner->public_id],
            new DateTimeImmutable('2026-08-17 09:30:00 Europe/Warsaw'),
            new DateTimeImmutable('2026-08-17 10:30:00 Europe/Warsaw'),
        );

        $windows = $lookup->windows($query);
        self::assertCount(1, $windows);
        self::assertSame($owner->public_id, $windows[0]->userPublicId);
        self::assertCount(1, $lookup->conflicts($query));
        self::assertSame(['userPublicId', 'startsAt', 'endsAt'], array_keys(get_object_vars($windows[0])));

        $this->asCalendarUser($owner, $team)->post('/calendar/events', [
            ...$this->eventPayload(),
            'title' => 'Allowed overlap',
            'starts_at' => '2026-08-17T09:30:00',
            'ends_at' => '2026-08-17T10:30:00',
        ])->assertRedirect();

        $this->assertDatabaseHas(CalendarDatabaseTable::PERSONAL_EVENTS, ['title' => 'Allowed overlap']);
    }

    /** @return array{User, Team} */
    private function calendarUser(?Team $team = null): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();
        $user = User::factory()->create();
        $team ??= Team::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Calendar Team',
            'slug' => 'calendar-team-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'structural_role' => 'employee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([CalendarPermissionCatalog::INDEX, CalendarPermissionCatalog::EVENT_STORE, CalendarPermissionCatalog::EVENT_UPDATE, CalendarPermissionCatalog::EVENT_DESTROY, CalendarPermissionCatalog::PREFERENCE_UPDATE] as $name) {
            $permission = Permission::query()->where('name', $name)->firstOrFail();
            DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)->insert([
                'permission_id' => $permission->id,
                'model_type' => config('auth.providers.users.model'),
                'model_id' => $user->id,
                'team_id' => $team->id,
            ]);
        }

        return [$user, $team];
    }

    private function asCalendarUser(User $user, Team $team): self
    {
        return $this->actingAs($user)->withSession(['active_team_public_id' => $team->public_id]);
    }

    /** @return array<string, mixed> */
    private function eventPayload(): array
    {
        return [
            'title' => 'Private event title',
            'description' => 'Private event description',
            'starts_at' => '2026-08-17T09:00:00',
            'ends_at' => '2026-08-17T10:00:00',
            'all_day' => false,
            'location' => 'Private location',
            'availability' => 'busy',
            'recurrence_frequency' => null,
            'recurrence_interval' => 1,
            'recurrence_weekdays' => [],
            'reminder_minutes' => [15],
        ];
    }
}
