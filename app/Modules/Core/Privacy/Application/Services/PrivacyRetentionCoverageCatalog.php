<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\Services;

use App\Modules\Core\Privacy\Application\DTOs\PrivacyCoverageItem;

final class PrivacyRetentionCoverageCatalog
{
    private const AUTHORIZATION_PARTICIPANT_CLASS = 'App\Modules\Core\Authorization\Application\Lifecycle\UserAuthorizationDataLifecycleParticipant';

    private const EXPORT_PARTICIPANT_CLASS = 'App\Modules\Core\Exports\Application\Lifecycle\ExportDataLifecycleParticipant';

    private const FILE_PARTICIPANT_CLASS = 'App\Modules\Core\Files\Application\Lifecycle\FileDataLifecycleParticipant';

    private const SEARCH_PARTICIPANT_CLASS = 'App\Modules\Optional\Search\Application\Lifecycle\SearchDataLifecycleParticipant';

    private const MANAGED_PROCESS_PARTICIPANT_CLASS = 'App\Modules\Optional\ManagedProcesses\Application\Lifecycle\ManagedProcessDataLifecycleParticipant';

    private const SHARED_DERIVED_DATA_PARTICIPANT_CLASS = 'App\Shared\Infrastructure\DataLifecycle\SharedDerivedDataLifecycleParticipant';

    private const TEAM_PARTICIPANT_CLASS = 'App\Modules\Core\Teams\Application\Lifecycle\TeamUserDataLifecycleParticipant';

    private const USER_PARTICIPANT_CLASS = 'App\Modules\Core\Users\Application\Lifecycle\UserAccountDataLifecycleParticipant';

    /**
     * @param  list<class-string>  $participantClasses
     * @return list<PrivacyCoverageItem>
     */
    public function items(array $participantClasses): array
    {
        $hasFileParticipant = in_array(self::FILE_PARTICIPANT_CLASS, $participantClasses, true);
        $hasSearchParticipant = in_array(self::SEARCH_PARTICIPANT_CLASS, $participantClasses, true);
        $hasManagedProcessParticipant = in_array(self::MANAGED_PROCESS_PARTICIPANT_CLASS, $participantClasses, true);
        $hasSharedDerivedDataParticipant = in_array(self::SHARED_DERIVED_DATA_PARTICIPANT_CLASS, $participantClasses, true);
        $hasExportParticipant = in_array(self::EXPORT_PARTICIPANT_CLASS, $participantClasses, true);
        $hasUserParticipant = in_array(self::USER_PARTICIPANT_CLASS, $participantClasses, true);
        $hasTeamParticipant = in_array(self::TEAM_PARTICIPANT_CLASS, $participantClasses, true);
        $hasAuthorizationParticipant = in_array(self::AUTHORIZATION_PARTICIPANT_CLASS, $participantClasses, true);

        return [
            new PrivacyCoverageItem('identity-users', 'identity.users', 'identity', $hasUserParticipant ? 'implemented' : 'planned', 'restricted', 'implemented', true, $hasUserParticipant),
            new PrivacyCoverageItem('teams-authorization', 'teams.authorization_assignments', 'teams', ($hasTeamParticipant && $hasAuthorizationParticipant) ? 'implemented' : 'planned', 'restricted', 'implemented', true, $hasTeamParticipant && $hasAuthorizationParticipant),
            new PrivacyCoverageItem('audit-events', 'audit.events', 'audit', 'retention_exception', 'blocked', 'limited', true, false),
            new PrivacyCoverageItem('files-private-storage', 'files.private_objects', 'files', 'implemented', 'restricted', 'implemented', true, $hasFileParticipant),
            new PrivacyCoverageItem('managed-processes', 'managed_processes.runs_and_logs', 'managed_processes', $hasManagedProcessParticipant ? 'implemented' : 'planned', 'restricted', 'implemented', true, $hasManagedProcessParticipant),
            new PrivacyCoverageItem('search-indexes', 'search.index_documents', 'search', $hasSearchParticipant ? 'partial' : 'planned', 'remove_projection', 'remove_projection', false, $hasSearchParticipant),
            new PrivacyCoverageItem('cache-queues', 'shared.cache_and_queues', 'shared', $hasSharedDerivedDataParticipant ? 'implemented' : 'planned', 'purge_derived', 'purge_derived', false, $hasSharedDerivedDataParticipant),
            new PrivacyCoverageItem('exports-artifacts', 'exports.artifacts', 'exports', $hasExportParticipant ? 'implemented' : 'partial', 'retention_cleanup', 'implemented', true, $hasExportParticipant),
        ];
    }
}
