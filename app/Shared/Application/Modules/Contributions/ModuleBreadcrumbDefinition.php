<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions;

use InvalidArgumentException;

final readonly class ModuleBreadcrumbDefinition
{
    public function __construct(
        public string $name,
        public string $routeName,
        public ?string $parentName = null,
    ) {
        $this->guardNonEmpty($name, 'breadcrumb name');
        $this->guardNonEmpty($routeName, 'breadcrumb route name');

        if ($parentName !== null) {
            $this->guardNonEmpty($parentName, 'breadcrumb parent name');
        }
    }

    private function guardNonEmpty(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Module %s must be a non-empty string.', $field));
        }
    }
}
