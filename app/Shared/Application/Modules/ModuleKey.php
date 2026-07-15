<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use InvalidArgumentException;
use Stringable;

final readonly class ModuleKey implements Stringable
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid module key [%s].', $value));
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
