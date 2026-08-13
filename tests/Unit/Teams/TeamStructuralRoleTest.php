<?php

declare(strict_types=1);

namespace Tests\Unit\Teams;

use App\Modules\Core\Teams\Domain\Enums\TeamStructuralRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TeamStructuralRoleTest extends TestCase
{
    #[Test]
    public function structural_roles_have_explicit_relationship_capabilities(): void
    {
        self::assertFalse(TeamStructuralRole::Employee->canManageDirectReports());
        self::assertTrue(TeamStructuralRole::Employee->canParticipateAsReport());

        self::assertTrue(TeamStructuralRole::Manager->canManageDirectReports());
        self::assertTrue(TeamStructuralRole::Manager->canParticipateAsReport());

        self::assertFalse(TeamStructuralRole::HeadManager->canManageDirectReports());
        self::assertFalse(TeamStructuralRole::HeadManager->canParticipateAsReport());
        self::assertSame(['employee', 'manager', 'head_manager'], array_column(TeamStructuralRole::cases(), 'value'));
    }
}
