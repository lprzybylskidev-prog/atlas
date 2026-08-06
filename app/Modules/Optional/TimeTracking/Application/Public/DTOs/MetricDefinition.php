<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\DTOs;

use InvalidArgumentException;

final readonly class MetricDefinition
{
    /**
     * @param  list<string>  $sourceEventKeys
     */
    public function __construct(
        public string $metricKey,
        public string $ownerModuleKey,
        public int $ruleVersion,
        public string $calculationRuleKey,
        public string $labelTranslationKey,
        public array $sourceEventKeys,
    ) {
        if (
            trim($metricKey) === ''
            || trim($ownerModuleKey) === ''
            || trim($calculationRuleKey) === ''
            || trim($labelTranslationKey) === ''
        ) {
            throw new InvalidArgumentException('Metric definition keys and label translation key must be non-empty strings.');
        }

        if ($ruleVersion < 1) {
            throw new InvalidArgumentException('Metric definition rule version must be positive.');
        }

        if ($sourceEventKeys === []) {
            throw new InvalidArgumentException('Metric definitions must declare at least one source event key.');
        }

        foreach ($sourceEventKeys as $sourceEventKey) {
            if (trim($sourceEventKey) === '') {
                throw new InvalidArgumentException('Metric definition source event keys must be non-empty strings.');
            }
        }
    }
}
