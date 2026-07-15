<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Identity\Application\Mfa\MfaRequirementContext;
use App\Modules\Core\Identity\Application\Mfa\MfaRequirementEvaluator;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class MfaRequirementEvaluatorTest extends TestCase
{
    public function test_mfa_can_be_required_globally(): void
    {
        Config::set('atlas.security.mfa.requirements.global', true);

        self::assertTrue(new MfaRequirementEvaluator()->isRequired(new MfaRequirementContext));
    }

    public function test_mfa_can_be_required_by_user_team_permission_or_operation(): void
    {
        Config::set('atlas.security.mfa.requirements', [
            'global' => false,
            'users' => ['user-1'],
            'teams' => ['team-1'],
            'permissions' => ['payments.approve'],
            'operations' => ['exports.create'],
        ]);

        $evaluator = new MfaRequirementEvaluator;

        self::assertTrue($evaluator->isRequired(new MfaRequirementContext(userPublicId: 'user-1')));
        self::assertTrue($evaluator->isRequired(new MfaRequirementContext(teamPublicId: 'team-1')));
        self::assertTrue($evaluator->isRequired(new MfaRequirementContext(permissions: ['payments.approve'])));
        self::assertTrue($evaluator->isRequired(new MfaRequirementContext(operation: 'exports.create')));
        self::assertFalse($evaluator->isRequired(new MfaRequirementContext(userPublicId: 'user-2')));
    }
}
