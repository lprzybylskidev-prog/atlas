<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use App\Modules\Core\Notifications\Application\Public\DTOs\NotificationSummary;
use App\Modules\Core\Notifications\Presentation\Support\NotificationTextLocalizer;
use App\Modules\Core\Settings\Application\Settings\EffectiveSettings;
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
                ],
                'availableAdminRoutes' => $this->availableAdminRoutes($request),
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
            'flash' => [
                'messages' => $request->session()->get('flash.messages', []),
            ],
        ];
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

        if (! is_string($teamPublicId) && is_string($userPublicId) && $request->hasSession()) {
            $teamPublicId = $this->firstAssignedTeamPublicId($userPublicId);

            if (is_string($teamPublicId)) {
                $request->session()->put('active_team_public_id', $teamPublicId);
            }
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

    private function adminRouteCanBeOffered(string $route): bool
    {
        return match ($route) {
            'admin.pulse.view' => Route::has('pulse'),
            'admin.telescope.view' => app()->environment(['local', 'development']) && Route::has('telescope'),
            default => true,
        };
    }

    private function firstAssignedTeamPublicId(string $userPublicId): ?string
    {
        $team = DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
            ->where('teams.is_active', true)
            ->orderBy('teams.display_name')
            ->orderBy('teams.name')
            ->value('teams.public_id');

        return is_string($team) ? $team : null;
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

        foreach (Breadcrumbs::generate($routeName) as $breadcrumb) {
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
