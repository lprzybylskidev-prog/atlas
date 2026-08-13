<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopeContext;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopePolicy;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConversationScopePolicyTest extends TestCase
{
    #[DataProvider('scopeProvider')]
    public function test_conversation_scope_is_explicit(ConversationScopeContext $context, bool $allowed): void
    {
        self::assertSame($allowed, (new ConversationScopePolicy)->allows($context));
    }

    /** @return iterable<string, array{ConversationScopeContext, bool}> */
    public static function scopeProvider(): iterable
    {
        yield 'direct ignores active team' => [
            new ConversationScopeContext(ConversationType::Direct, true, activeTeamPublicId: 'team-b'),
            true,
        ];
        yield 'group ignores active team' => [
            new ConversationScopeContext(ConversationType::Group, true, activeTeamPublicId: 'team-b'),
            true,
        ];
        yield 'non-member cannot read global conversation' => [
            new ConversationScopeContext(ConversationType::Group, false),
            false,
        ];
        yield 'team conversation follows active team' => [
            new ConversationScopeContext(ConversationType::Team, true, 'team-a', 'team-a'),
            true,
        ];
        yield 'team conversation rejects another active team' => [
            new ConversationScopeContext(ConversationType::Team, true, 'team-a', 'team-b'),
            false,
        ];
        yield 'meeting participant ignores active team' => [
            new ConversationScopeContext(ConversationType::Meeting, false, activeTeamPublicId: 'team-b', isMeetingParticipant: true),
            true,
        ];
        yield 'meeting membership is required even for a chat member flag' => [
            new ConversationScopeContext(ConversationType::Meeting, true, isMeetingParticipant: false),
            false,
        ];
    }
}
