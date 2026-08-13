<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessDenialReason;
use App\Shared\Application\Modules\ModuleAccessRequest;
use PHPUnit\Framework\TestCase;

final class ChatModuleAccessTest extends TestCase
{
    public function test_every_application_access_request_uses_chat_module_permission_and_team_context(): void
    {
        $gate = new class implements ModuleGate
        {
            public ?ModuleAccessRequest $request = null;

            public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
            {
                $this->request = $request;

                return ModuleAccessDecision::allow();
            }

            public function allows(ModuleAccessRequest $request): bool
            {
                return $this->inspect($request)->allowed;
            }
        };

        $access = new ChatModuleAccess($gate);

        self::assertTrue($access->allows('user-1', 'team-1', ChatPermissionCatalog::CALL_START));
        $capturedRequest = $gate->request;
        self::assertNotNull($capturedRequest);
        self::assertSame('chat', $capturedRequest->moduleKey);
        self::assertSame('user-1', $capturedRequest->userPublicId);
        self::assertSame('team-1', $capturedRequest->activeTeamPublicId);
        self::assertSame(ChatPermissionCatalog::CALL_START, $capturedRequest->requiredPermission);
    }

    public function test_global_deactivation_denies_chat_application_access(): void
    {
        $gate = new class implements ModuleGate
        {
            public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
            {
                return ModuleAccessDecision::deny(ModuleAccessDenialReason::GloballyInactive);
            }

            public function allows(ModuleAccessRequest $request): bool
            {
                return false;
            }
        };

        self::assertFalse((new ChatModuleAccess($gate))->allows('user-1', 'team-1', ChatPermissionCatalog::INDEX));
    }
}
