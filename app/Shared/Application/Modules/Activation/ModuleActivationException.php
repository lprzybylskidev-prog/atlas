<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Activation;

use RuntimeException;

final class ModuleActivationException extends RuntimeException
{
    public static function coreModuleCannotBeDisabled(string $moduleKey): self
    {
        return new self(sprintf('Core module [%s] cannot be disabled operationally.', $moduleKey));
    }

    public static function unavailableModuleCannotBeActivated(string $moduleKey): self
    {
        return new self(sprintf('Technically unavailable module [%s] cannot be activated.', $moduleKey));
    }

    public static function teamScopeNotSupported(string $moduleKey): self
    {
        return new self(sprintf('Module [%s] does not support team activation.', $moduleKey));
    }

    public static function globalScopeNotSupported(string $moduleKey): self
    {
        return new self(sprintf('Module [%s] does not support global activation.', $moduleKey));
    }

    public static function conflictingSchedule(string $moduleKey): self
    {
        return new self(sprintf('Module [%s] already has a conflicting scheduled activation change.', $moduleKey));
    }

    public static function staleState(string $moduleKey): self
    {
        return new self(sprintf('Module [%s] activation state was changed by another request.', $moduleKey));
    }

    public static function unsafeProcessesBlockDeactivation(string $moduleKey): self
    {
        return new self(sprintf('Module [%s] cannot be disabled while unsafe processes are active.', $moduleKey));
    }
}
