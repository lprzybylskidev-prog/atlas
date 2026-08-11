<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class LegacyReferenceScanner
{
    /**
     * @param  array<string, string>  $sources
     * @param  list<string>  $forbiddenReferences
     * @return list<string>
     */
    public function violations(array $sources, array $forbiddenReferences): array
    {
        $violations = [];

        foreach ($sources as $path => $contents) {
            foreach ($forbiddenReferences as $reference) {
                if (str_contains($contents, $reference)) {
                    $violations[] = $path.' -> '.$reference;
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
