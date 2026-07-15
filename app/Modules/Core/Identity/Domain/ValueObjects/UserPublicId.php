<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Domain\ValueObjects;

use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

final readonly class UserPublicId
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        if (! Ulid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('User public ID [%s] is not a valid ULID.', $value));
        }

        return new self($value);
    }

    public static function new(): self
    {
        return new self((string) new Ulid);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
