<?php

declare(strict_types=1);

namespace App\Support\Money;

use DomainException;

final class CurrencyMismatch extends DomainException
{
    public static function between(Currency $left, Currency $right): self
    {
        return new self(sprintf(
            'Money currencies must match. Got [%s] and [%s].',
            $left->code,
            $right->code,
        ));
    }
}
