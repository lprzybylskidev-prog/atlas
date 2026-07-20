<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Infrastructure\Runtime;

use App\Modules\Optional\Integrations\Application\Contracts\IntegrationAdapter;
use App\Modules\Optional\Integrations\Application\Contracts\IntegrationRegistry;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationDefinition;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;
use RuntimeException;

final readonly class ConfiguredIntegrationRegistry implements IntegrationRegistry
{
    public function __construct(private Container $container) {}

    public function all(): array
    {
        return array_map(
            fn (IntegrationAdapter $adapter): IntegrationDefinition => $adapter->definition(),
            $this->adapters(),
        );
    }

    public function get(string $integrationKey): ?IntegrationAdapter
    {
        foreach ($this->adapters() as $adapter) {
            if ($adapter->definition()->key === $integrationKey) {
                return $adapter;
            }
        }

        return null;
    }

    /**
     * @return list<IntegrationAdapter>
     */
    private function adapters(): array
    {
        $classes = Config::array('atlas.integrations.adapters', []);
        $adapters = [];

        foreach ($classes as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                throw new RuntimeException('Configured integration adapter must be an existing class string.');
            }

            $adapter = $this->container->make($class);

            if (! $adapter instanceof IntegrationAdapter) {
                throw new RuntimeException(sprintf('Configured integration adapter [%s] must implement IntegrationAdapter.', $class));
            }

            $adapters[] = $adapter;
        }

        return $adapters;
    }
}
