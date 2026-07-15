<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions;

use InvalidArgumentException;

final readonly class ModuleMenuItem
{
    public function __construct(
        public string $key,
        public string $label,
        public string $routeName,
        public ?string $requiredPermission = null,
    ) {
        $this->guardNonEmpty($key, 'menu key');
        $this->guardNonEmpty($label, 'menu label');
        $this->guardNonEmpty($routeName, 'menu route name');

        if ($requiredPermission !== null) {
            $this->guardNonEmpty($requiredPermission, 'menu required permission');
        }
    }

    private function guardNonEmpty(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Module %s must be a non-empty string.', $field));
        }
    }
}
