<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Modules\Core\Identity\Application\Sessions\SessionLimitResolver;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use App\Modules\Core\Notifications\Application\Public\DTOs\NotificationSummary;
use App\Modules\Core\Notifications\Application\Public\Permissions\NotificationPermissionNames;
use App\Modules\Core\Notifications\Presentation\Support\NotificationTextLocalizer;
use App\Modules\Core\Settings\Application\Settings\EffectiveSettings;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Users\Application\Permissions\UserPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\DTOs\InactivityPolicy;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Diglactic\Breadcrumbs\Breadcrumbs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'release' => [
                    'version' => config('atlas.release.version'),
                    'id' => config('atlas.release.id'),
                ],
            ],
            'auth' => [
                'user' => $request->user() === null ? null : [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar' => [
                        'color' => $request->user()->avatar_color,
                        'imageUrl' => $this->avatarImageUrl($request->user()->avatar_image_file_public_id),
                    ],
                ],
                'availableAdminRoutes' => $this->availableAdminRoutes($request),
                'availableApplicationRoutes' => $this->availableApplicationRoutes($request),
                'teams' => $this->teams($request),
                'impersonation' => app(ImpersonationManager::class)->sharedState($request),
            ],
            'locale' => app()->getLocale(),
            'supportedLocales' => ['pl', 'en'],
            'translations' => $this->translations(),
            'preferences' => [
                'theme' => $this->theme($request),
            ],
            'navigation' => [
                'breadcrumbs' => $this->breadcrumbs($request),
            ],
            'notifications' => $this->notifications($request),
            'timeTracking' => [
                'activity' => $this->timeTrackingActivity($request),
            ],
            'flash' => [
                'messages' => $request->session()->get('flash.messages', []),
            ],
        ];
    }

    private function avatarImageUrl(mixed $filePublicId): ?string
    {
        if (! is_string($filePublicId) || $filePublicId === '') {
            return null;
        }

        $clean = DB::table(DatabaseTable::FILE_OBJECTS)
            ->where('public_id', $filePublicId)
            ->where('scan_state', 'clean')
            ->whereNull('deleted_at')
            ->exists();

        return $clean ? route('users.profile.avatar-image', absolute: false) : null;
    }

    /**
     * @return array{enabled: bool, endpoint: string, thresholdSeconds: int, warningSeconds: int}
     */
    private function timeTrackingActivity(Request $request): array
    {
        $policy = $this->inactivityPolicy($request);
        $defaults = [
            'enabled' => false,
            'endpoint' => route(TimeTrackingPermissionCatalog::ACTIVITY_RECORD, absolute: false),
            'thresholdSeconds' => $policy->inactivityThresholdSeconds,
            'warningSeconds' => $policy->warningSeconds,
        ];
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $userId = data_get($request->user(), 'id');

        if (! is_string($userPublicId) || ! is_string($teamPublicId) || ! is_numeric($userId)) {
            return $defaults;
        }

        /** @var EffectivePermissionChecker $checker */
        $checker = app(EffectivePermissionChecker::class);

        if (! $checker->check(new EffectivePermissionRequest($userPublicId, TimeTrackingPermissionCatalog::ACTIVITY_RECORD, $teamPublicId))->allowed) {
            return $defaults;
        }

        $hasActiveWork = DB::table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)
            ->where('user_id', (int) $userId)
            ->whereNull('ended_at')
            ->exists();

        if (! $hasActiveWork) {
            return $defaults;
        }

        $hasActiveLock = DB::table(DatabaseTable::TIME_TRACKING_BREAKS)
            ->where('user_id', (int) $userId)
            ->whereNull('ended_at')
            ->exists()
            || DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK)
                ->where('user_id', (int) $userId)
                ->whereNull('ended_at')
                ->exists();

        return [
            ...$defaults,
            'enabled' => ! $hasActiveLock,
        ];
    }

    private function inactivityPolicy(Request $request): InactivityPolicy
    {
        $user = $request->user();
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if ($user instanceof User && is_string($teamPublicId)) {
            $limits = app(SessionLimitResolver::class)->limitsFor($user, $teamPublicId);

            return new InactivityPolicy(max(60, $limits['inactivity'] * 60));
        }

        return new InactivityPolicy;
    }

    /**
     * @return array{active: array{publicId: string, name: string}|null, available: list<array{publicId: string, name: string}>}
     */
    private function teams(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');

        if (! is_string($userPublicId)) {
            return [
                'active' => null,
                'available' => [],
            ];
        }

        $available = [];

        foreach (DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
            ->where('teams.is_active', true)
            ->orderBy('teams.display_name')
            ->orderBy('teams.name')
            ->get(['teams.public_id', 'teams.name', 'teams.display_name'])
            ->all() as $team) {
            $available[] = [
                'publicId' => self::stringValue($team, 'public_id'),
                'name' => self::teamDisplayName($team),
            ];
        }

        $activePublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $active = null;

        foreach ($available as $team) {
            if (is_string($activePublicId) && $team['publicId'] === $activePublicId) {
                $active = $team;
                break;
            }
        }

        return [
            'active' => $active,
            'available' => $available,
        ];
    }

    private static function stringValue(object $record, string $property): string
    {
        $value = $record->{$property} ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private static function teamDisplayName(object $record): string
    {
        $displayName = self::stringValue($record, 'display_name');

        return $displayName !== '' ? $displayName : self::stringValue($record, 'name');
    }

    private function theme(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $teamId = null;

        if (is_string($teamPublicId)) {
            $teamId = DB::table(DatabaseTable::TEAMS)
                ->where('public_id', $teamPublicId)
                ->value('id');
        }

        $guestTheme = $request->cookie('atlas_theme');

        /** @var EffectiveSettings $settings */
        $settings = app(EffectiveSettings::class);

        return $settings->theme(
            userId: is_int($userId) ? $userId : null,
            teamId: is_int($teamId) ? $teamId : null,
            guestTheme: is_string($guestTheme) ? $guestTheme : null,
        );
    }

    /**
     * @return list<string>
     */
    private function availableAdminRoutes(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (app(ImpersonationManager::class)->active($request)) {
            return [];
        }

        if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
            return [];
        }

        /** @var EffectivePermissionChecker $checker */
        $checker = app(EffectivePermissionChecker::class);
        $routes = [
            'admin.system-status',
            'admin.system-status.release',
            'admin.system-status.readiness',
            'admin.system-status.modules',
            'admin.system-status.scheduler',
            'admin.system-status.module-activation',
            'admin.system-status.failed-jobs',
            'admin.users.index',
            'admin.teams.index',
            'admin.managers.index',
            'admin.authorization.roles.index',
            'admin.authorization.packages.index',
            'admin.authorization.permissions.index',
            'admin.audit.index',
            'admin.audit.security-history.index',
            'admin.audit.impersonation.show',
            'admin.modules.index',
            'admin.queues.index',
            'admin.files.index',
            'admin.work-time.summary.index',
            'admin.work-time.other-work.index',
            'admin.work-time.other-work.categories.index',
            'admin.work-time.other-work.categories.create',
            'admin.work-time.other-work.decide',
            'admin.work-time.other-work.force-close',
            'admin.work-time.other-work.show',
            'admin.work-time.work-sessions.index',
            'admin.work-time.work-sessions.show',
            'admin.work-time.work-sessions.terminate',
            'admin.work-time.breaks.index',
            'admin.work-time.breaks.force-close',
            'admin.work-time.breaks.show',
            'admin.work-time.breaks.convert-excess',
            'admin.work-time.corrections.index',
            'admin.work-time.corrections.decide',
            'admin.work-time.corrections.manual-entry',
            'admin.work-time.corrections.manual-entry.store',
            'admin.work-time.corrections.show',
            'admin.privacy-retention.index',
            'admin.privacy-retention.legal-holds.index',
            'admin.privacy-retention.legal-holds.create',
            'admin.privacy-retention.operations.index',
            'admin.logs.index',
            'admin.pulse.view',
            'admin.telescope.view',
            'admin.feature-flags.index',
            'admin.rate-limits.index',
            'admin.integrations.index',
            'admin.search.index',
            'admin.managed-processes.index',
            'admin.managed-processes.definitions.index',
            'admin.managed-processes.schedules.index',
            'admin.managed-processes.schedules.create',
        ];
        $available = [];

        foreach ($routes as $route) {
            if (! $this->adminRouteCanBeOffered($route)) {
                continue;
            }

            $decision = $checker->check(new EffectivePermissionRequest($userPublicId, $route, $teamPublicId));

            if ($decision->allowed) {
                $available[] = $route;
            }
        }

        return $available;
    }

    /**
     * @return list<string>
     */
    private function availableApplicationRoutes(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
            return [];
        }

        /** @var EffectivePermissionChecker $checker */
        $checker = app(EffectivePermissionChecker::class);
        $routes = [
            CoreAuthorizationPermissionCatalog::DASHBOARD,
            UserPermissionCatalog::USERS_PROFILE,
            NotificationPermissionNames::NOTIFICATIONS_INDEX,
        ];

        if ($this->activeUserTeamTrackingEnabled($request)) {
            $routes[] = TimeTrackingPermissionCatalog::USER_REPORT;
            $routes[] = TimeTrackingPermissionCatalog::USER_CORRECTION_REQUEST_STORE;
            $routes[] = TimeTrackingPermissionCatalog::BREAK_START;
            $routes[] = TimeTrackingPermissionCatalog::OTHER_WORK_CREATE;
            $routes[] = TimeTrackingPermissionCatalog::OTHER_WORK_START;
        }

        if ($this->hasManagerScope($teamPublicId, $userPublicId)) {
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_PANEL;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_REPORT;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_SUMMARY;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_OTHER_WORK;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSIONS;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_BREAKS;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_CORRECTIONS;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSION_SHOW;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_BREAK_SHOW;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_OTHER_WORK_SHOW;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_CORRECTION_SHOW;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_TERMINATE_SESSION;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_BREAK_FORCE_CLOSE;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_BREAK_CONVERT_EXCESS;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_FORCE_CLOSE;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_DECIDE;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_CORRECTION_DECIDE;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_INDEX;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_CREATE;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_STORE;
            $routes[] = TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_DEACTIVATE;
        }
        $available = [];

        foreach ($routes as $route) {
            $decision = $checker->check(new EffectivePermissionRequest($userPublicId, $route, $teamPublicId));

            if ($decision->allowed) {
                $available[] = $route;
            }
        }

        return $available;
    }

    private function hasManagerScope(string $teamPublicId, string $userPublicId): bool
    {
        $scope = app(ManagerHierarchy::class)->scopeFor($teamPublicId, $userPublicId);

        return $scope->visibleUserPublicIds !== [];
    }

    private function activeUserTeamTrackingEnabled(Request $request): bool
    {
        $userId = $request->user()?->getAuthIdentifier();
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_int($userId) || ! is_string($teamPublicId)) {
            return false;
        }

        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if (! is_numeric($teamId)) {
            return false;
        }

        return app(UserTeamTrackingSettings::class)->isEnabledForUserTeam($userId, (int) $teamId);
    }

    private function adminRouteCanBeOffered(string $route): bool
    {
        return match ($route) {
            'admin.pulse.view' => Route::has('pulse'),
            'admin.telescope.view' => app()->environment(['local', 'development']) && Route::has('telescope'),
            default => true,
        };
    }

    /**
     * @return array{unreadCount: int, latest: list<array{publicId: string, type: string, severity: string, title: string, body: string|null, deepLinkUrl: string|null, teamPublicId: string|null, read: bool, createdAt: string, readAt: string|null}>}
     */
    private function notifications(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId)) {
            return [
                'unreadCount' => 0,
                'latest' => [],
            ];
        }

        /** @var NotificationInbox $notifications */
        $notifications = app(NotificationInbox::class);
        $localizer = new NotificationTextLocalizer;
        $team = is_string($teamPublicId) ? $teamPublicId : null;

        return [
            'unreadCount' => $notifications->unreadCount($userPublicId, $team),
            'latest' => array_map(
                static fn (NotificationSummary $notification): array => [
                    'publicId' => $notification->publicId,
                    'type' => $notification->type,
                    'severity' => $notification->severity,
                    'title' => $localizer->title($notification),
                    'body' => $localizer->body($notification),
                    'deepLinkUrl' => $notification->deepLinkUrl,
                    'teamPublicId' => $notification->teamPublicId,
                    'read' => $notification->read,
                    'createdAt' => $notification->createdAt,
                    'readAt' => $notification->readAt,
                ],
                $notifications->latestForUser($userPublicId, $team, 10),
            ),
        ];
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    private function breadcrumbs(Request $request): array
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null || ! Breadcrumbs::exists($routeName)) {
            return [];
        }

        $items = [];

        $routeParameters = array_values($request->route()?->parameters() ?? []);

        foreach (Breadcrumbs::generate($routeName, ...$routeParameters) as $breadcrumb) {
            if (! is_object($breadcrumb)) {
                continue;
            }

            $attributes = get_object_vars($breadcrumb);
            $title = $attributes['title'] ?? '';
            $url = $attributes['url'] ?? null;

            $items[] = [
                'label' => is_scalar($title) ? (string) $title : '',
                'url' => is_string($url) && $url !== '' ? $url : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function translations(): array
    {
        $path = lang_path(app()->getLocale().'.json');

        if (! is_file($path)) {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            return [];
        }

        $translations = [];

        foreach ($decoded as $key => $value) {
            if (
                is_string($key)
                && is_string($value)
                && preg_match('/^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/', $key) === 1
            ) {
                $translations[$key] = $value;
            }
        }

        ksort($translations);

        return $translations;
    }
}
