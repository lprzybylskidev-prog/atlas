<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Money;

use App\Shared\Domain\Money\Currency;
use App\Shared\Domain\Money\CurrencyMismatch;
use App\Shared\Domain\Money\Money;
use InvalidArgumentException;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_stores_integer_minor_units_and_currency(): void
    {
        $money = Money::ofMinor(1250, Currency::PLN());

        self::assertSame(1250, $money->minorAmount);
        self::assertTrue(Currency::PLN()->equals($money->currency));
        self::assertSame([
            'minor_amount' => 1250,
            'currency' => 'PLN',
        ], $money->toArray());
    }

    public function test_it_uses_configured_default_currency_for_explicit_default_zero(): void
    {
        $money = Money::zeroDefaultCurrency();

        self::assertSame(0, $money->minorAmount);
        self::assertTrue(Currency::PLN()->equals($money->currency));
    }

    public function test_it_adds_and_subtracts_only_matching_currencies(): void
    {
        $first = Money::ofMinor(1200, Currency::PLN());
        $second = Money::ofMinor(250, Currency::PLN());

        self::assertSame(1450, $first->add($second)->minorAmount);
        self::assertSame(950, $first->subtract($second)->minorAmount);
    }

    public function test_it_compares_only_matching_currencies(): void
    {
        $lower = Money::ofMinor(100, Currency::PLN());
        $higher = Money::ofMinor(200, Currency::PLN());

        self::assertTrue($higher->isGreaterThan($lower));
        self::assertTrue($lower->isLessThan($higher));
        self::assertTrue($lower->isEqualTo(Money::ofMinor(100, Currency::PLN())));
    }

    public function test_it_rejects_implicit_mixed_currency_operations(): void
    {
        $pln = Money::ofMinor(100, Currency::PLN());
        $other = Money::ofMinor(100, Currency::fromIsoCode('EUR'));

        $this->expectException(CurrencyMismatch::class);

        $pln->add($other);
    }

    public function test_it_rejects_implicit_mixed_currency_comparisons(): void
    {
        $pln = Money::ofMinor(100, Currency::PLN());
        $other = Money::ofMinor(100, Currency::fromIsoCode('EUR'));

        $this->expectException(CurrencyMismatch::class);

        $pln->isEqualTo($other);
    }

    public function test_it_rejects_invalid_currency_codes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Currency::fromIsoCode('PL');
    }
}
