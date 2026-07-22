<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Presentation\Http\Controllers;

use App\Modules\Optional\Reports\Application\Exceptions\ReportArtifactNotDownloadable;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportArtifactAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadReportArtifactController
{
    public function __construct(private ReportExportArtifactAccess $artifacts) {}

    public function __invoke(Request $request, string $artifact): StreamedResponse
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->session()->get('active_team_public_id');

        if (! is_string($userPublicId)) {
            abort(403);
        }

        try {
            $download = $this->artifacts->download(
                artifactPublicId: $artifact,
                actorPublicId: $userPublicId,
                activeTeamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            );
        } catch (ReportArtifactNotDownloadable) {
            abort(404);
        }

        return Storage::disk($download->disk)->download(
            $download->path,
            $download->filename,
            ['Content-Type' => $download->contentType],
        );
    }
}
