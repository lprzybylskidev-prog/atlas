<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Exceptions;

use RuntimeException;

final class ManagerHierarchyViolation extends RuntimeException
{
    public static function selfManagement(): self
    {
        return new self('A user cannot manage themselves.');
    }

    public static function cycle(): self
    {
        return new self('This relationship would create a manager hierarchy cycle.');
    }

    public static function inactiveMembership(): self
    {
        return new self('Both users must have active access to the selected team.');
    }

    public static function duplicateActiveRelationship(): self
    {
        return new self('This manager relationship is already active.');
    }

    public static function missingActiveRelationship(): self
    {
        return new self('The selected manager relationship is not active.');
    }
}
