<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Enums\ReportExportStatus;
use App\Modules\Core\Exports\Application\Exceptions\ReportRenderCredentialInvalid;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportPrintViewAccess;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportRenderCredentialAccess;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportRenderCredentialIssuer;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Exports\Application\Public\Persistence\ExportsDatabaseTable;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class ReportPrintViewService implements ReportPrintViewAccess
{
    public function __construct(
        private ConnectionInterface $database,
        private OperationalModuleGuard $modules,
        private ReportRenderCredentialIssuer $issuer,
        private ReportRenderCredentialAccess $credentials,
        private ReportHtmlDocumentFactory $html,
    ) {}

    public function html(string $exportRequestPublicId, string $actorPublicId, ?string $activeTeamPublicId): string
    {
        $request = $this->request($exportRequestPublicId);
        $teamPublicId = $this->nullableString($request, 'active_team_public_id');
        $requestingUserPublicId = $this->requiredString($request, 'requesting_user_public_id');
        $status = $this->requiredString($request, 'status');

        if ($requestingUserPublicId !== $actorPublicId || $teamPublicId !== $activeTeamPublicId) {
            throw ReportRenderCredentialInvalid::blocked();
        }

        if (in_array($status, [ReportExportStatus::Cancelled->value, ReportExportStatus::Expired->value, ReportExportStatus::Failed->value], true) || $this->expired($request->expires_at ?? null)) {
            throw ReportRenderCredentialInvalid::blocked();
        }

        $this->modules->ensureAllowed('exports', $teamPublicId, $actorPublicId, ReportsPermissionCatalog::PRINT);
        $this->modules->ensureAllowed($this->requiredString($request, 'module_key'), $teamPublicId, $actorPublicId);
        if ((bool) ($request->audit_export ?? false)) {
            $this->modules->ensureAllowed('exports', $teamPublicId, $actorPublicId, ReportsPermissionCatalog::AUDIT_EXPORT);
        }

        $issued = $this->issuer->issue($exportRequestPublicId);
        $resolved = $this->credentials->resolve($issued->token);
        $html = $this->html->tableReport($resolved->request, browserPrint: true);
        $this->credentials->consume($resolved->publicId);

        return $html;
    }

    private function request(string $publicId): stdClass
    {
        $request = $this->database->table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $publicId)->first();

        if ($request instanceof stdClass) {
            return $request;
        }

        throw ReportRenderCredentialInvalid::blocked();
    }

    private function requiredString(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw ReportRenderCredentialInvalid::blocked();
    }

    private function nullableString(stdClass $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function expired(mixed $value): bool
    {
        if ($value instanceof DateTimeInterface) {
            return $value <= now('UTC');
        }

        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value) <= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        return true;
    }
}
