<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Authorization\Infrastructure\Persistence\SpatieEffectivePermissionChecker;
use App\Modules\Core\Teams\Application\Permissions\TeamPermissionCatalog;
use App\Modules\Core\Users\Application\Permissions\UserPermissionCatalog;
use App\Shared\Application\Authorization\Contracts\EffectivePermissionChecker;
use App\Shared\Application\Authorization\DTOs\EffectivePermissionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class AuthorizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_teams_mode_is_mandatory(): void
    {
        self::assertTrue(config('permission.teams'));
        self::assertSame('team_id', config('permission.column_names.team_foreign_key'));
    }

    public function test_module_owned_permission_catalogs_are_registered_without_duplicates(): void
    {
        $registry = $this->app->make(PermissionCatalogRegistry::class);

        self::assertContains(UserPermissionCatalog::USERS_VIEW, $registry->names());
        self::assertContains(TeamPermissionCatalog::TEAMS_VIEW, $registry->names());
        self::assertContains('authorization.roles.view', $registry->names());
        self::assertSame($registry->names(), array_values(array_unique($registry->names())));
    }

    public function test_every_authorization_option_segment_and_starter_role_has_polish_and_english_copy(): void
    {
        $registry = $this->app->make(PermissionCatalogRegistry::class);

        foreach (['pl', 'en'] as $locale) {
            foreach ($registry->names() as $permission) {
                foreach (explode('.', $permission) as $segment) {
                    $key = 'authorization.permission_segments.'.str_replace('-', '_', $segment);
                    self::assertTrue(Lang::has($key, $locale), sprintf('Missing %s authorization label [%s].', $locale, $key));
                }
            }

            foreach (StarterRoleName::cases() as $role) {
                $key = 'authorization.role_labels.'.str_replace(['.', '-'], '_', $role->value);
                self::assertTrue(Lang::has($key, $locale), sprintf('Missing %s starter-role label [%s].', $locale, $key));
            }
        }
    }

    public function test_effective_permission_checker_is_a_public_authorization_contract(): void
    {
        $checker = $this->app->make(EffectivePermissionChecker::class);

        self::assertInstanceOf(SpatieEffectivePermissionChecker::class, $checker);

        $decision = $checker->check(new EffectivePermissionRequest(
            userPublicId: '01HZY000000000000000000000',
            permission: UserPermissionCatalog::USERS_VIEW,
            teamPublicId: '01HZY000000000000000000001',
        ));

        self::assertFalse($decision->allowed);
        self::assertSame('authorization.permission_unknown', $decision->reason);
    }

    public function test_spatie_authorization_apis_stay_inside_authorization_infrastructure(): void
    {
        $basePath = dirname(__DIR__, 3);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath.'/app'));

        foreach ($iterator as $candidate) {
            if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($candidate->getPathname());

            self::assertIsString($contents);

            if (! str_contains($contents, 'Spatie\\Permission\\')) {
                continue;
            }

            self::assertStringContainsString(
                '/app/Modules/Core/Authorization/Infrastructure/',
                $candidate->getPathname(),
                sprintf('Spatie Permission API usage in [%s] must stay inside Authorization Infrastructure.', $candidate->getPathname()),
            );
        }
    }

    public function test_business_code_does_not_check_starter_role_names(): void
    {
        $basePath = dirname(__DIR__, 3);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath.'/app'));

        foreach ($iterator as $candidate) {
            if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($candidate->getPathname());

            self::assertIsString($contents);
            self::assertDoesNotMatchRegularExpression(
                '/->has(?:Any|All|Exact)?Role\s*\(/',
                $contents,
                sprintf('Business code must not check role names in [%s].', $candidate->getPathname()),
            );
        }
    }

    public function test_authorization_model_has_no_permission_deny_layer(): void
    {
        $basePath = dirname(__DIR__, 3);

        foreach ([$basePath.'/app', $basePath.'/resources/js'] as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($iterator as $candidate) {
                if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || ! in_array($candidate->getExtension(), ['php', 'ts', 'vue'], true)) {
                    continue;
                }

                $contents = file_get_contents($candidate->getPathname());
                self::assertIsString($contents);
                self::assertDoesNotMatchRegularExpression(
                    '/\b(?:direct_deny|denied_permissions?|deny_permissions?|negative_permissions?)\b/i',
                    $contents,
                    sprintf('Authorization deny-layer semantics are forbidden in [%s].', $candidate->getPathname()),
                );
            }
        }
    }

    public function test_protected_named_routes_require_matching_route_permission_middleware(): void
    {
        $publicRoutes = [
            'login',
            'locale.update',
            'password.confirm',
            'password.confirm.store',
            'password.confirmation',
            'password.email',
            'password.reset',
            'team.select',
            'team.select.store',
            'team.switch',
            'theme.update',
            'table-views.store',
            'table-views.update',
            'table-views.destroy',
            'table-views.copy',
            'table-views.default',
            'users.profile.notification-emails.verify',
        ];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if (! is_string($name) || in_array($name, $publicRoutes, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if (! in_array('auth', $middleware, true)) {
                continue;
            }

            self::assertContains(
                'route.permission',
                $middleware,
                sprintf('Protected route [%s] must require the route-name permission middleware.', $name),
            );
        }
    }

    public function test_legacy_separate_manager_administration_area_cannot_return(): void
    {
        $basePath = dirname(__DIR__, 3);
        $roots = [$basePath.'/app', $basePath.'/resources/js', $basePath.'/routes'];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($iterator as $candidate) {
                if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || ! in_array($candidate->getExtension(), ['php', 'ts', 'vue'], true)) {
                    continue;
                }

                $contents = file_get_contents($candidate->getPathname());
                self::assertIsString($contents);
                self::assertStringNotContainsString('/admin/managers', $contents, $candidate->getPathname());
                self::assertStringNotContainsString('admin.managers.', $contents, $candidate->getPathname());
                self::assertStringNotContainsString('Admin/Managers', $contents, $candidate->getPathname());
            }
        }

        self::assertDirectoryDoesNotExist($basePath.'/resources/js/Pages/Admin/Managers');
    }
}
