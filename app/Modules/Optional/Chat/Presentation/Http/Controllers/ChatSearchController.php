<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\ChatSearch;
use App\Modules\Optional\Search\Application\Public\Exceptions\SearchUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ChatSearchController
{
    public function __construct(private ChatSearch $search) {}

    public function __invoke(Request $request): JsonResponse
    {
        $values = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'author' => ['nullable', 'string', 'max:200'],
            'conversation' => ['nullable', 'string', 'size:26'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'type' => ['nullable', 'in:user,conversation,message,file,link,transcript'],
        ]);
        $actor = data_get($request->user(), 'public_id');
        $team = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        if (! is_array($values)) {
            abort(422);
        }
        $term = $values['q'] ?? null;
        if (! is_string($actor) || ! is_string($team) || ! is_string($term)) {
            abort(403);
        }

        $filters = array_filter([
            'author' => $this->optional($values, 'author'),
            'conversation' => $this->optional($values, 'conversation'),
            'date_from' => $this->optional($values, 'date_from'),
            'date_to' => $this->optional($values, 'date_to'),
            'type' => $this->optional($values, 'type'),
        ], static fn (?string $value): bool => $value !== null);

        try {
            return response()->json($this->search->query($actor, $team, $term, $filters));
        } catch (SearchUnavailable) {
            return response()->json(['message' => __('chat.search.unavailable')], 503);
        }
    }

    /** @param array<mixed> $values */
    private function optional(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
