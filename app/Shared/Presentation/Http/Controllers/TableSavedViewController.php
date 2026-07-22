<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers;

use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class TableSavedViewController
{
    public function __construct(
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayload($request, true);
        [$userId, $teamId, $actorPublicId] = $this->context->userTeam($request);

        $publicId = $this->views->create(
            tableKey: self::stringValue($validated['table_key'] ?? ''),
            name: self::stringValue($validated['name'] ?? ''),
            type: self::stringValue($validated['type'] ?? 'private'),
            state: self::arrayValue($validated['state'] ?? []),
            userId: $userId,
            teamId: $teamId,
            actorPublicId: $actorPublicId,
        );

        return back()
            ->with('flash.messages', [
                FlashMessage::success('flash.table_views.saved'),
            ])
            ->with('table_view_public_id', $publicId);
    }

    public function update(Request $request, string $view): RedirectResponse
    {
        $validated = $this->validatedPayload($request, false);
        [$userId, $teamId, $actorPublicId] = $this->context->userTeam($request);

        $this->views->update(
            publicId: $view,
            name: self::stringValue($validated['name'] ?? ''),
            state: self::arrayValue($validated['state'] ?? []),
            userId: $userId,
            teamId: $teamId,
            actorPublicId: $actorPublicId,
        );

        return back()->with('flash.messages', [
            FlashMessage::success('flash.table_views.updated'),
        ]);
    }

    public function destroy(Request $request, string $view): RedirectResponse
    {
        [$userId, $teamId, $actorPublicId] = $this->context->userTeam($request);

        $this->views->delete($view, $userId, $teamId, $actorPublicId);

        return back()->with('flash.messages', [
            FlashMessage::success('flash.table_views.deleted'),
        ]);
    }

    public function copy(Request $request, string $view): RedirectResponse
    {
        $validated = self::arrayValue($request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', 'in:private,team'],
        ]));
        [$userId, $teamId, $actorPublicId] = $this->context->userTeam($request);
        $publicId = $this->views->copy(
            publicId: $view,
            name: self::stringValue($validated['name'] ?? ''),
            type: self::stringValue($validated['type'] ?? 'private'),
            userId: $userId,
            teamId: $teamId,
            actorPublicId: $actorPublicId,
        );

        return back()
            ->with('flash.messages', [
                FlashMessage::success('flash.table_views.copied'),
            ])
            ->with('table_view_public_id', $publicId);
    }

    public function default(Request $request, string $view): RedirectResponse
    {
        [$userId, $teamId] = $this->context->userTeam($request);

        $this->views->setDefault($view, $userId, $teamId);

        return back()->with('flash.messages', [
            FlashMessage::success('flash.table_views.default_updated'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $requiresTableKey): array
    {
        $validated = $request->validate([
            'table_key' => [$requiresTableKey ? 'required' : 'sometimes', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:80'],
            'type' => [$requiresTableKey ? 'required' : 'sometimes', 'string', 'in:private,team'],
            'state' => ['required', 'array'],
            'state.sort' => ['nullable', 'string', 'max:80'],
            'state.direction' => ['nullable', 'string', 'in:asc,desc'],
            'state.search' => ['nullable', 'string', 'max:120'],
            'state.columns' => ['nullable', 'array'],
            'state.columns.*' => ['string', 'max:80'],
            'state.columnOrder' => ['nullable', 'array'],
            'state.columnOrder.*' => ['string', 'max:80'],
            'state.filters' => ['nullable', 'array'],
            'state.filters.*' => ['nullable'],
        ]);

        return self::arrayValue($validated);
    }

    private static function stringValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function arrayValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
