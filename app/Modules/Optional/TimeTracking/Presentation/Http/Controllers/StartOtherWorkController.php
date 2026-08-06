<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Contracts\OtherWorkCategoryStore;
use App\Modules\Optional\TimeTracking\Application\OtherWorkSessionCoordinator;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

final readonly class StartOtherWorkController
{
    public function __construct(
        private OtherWorkSessionCoordinator $otherWork,
        private OtherWorkCategoryStore $categories,
    ) {}

    public function create(Request $request): Response
    {
        $teamId = $this->activeTeamId($request);

        return Inertia::render('TimeTracking/StartOtherWork', [
            'categories' => $teamId === null ? [] : $this->categoryOptions($teamId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $values = $request->validate([
            'category_key' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
        ], [], [
            'category_key' => __('validation.attributes.time_tracking_other_work_category'),
            'description' => __('validation.attributes.time_tracking_other_work_description'),
        ]);
        $categoryKey = data_get($values, 'category_key');
        $description = data_get($values, 'description');
        $userId = $this->userId($request);

        if ($userId === null) {
            abort(403);
        }

        try {
            $this->otherWork->start(
                $userId,
                $this->nullableString($categoryKey),
                $this->stringValue($description),
                new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')),
            );
        } catch (InvalidArgumentException|RuntimeException) {
            return back()->with('flash.messages', [FlashMessage::error('flash.time_tracking.other_work_start_failed')]);
        }

        return redirect()->route(TimeTrackingPermissionCatalog::OTHER_WORK_SHOW);
    }

    /**
     * @return list<array{value: string, label: string, description: string|null, requiresComment: bool, autoApprovalEnabled: bool}>
     */
    private function categoryOptions(int $teamId): array
    {
        $locale = app()->getLocale();
        $options = [];

        foreach ($this->categories->activeForTeam($teamId) as $category) {
            $options[] = [
                'value' => $category->categoryKey,
                'label' => $locale === 'pl' ? $category->labelPl : $category->labelEn,
                'description' => $locale === 'pl' ? $category->descriptionPl : $category->descriptionEn,
                'requiresComment' => $category->requiresComment,
                'autoApprovalEnabled' => $category->autoApprovalEnabled,
            ];
        }

        return $options;
    }

    private function activeTeamId(Request $request): ?int
    {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($teamPublicId) || $teamPublicId === '') {
            return null;
        }

        $id = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function userId(Request $request): ?int
    {
        $id = data_get($request->user(), 'id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
