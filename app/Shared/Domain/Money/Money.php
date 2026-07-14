<?php

declare(strict_types=1);

namespace App\Shared\Domain\Money;

final readonly class Money
{
    private function __construct(
        public int $minorAmount,
        public Currency $currency,
    ) {}

    public static function ofMinor(int $minorAmount, Currency $currency): self
    {
        return new self($minorAmount, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public static function zeroDefaultCurrency(): self
    {
        return self::zero(Currency::default());
    }

    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->minorAmount + $other->minorAmount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->minorAmount - $other->minorAmount, $this->currency);
    }

    public function isEqualTo(self $other): bool
    {
        $this->ensureSameCurrency($other);

        return $this->minorAmount === $other->minorAmount;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->ensureSameCurrency($other);

        return $this->minorAmount > $other->minorAmount;
    }

    public function isLessThan(self $other): bool
    {
        $this->ensureSameCurrency($other);

        return $this->minorAmount < $other->minorAmount;
    }

    /**
     * @return array{minor_amount: int, currency: string}
     */
    public function toArray(): array
    {
        return [
            'minor_amount' => $this->minorAmount,
            'currency' => $this->currency->code,
        ];
    }

    private function ensureSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }
    }
}
