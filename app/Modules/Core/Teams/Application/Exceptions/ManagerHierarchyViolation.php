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

    public static function staleStructure(): self
    {
        return new self('The team structure changed after this page was opened. Reload it before saving.');
    }

    public static function sameParent(): self
    {
        return new self('Select a different manager for this move.');
    }

    public static function invalidEffectiveDate(): self
    {
        return new self('The move date must fall between the current relationship start and now.');
    }

    public static function activeProcess(string $message = 'An active process blocks this hierarchy change.'): self
    {
        return new self($message);
    }

    public static function invalidStructuralRole(): self
    {
        return new self('Select a valid structural role.');
    }

    public static function unchangedStructuralRole(): self
    {
        return new self('The selected structural role is already active.');
    }

    public static function structuralRoleReasonRequired(): self
    {
        return new self('A reason is required to change the structural role.');
    }

    public static function managerRoleRequired(): self
    {
        return new self('Only a structural Manager can own direct-report relationships.');
    }

    public static function headManagerRelationshipForbidden(): self
    {
        return new self('A Head Manager cannot participate in normal manager relationships.');
    }

    public static function lastHeadManager(): self
    {
        return new self('The last active head manager cannot be changed. Assign another head manager first.');
    }
}
