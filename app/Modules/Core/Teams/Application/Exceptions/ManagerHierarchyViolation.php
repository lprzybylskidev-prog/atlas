<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Exceptions;

use RuntimeException;

final class ManagerHierarchyViolation extends RuntimeException
{
    public function __construct(public readonly string $errorKey, string $message)
    {
        parent::__construct($message);
    }

    public static function selfManagement(): self
    {
        return new self('self_management', 'A user cannot manage themselves.');
    }

    public static function cycle(): self
    {
        return new self('cycle', 'This relationship would create a manager hierarchy cycle.');
    }

    public static function inactiveMembership(): self
    {
        return new self('inactive_membership', 'Both users must have active access to the selected team.');
    }

    public static function duplicateActiveRelationship(): self
    {
        return new self('duplicate_active_relationship', 'This manager relationship is already active.');
    }

    public static function missingActiveRelationship(): self
    {
        return new self('missing_active_relationship', 'The selected manager relationship is not active.');
    }

    public static function staleStructure(): self
    {
        return new self('stale_structure', 'The team structure changed after this page was opened. Reload it before saving.');
    }

    public static function invalidStructuralRole(): self
    {
        return new self('invalid_structural_role', 'Select a valid structural role.');
    }

    public static function unchangedStructuralRole(): self
    {
        return new self('unchanged_structural_role', 'The selected structural role is already active.');
    }

    public static function structuralRoleReasonRequired(): self
    {
        return new self('structural_role_reason_required', 'A reason is required to change the structural role.');
    }

    public static function managerRoleRequired(): self
    {
        return new self('manager_role_required', 'Only a structural Manager can own direct-report relationships.');
    }

    public static function headManagerRelationshipForbidden(): self
    {
        return new self('head_manager_relationship_forbidden', 'A Head Manager cannot participate in normal manager relationships.');
    }

    public static function lastHeadManager(): self
    {
        return new self('last_head_manager', 'The last active head manager cannot be changed. Assign another head manager first.');
    }
}
