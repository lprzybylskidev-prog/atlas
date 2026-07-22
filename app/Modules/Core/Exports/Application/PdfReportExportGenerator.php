<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\ReportExportGenerator;
use App\Modules\Core\Exports\Application\Contracts\ReportPdfRenderer;
use App\Modules\Core\Exports\Application\DTOs\GeneratedReportArtifact;
use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportRenderCredentialAccess;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportRenderCredentialIssuer;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use Illuminate\Support\Str;

final readonly class PdfReportExportGenerator implements ReportExportGenerator
{
    public function __construct(
        private ReportRenderCredentialIssuer $issuer,
        private ReportRenderCredentialAccess $credentials,
        private ReportHtmlDocumentFactory $html,
        private ReportPdfRenderer $renderer,
        private ReportRenderReadinessRegistry $readiness,
    ) {}

    public function supports(ReportExportFormat $format): bool
    {
        return $format === ReportExportFormat::Pdf;
    }

    public function generate(ReportExportGenerationRequest $request): GeneratedReportArtifact
    {
        $this->readiness->assertReady($request);

        $issued = $this->issuer->issue($request->publicId);
        $resolved = $this->credentials->resolve($issued->token);
        $contents = $this->renderer->render($this->html->tableReport($resolved->request));
        $this->credentials->consume($resolved->publicId);

        return new GeneratedReportArtifact(
            filename: sprintf('%s-%s.pdf', Str::slug($request->reportName), now('UTC')->format('Ymd-His')),
            contentType: 'application/pdf',
            contents: $contents,
        );
    }
}
