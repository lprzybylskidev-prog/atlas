<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Application\Modules\Contracts\ModuleGateStateProvider;
use App\Shared\Application\Modules\DefaultModuleGate;
use App\Shared\Application\Modules\ModuleAccessDenialReason;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Modules\ModuleAccessState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleGateTest extends TestCase
{
    /**
     * @return iterable<string, array{ModuleAccessState, ModuleAccessDenialReason}>
     */
    public static function deniedStates(): iterable
    {
        yield 'not deployed' => [
            self::state(deployed: false),
            ModuleAccessDenialReason::NotDeployed,
        ];

        yield 'missing required dependency' => [
            self::state(requiredDependenciesSatisfied: false),
            ModuleAccessDenialReason::MissingRequiredDependency,
        ];

        yield 'technically unavailable' => [
            self::state(technicallyAvailable: false),
            ModuleAccessDenialReason::TechnicallyUnavailable,
        ];

        yield 'globally inactive' => [
            self::state(globallyActive: false),
            ModuleAccessDenialReason::GloballyInactive,
        ];

        yield 'team inactive' => [
            self::state(teamActive: false),
            ModuleAccessDenialReason::TeamInactive,
        ];

        yield 'invalid active team' => [
            self::state(activeTeamValid: false),
            ModuleAccessDenialReason::InvalidActiveTeam,
        ];

        yield 'permission denied' => [
            self::state(permissionGranted: false),
            ModuleAccessDenialReason::PermissionDenied,
        ];
    }

    #[DataProvider('deniedStates')]
    public function test_it_denies_module_access_in_the_canonical_order(
        ModuleAccessState $state,
        ModuleAccessDenialReason $reason,
    ): void {
        $gate = new DefaultModuleGate(new StaticModuleGateStateProvider($state));
        $decision = $gate->inspect(new ModuleAccessRequest(
            moduleKey: 'identity',
            activeTeamId: 1,
            requiredPermission: 'identity.users.view',
        ));

        self::assertFalse($decision->allowed);
        self::assertSame($reason, $decision->denialReason);
    }

    public function test_it_allows_module_access_when_every_gate_condition_passes(): void
    {
        $gate = new DefaultModuleGate(new StaticModuleGateStateProvider(self::state()));
        $request = new ModuleAccessRequest(
            moduleKey: 'identity',
            activeTeamId: 1,
            requiredPermission: 'identity.users.view',
        );

        $decision = $gate->inspect($request);

        self::assertTrue($decision->allowed);
        self::assertNull($decision->denialReason);
        self::assertTrue($gate->allows($request));
    }

    private static function state(
        bool $deployed = true,
        bool $requiredDependenciesSatisfied = true,
        bool $technicallyAvailable = true,
        bool $globallyActive = true,
        bool $teamActive = true,
        bool $activeTeamValid = true,
        bool $permissionGranted = true,
    ): ModuleAccessState {
        return new ModuleAccessState(
            deployed: $deployed,
            requiredDependenciesSatisfied: $requiredDependenciesSatisfied,
            technicallyAvailable: $technicallyAvailable,
            globallyActive: $globallyActive,
            teamActive: $teamActive,
            activeTeamValid: $activeTeamValid,
            permissionGranted: $permissionGranted,
        );
    }
}

final readonly class StaticModuleGateStateProvider implements ModuleGateStateProvider
{
    public function __construct(private ModuleAccessState $state) {}

    public function stateFor(ModuleAccessRequest $request): ModuleAccessState
    {
        return $this->state;
    }
}
