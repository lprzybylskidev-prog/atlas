<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Presentation\Providers;

use App\Modules\Optional\Search\Application\Contracts\SearchDocumentStore;
use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Exports\AdminSearchIndexesDataTableExportProvider;
use App\Modules\Optional\Search\Application\Exports\AdminSearchRebuildsDataTableExportProvider;
use App\Modules\Optional\Search\Application\Indexing\SearchOutboxEventDispatcher;
use App\Modules\Optional\Search\Application\Lifecycle\SearchDataLifecycleParticipant;
use App\Modules\Optional\Search\Application\Permissions\SearchPermissionCatalog;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchClient;
use App\Modules\Optional\Search\Application\Query\SearchService;
use App\Modules\Optional\Search\Application\SearchRebuildProcess;
use App\Modules\Optional\Search\Infrastructure\Meilisearch\MeilisearchDocumentStore;
use App\Modules\Optional\Search\Infrastructure\Meilisearch\MeilisearchSearchClient;
use App\Modules\Optional\Search\Infrastructure\Runtime\ConfiguredSearchIndexRegistry;
use App\Modules\Optional\Search\Infrastructure\Runtime\SearchRebuildProcessHandler;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SearchIndexRegistry::class, ConfiguredSearchIndexRegistry::class);
        $this->app->singleton(SearchOutboxEventDispatcher::class);
        $this->app->singleton(Client::class, fn (): Client => new Client(
            config()->string('scout.meilisearch.host'),
            config()->string('scout.meilisearch.key', ''),
        ));
        $this->app->bind(SearchDocumentStore::class, MeilisearchDocumentStore::class);
        $this->app->bind('search.engine_client', MeilisearchSearchClient::class);
        $this->app->bind(SearchClient::class, fn (): SearchClient => new SearchService(
            $this->app->make(SearchIndexRegistry::class),
            $this->app->make('search.engine_client'),
            $this->app->make(ModuleGate::class),
        ));
        $this->app->bind('search.managed_process.rebuild_definition', fn () => SearchRebuildProcess::definition());

        $this->app->tag([SearchPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([SearchDataLifecycleParticipant::class], 'atlas.data_lifecycle_participants');
        $this->app->tag(['search.managed_process.rebuild_definition'], 'atlas.managed_process_definitions');
        $this->app->tag([SearchRebuildProcessHandler::class], 'atlas.managed_process_handlers');
        $this->app->tag([
            AdminSearchIndexesDataTableExportProvider::class,
            AdminSearchRebuildsDataTableExportProvider::class,
        ], 'atlas.admin_data_table_export_providers');
    }
}
