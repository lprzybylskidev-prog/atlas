<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Mfa;

final class MfaRequirementEvaluator
{
    public function isRequired(MfaRequirementContext $context): bool
    {
        $config = config('atlas.security.mfa.requirements');

        if (! is_array($config)) {
            return false;
        }

        if (($config['global'] ?? false) === true) {
            return true;
        }

        return $this->containsConfiguredValue($config['users'] ?? [], $context->userPublicId)
            || $this->containsConfiguredValue($config['teams'] ?? [], $context->teamPublicId)
            || $this->containsConfiguredValue($config['operations'] ?? [], $context->operation)
            || $this->containsAnyConfiguredValue($config['permissions'] ?? [], $context->permissions);
    }

    private function containsConfiguredValue(mixed $configuredValues, ?string $value): bool
    {
        if ($value === null || ! is_array($configuredValues)) {
            return false;
        }

        return in_array($value, array_filter($configuredValues, 'is_string'), true);
    }

    /**
     * @param  list<string>  $values
     */
    private function containsAnyConfiguredValue(mixed $configuredValues, array $values): bool
    {
        if (! is_array($configuredValues) || $values === []) {
            return false;
        }

        $configuredValues = array_filter($configuredValues, 'is_string');

        foreach ($values as $value) {
            if (in_array($value, $configuredValues, true)) {
                return true;
            }
        }

        return false;
    }
}
