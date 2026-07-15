<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contracts;

use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

interface ModuleDefinition
{
    public function key(): ModuleKey;

    public function category(): ModuleCategory;

    /**
     * @return list<ModuleKey>
     */
    public function requiredDependencies(): array;

    /**
     * @return list<ModuleKey>
     */
    public function optionalDependencies(): array;

    /**
     * @return class-string
     */
    public function serviceProvider(): string;

    public function supportsGlobalActivation(): bool;

    public function supportsTeamActivation(): bool;

    /**
     * @return list<string>
     */
    public function integrations(): array;

    /**
     * @return list<string>
     */
    public function healthChecks(): array;

    /**
     * @return list<string>
     */
    public function frontendEntrypoints(): array;
}
