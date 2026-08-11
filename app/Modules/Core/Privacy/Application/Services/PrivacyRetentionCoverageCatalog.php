<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\Services;

use App\Modules\Core\Privacy\Application\DTOs\PrivacyCoverageItem;

final class PrivacyRetentionCoverageCatalog
{
    /**
     * @param  list<string>  $participantKeys
     * @return list<PrivacyCoverageItem>
     */
    public function items(array $participantKeys): array
    {
        $hasFileParticipant = in_array('files', $participantKeys, true);
        $hasSearchParticipant = in_array('search', $participantKeys, true);
        $hasManagedProcessParticipant = in_array('managed_processes', $participantKeys, true);
        $hasSharedDerivedDataParticipant = in_array('shared', $participantKeys, true);
        $hasExportParticipant = in_array('exports', $participantKeys, true);
        $hasUserParticipant = in_array('identity', $participantKeys, true);
        $hasTeamParticipant = in_array('teams', $participantKeys, true);
        $hasAuthorizationParticipant = in_array('authorization', $participantKeys, true);

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
