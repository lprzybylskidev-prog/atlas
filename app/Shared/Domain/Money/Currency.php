<?php

declare(strict_types=1);

namespace App\Shared\Domain\Money;

use InvalidArgumentException;

final readonly class Currency
{
    private function __construct(
        public string $code,
    ) {}

    public static function PLN(): self
    {
        return self::fromIsoCode('PLN');
    }

    public static function fromIsoCode(string $code): self
    {
        $normalized = strtoupper($code);

        if (preg_match('/^[A-Z]{3}$/', $normalized) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Currency code must be a three-letter ISO 4217 code. Got [%s].',
                $code,
            ));
        }

        return new self($normalized);
    }

    public static function default(): self
    {
        return self::fromIsoCode(config()->string('atlas.currency.default'));
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
