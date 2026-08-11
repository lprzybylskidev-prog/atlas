<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use App\Shared\Application\Modules\Contracts\ModuleOperationalDiagnostics;

final class ModuleOperationalDiagnosticsRegistry
{
    /**
     * @var array<string, ModuleOperationalDiagnostics>
     */
    private array $diagnostics = [];

    /**
     * @param  iterable<ModuleOperationalDiagnostics>  $diagnostics
     */
    public function __construct(iterable $diagnostics)
    {
        foreach ($diagnostics as $diagnostic) {
            $this->diagnostics[$diagnostic->moduleKey()] = $diagnostic;
        }
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    public function issuesFor(string $moduleKey): array
    {
        $diagnostic = $this->diagnostics[$moduleKey] ?? null;

        return $diagnostic?->issues() ?? [];
    }
}
