<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Lifecycle;

use App\Modules\Optional\Search\Application\Contracts\SearchDocumentStore;
use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchLifecycleProjector;
use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;

final readonly class SearchDataLifecycleParticipant implements DataLifecycleParticipant
{
    public function __construct(
        private SearchIndexRegistry $indexes,
        private SearchDocumentStore $documents,
    ) {}

    public function preview(DataLifecycleSubject $subject, DataLifecycleOperation $operation): DataLifecyclePreview
    {
        return new DataLifecyclePreview([
            new DataLifecycleImpact('search.indexes', $this->countAffected($subject, $operation), true, $this->details($subject, $operation)),
        ]);
    }

    public function execute(DataLifecycleSubject $subject, DataLifecycleOperation $operation, string $correlationId): DataLifecycleResult
    {
        $affected = 0;

        foreach ($this->projectors() as $projector) {
            if (! $projector->supports($subject, $operation)) {
                continue;
            }

            foreach ($projector->documentIdsFor($subject, $operation) as $indexKey => $documentPublicIds) {
                $descriptor = $this->indexes->get($indexKey);

                if ($descriptor === null || $documentPublicIds === []) {
                    continue;
                }

                $this->documents->delete($descriptor, $documentPublicIds);
                $affected += count($documentPublicIds);
            }
        }

        return new DataLifecycleResult([
            new DataLifecycleStepResult('search.index_documents_removed', $affected, true),
        ]);
    }

    private function countAffected(DataLifecycleSubject $subject, DataLifecycleOperation $operation): int
    {
        $affected = 0;

        foreach ($this->projectors() as $projector) {
            if (! $projector->supports($subject, $operation)) {
                continue;
            }

            foreach ($projector->documentIdsFor($subject, $operation) as $documentPublicIds) {
                $affected += count($documentPublicIds);
            }
        }

        return $affected;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function details(DataLifecycleSubject $subject, DataLifecycleOperation $operation): array
    {
        $details = [];

        foreach ($this->projectors() as $projector) {
            if (! $projector->supports($subject, $operation)) {
                continue;
            }

            foreach ($projector->documentIdsFor($subject, $operation) as $indexKey => $documentPublicIds) {
                foreach ($documentPublicIds as $documentPublicId) {
                    $details[] = [
                        'index_key' => $indexKey,
                        'document_public_id' => $documentPublicId,
                    ];
                }
            }
        }

        return $details;
    }

    /**
     * @return list<SearchLifecycleProjector>
     */
    private function projectors(): array
    {
        $projectors = [];

        foreach (app()->tagged('atlas.search_lifecycle_projectors') as $projector) {
            if ($projector instanceof SearchLifecycleProjector) {
                $projectors[] = $projector;
            }
        }

        return $projectors;
    }
}
