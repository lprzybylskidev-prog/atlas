<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Identity\Domain\ValueObjects\UserPublicId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UserPublicIdTest extends TestCase
{
    public function test_user_public_id_accepts_valid_ulid(): void
    {
        $value = (string) new Ulid;

        self::assertSame($value, UserPublicId::fromString($value)->toString());
    }

    public function test_user_public_id_rejects_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UserPublicId::fromString('not-a-ulid');
    }

    public function test_new_user_public_id_generates_ulid(): void
    {
        self::assertTrue(Ulid::isValid(UserPublicId::new()->toString()));
    }
}
