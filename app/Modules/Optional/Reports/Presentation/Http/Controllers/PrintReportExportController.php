<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Presentation\Http\Controllers;

use App\Modules\Optional\Reports\Application\Exceptions\ReportRenderCredentialInvalid;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportPrintViewAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PrintReportExportController
{
    public function __construct(private ReportPrintViewAccess $prints) {}

    public function __invoke(Request $request, string $export): Response
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->session()->get('active_team_public_id');

        if (! is_string($userPublicId)) {
            abort(403);
        }

        try {
            $html = $this->prints->html(
                exportRequestPublicId: $export,
                actorPublicId: $userPublicId,
                activeTeamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            );
        } catch (ReportRenderCredentialInvalid) {
            abort(404);
        }

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
