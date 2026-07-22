<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\Search\Application\Contracts\SearchDocumentStore;
use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\DTOs\SearchDocument;
use App\Modules\Optional\Search\Application\DTOs\SearchIndexDescriptor;
use App\Modules\Optional\Search\Application\Indexing\SearchOutboxEventIndexer;
use App\Modules\Optional\Search\Application\Lifecycle\SearchDataLifecycleParticipant;
use App\Modules\Optional\Search\Application\Permissions\SearchPermissionCatalog;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchClient;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchEventProjector;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchLifecycleProjector;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchRebuildDocumentProvider;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchHit;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchQuery;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchResult;
use App\Modules\Optional\Search\Application\Query\SearchService;
use App\Modules\Optional\Search\Application\Rebuild\SearchIndexMaintenanceService;
use App\Modules\Optional\Search\Application\SearchRebuildProcess;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Modules\ModuleKeyResolver;
use App\Shared\Application\Outbox\Contracts\OutboxConsumerDeduplicator;
use App\Shared\Application\Outbox\IntegrationEventMessage;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use DateTimeImmutable;
use Illuminate\Testing\PendingCommand;
use InvalidArgumentException;
use Tests\TestCase;

final class SearchModuleTest extends TestCase
{
    public function test_search_document_payload_contains_visibility_scope_fields(): void
    {
        $document = new SearchDocument(
            publicId: '01JZSEARCHDOC000000000001',
            indexKey: 'cases.people',
            moduleKey: 'cases',
            fields: ['display_name' => 'Jan Kowalski'],
            teamPublicIds: ['01JZTEAM000000000000001'],
            permissionKeys: ['cases.people.view'],
            visibilityHash: 'visibility-v1',
        );

        $this->assertSame([
            'id' => '01JZSEARCHDOC000000000001',
            'module_key' => 'cases',
            'team_public_ids' => ['01JZTEAM000000000000001'],
            'permission_keys' => ['cases.people.view'],
            'visibility_hash' => 'visibility-v1',
            'display_name' => 'Jan Kowalski',
        ], $document->toMeilisearchPayload());
    }

    public function test_search_index_registry_reads_explicit_tagged_descriptors(): void
    {
        $this->app->bind('tests.search.people_index', fn (): SearchIndexDescriptor => new SearchIndexDescriptor(
            key: 'cases.people',
            moduleKey: 'cases',
            stableAlias: 'atlas_cases_people',
            searchableFields: ['display_name'],
            filterableFields: ['module_key', 'team_public_ids', 'permission_keys'],
            sortableFields: ['display_name'],
        ));
        $this->app->tag(['tests.search.people_index'], 'atlas.search_index_descriptors');
        $this->app->forgetInstance(SearchIndexRegistry::class);

        $registry = $this->app->make(SearchIndexRegistry::class);

        $this->assertCount(1, $registry->all());
        $this->assertSame('atlas_cases_people', $registry->get('cases.people')?->stableAlias);
    }

    public function test_search_contracts_reject_unsafe_visibility_shapes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchDocument(
            publicId: '01JZSEARCHDOC000000000002',
            indexKey: 'cases.people',
            moduleKey: 'cases',
            fields: ['permission_keys' => []],
        );
    }

    public function test_search_query_requires_team_and_permission_scope(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchQuery(
            indexKey: 'cases.people',
            term: 'anna',
            activeTeamPublicId: '01JZTEAM000000000000001',
            userPublicId: '01JZUSER000000000000001',
            permissionKeys: [],
        );
    }

    public function test_search_service_enforces_module_gate_before_engine_query(): void
    {
        $this->app->bind('tests.search.people_index', fn (): SearchIndexDescriptor => new SearchIndexDescriptor(
            key: 'cases.people',
            moduleKey: 'cases',
            stableAlias: 'atlas_cases_people',
            searchableFields: ['display_name'],
            filterableFields: ['module_key', 'team_public_ids', 'permission_keys'],
            sortableFields: ['display_name'],
        ));
        $this->app->tag(['tests.search.people_index'], 'atlas.search_index_descriptors');
        $this->app->forgetInstance(SearchIndexRegistry::class);

        $service = new SearchService(
            indexes: $this->app->make(SearchIndexRegistry::class),
            engine: new FakeSearchClient,
            moduleGate: new AllowAllModuleGate,
        );

        $result = $service->search(new SearchQuery(
            indexKey: 'cases.people',
            term: 'anna',
            activeTeamPublicId: '01JZTEAM000000000000001',
            userPublicId: '01JZUSER000000000000001',
            permissionKeys: ['cases.people.view'],
        ));

        $this->assertSame('cases.people', $result->indexKey);
        $this->assertSame('01JZPERSON0000000000001', $result->hits[0]->publicId);
    }

    public function test_rebuild_command_requires_actor_and_team_context(): void
    {
        $command = $this->artisan('search:rebuild');

        if (! $command instanceof PendingCommand) {
            self::fail('Search rebuild command did not return a pending test command.');
        }

        $command
            ->expectsOutput('Both --actor and --team are required so Search rebuilds remain authorized and audited.')
            ->assertFailed();
    }

    public function test_search_registers_permissions_process_definition_and_module_mapping(): void
    {
        $permissions = (new SearchPermissionCatalog)->permissions();
        $resolver = new ModuleKeyResolver;
        $definition = $this->app->make(ProcessDefinitionRegistry::class)->get(SearchRebuildProcess::KEY);

        if ($definition === null) {
            self::fail('Search rebuild process definition was not registered.');
        }

        $this->assertSame('search', $resolver->forPermission(SearchPermissionCatalog::ADMIN_REBUILD));
        $this->assertSame(SearchRebuildProcess::KEY, $definition->key);
        $this->assertSame('search', $definition->moduleKey);
        $this->assertSame('search', $definition->queueName);
        $this->assertContains(SearchPermissionCatalog::QUERY, array_map(static fn ($permission): string => $permission->name, $permissions));
    }

    public function test_search_outbox_indexer_is_idempotent_and_writes_projected_documents(): void
    {
        $this->app->bind('tests.search.people_index', fn (): SearchIndexDescriptor => new SearchIndexDescriptor(
            key: 'cases.people',
            moduleKey: 'cases',
            stableAlias: 'atlas_cases_people',
            searchableFields: ['display_name'],
            filterableFields: ['module_key', 'team_public_ids', 'permission_keys'],
            sortableFields: ['display_name'],
        ));
        $this->app->tag(['tests.search.people_index'], 'atlas.search_index_descriptors');
        $this->app->forgetInstance(SearchIndexRegistry::class);

        $store = new RecordingSearchDocumentStore;
        $deduplicator = new InMemoryOutboxDeduplicator;
        $indexer = new SearchOutboxEventIndexer(
            indexes: $this->app->make(SearchIndexRegistry::class),
            documents: $store,
            deduplicator: $deduplicator,
            modules: new OperationalModuleGuard(new AllowAllModuleGate),
        );
        $event = new IntegrationEventMessage(
            eventId: '01JZOUTBOXSEARCH00000000001',
            eventType: 'cases.person_changed',
            schemaVersion: 1,
            sourceModule: 'cases',
            payload: ['public_id' => '01JZPERSON0000000000001', 'display_name' => 'Anna Nowak'],
            occurredAt: new DateTimeImmutable('2026-07-20T10:00:00+00:00'),
            correlationId: 'corr-search-test',
        );

        $this->assertTrue($indexer->handle($event, [new PersonChangedProjector]));
        $this->assertFalse($indexer->handle($event, [new PersonChangedProjector]));

        $this->assertSame(['atlas_cases_people'], $store->configuredAliases);
        $this->assertSame('01JZPERSON0000000000001', $store->upserted[0]['id']);
        $this->assertSame(['01JZTEAM000000000000001'], $store->upserted[0]['team_public_ids']);
        $this->assertSame(['cases.people.view'], $store->upserted[0]['permission_keys']);
        $this->assertCount(1, $store->upserted);
    }

    public function test_search_maintenance_rebuilds_physical_index_and_promotes_after_count_validation(): void
    {
        $descriptor = new SearchIndexDescriptor(
            key: 'cases.people',
            moduleKey: 'cases',
            stableAlias: 'atlas_cases_people',
            searchableFields: ['display_name'],
            filterableFields: ['module_key', 'team_public_ids', 'permission_keys'],
            sortableFields: ['display_name'],
        );
        $store = new RecordingSearchDocumentStore;
        $maintenance = new SearchIndexMaintenanceService(new FixedSearchIndexRegistry([$descriptor]), $store);

        $reports = $maintenance->rebuild(null, null, [new PeopleRebuildProvider]);

        $this->assertCount(1, $reports);
        $this->assertSame(1, $reports[0]->expectedDocuments);
        $this->assertSame(1, $reports[0]->indexedDocuments);
        $this->assertSame(0, $reports[0]->discrepancy);
        $this->assertSame(['atlas_cases_people'], $store->promotedAliases);
    }

    public function test_search_data_lifecycle_deletes_projected_documents_idempotently(): void
    {
        $descriptor = new SearchIndexDescriptor(
            key: 'cases.people',
            moduleKey: 'cases',
            stableAlias: 'atlas_cases_people',
            searchableFields: ['display_name'],
            filterableFields: ['module_key', 'team_public_ids', 'permission_keys'],
            sortableFields: ['display_name'],
        );
        $store = new RecordingSearchDocumentStore;
        $participant = new SearchDataLifecycleParticipant(new FixedSearchIndexRegistry([$descriptor]), $store);
        $this->app->bind('tests.search.lifecycle_projector', fn (): SearchLifecycleProjector => new PeopleLifecycleProjector);
        $this->app->tag(['tests.search.lifecycle_projector'], 'atlas.search_lifecycle_projectors');

        $result = $participant->execute(
            new DataLifecycleSubject('person', '01JZPERSON0000000000001'),
            DataLifecycleOperation::Delete,
            'corr-lifecycle',
        );

        $this->assertTrue($result->completed());
        $this->assertSame(['01JZPERSON0000000000001'], $store->deleted['atlas_cases_people'] ?? []);
    }
}

final class PersonChangedProjector implements SearchEventProjector
{
    public function supports(IntegrationEventMessage $event): bool
    {
        return $event->eventType === 'cases.person_changed';
    }

    public function documentsFor(IntegrationEventMessage $event): array
    {
        $publicId = $event->payload['public_id'] ?? null;
        $displayName = $event->payload['display_name'] ?? null;

        if (! is_string($publicId) || ! is_string($displayName)) {
            return [];
        }

        return [
            new SearchDocument(
                publicId: $publicId,
                indexKey: 'cases.people',
                moduleKey: 'cases',
                fields: ['display_name' => $displayName],
                teamPublicIds: ['01JZTEAM000000000000001'],
                permissionKeys: ['cases.people.view'],
                visibilityHash: 'visibility-v1',
            ),
        ];
    }

    public function deletedDocumentIdsFor(IntegrationEventMessage $event): array
    {
        return [];
    }
}

final class RecordingSearchDocumentStore implements SearchDocumentStore
{
    /** @var list<string> */
    public array $configuredAliases = [];

    /** @var list<string> */
    public array $physicalIndexes = [];

    /** @var list<string> */
    public array $promotedAliases = [];

    /** @var list<array<string, mixed>> */
    public array $upserted = [];

    /** @var array<string, list<string>> */
    public array $deleted = [];

    public function configure(\App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor $descriptor): void
    {
        $this->configuredAliases[] = $descriptor->stableAlias;
    }

    public function createPhysicalIndex(\App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor $descriptor, string $physicalIndex): void
    {
        $this->physicalIndexes[] = $physicalIndex;
        $this->configuredAliases[] = $physicalIndex;
    }

    public function upsert(\App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor $descriptor, \App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument $document): void
    {
        $this->upserted[] = $document->toMeilisearchPayload();
    }

    public function upsertInto(\App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor $descriptor, string $physicalIndex, \App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument $document): void
    {
        $this->upserted[] = ['_index' => $physicalIndex, ...$document->toMeilisearchPayload()];
    }

    public function delete(\App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor $descriptor, array $documentPublicIds): void
    {
        $this->deleted[$descriptor->stableAlias] = $documentPublicIds;
    }

    public function count(string $indexName): int
    {
        return count(array_filter($this->upserted, static fn (array $document): bool => ($document['_index'] ?? null) === $indexName));
    }

    public function promote(\App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor $descriptor, string $physicalIndex): void
    {
        $this->promotedAliases[] = $descriptor->stableAlias;
    }
}

final readonly class FixedSearchIndexRegistry implements SearchIndexRegistry
{
    /**
     * @param  list<SearchIndexDescriptor>  $indexes
     */
    public function __construct(private array $indexes) {}

    public function all(): array
    {
        return $this->indexes;
    }

    public function get(string $indexKey): ?\App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor
    {
        foreach ($this->indexes as $index) {
            if ($index->key === $indexKey) {
                return $index;
            }
        }

        return null;
    }
}

final class PeopleRebuildProvider implements SearchRebuildDocumentProvider
{
    public function indexKey(): string
    {
        return 'cases.people';
    }

    public function expectedDocumentCount(): int
    {
        return 1;
    }

    public function documents(): iterable
    {
        yield new SearchDocument(
            publicId: '01JZPERSON0000000000001',
            indexKey: 'cases.people',
            moduleKey: 'cases',
            fields: ['display_name' => 'Anna Nowak'],
            teamPublicIds: ['01JZTEAM000000000000001'],
            permissionKeys: ['cases.people.view'],
        );
    }
}

final class PeopleLifecycleProjector implements SearchLifecycleProjector
{
    public function supports(DataLifecycleSubject $subject, DataLifecycleOperation $operation): bool
    {
        return $subject->type === 'person' && $operation === DataLifecycleOperation::Delete;
    }

    public function documentIdsFor(DataLifecycleSubject $subject, DataLifecycleOperation $operation): array
    {
        return ['cases.people' => [$subject->identifier]];
    }
}

final class FakeSearchClient implements SearchClient
{
    public function search(SearchQuery $query): SearchResult
    {
        return new SearchResult(
            indexKey: $query->indexKey,
            hits: [new SearchHit('01JZPERSON0000000000001', 'cases', ['display_name' => 'Anna Nowak'])],
            estimatedTotal: 1,
        );
    }
}

final class InMemoryOutboxDeduplicator implements OutboxConsumerDeduplicator
{
    /** @var array<string, true> */
    private array $seen = [];

    public function recordIfFirst(string $eventId, string $consumer): bool
    {
        $key = $eventId.'|'.$consumer;

        if (isset($this->seen[$key])) {
            return false;
        }

        $this->seen[$key] = true;

        return true;
    }
}

final class AllowAllModuleGate implements ModuleGate
{
    public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
    {
        return ModuleAccessDecision::allow();
    }

    public function allows(ModuleAccessRequest $request): bool
    {
        return true;
    }
}
