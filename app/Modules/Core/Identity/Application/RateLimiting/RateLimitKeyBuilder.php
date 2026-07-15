<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\RateLimiting;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class RateLimitKeyBuilder
{
    public function build(Request $request, RateLimitPolicy $policy): string
    {
        $parts = array_map(
            fn (RateLimitKeyPart $part): string => $this->resolvePart($request, $part),
            $policy->keyParts,
        );

        return Str::transliterate($policy->name.'|'.implode('|', $parts));
    }

    private function resolvePart(Request $request, RateLimitKeyPart $part): string
    {
        return match ($part) {
            RateLimitKeyPart::Ip => 'ip:'.($request->ip() ?? 'unknown'),
            RateLimitKeyPart::User => 'user:'.$this->userIdentifier($request),
            RateLimitKeyPart::Team => 'team:'.$this->teamIdentifier($request),
            RateLimitKeyPart::ApiClient => 'api-client:'.$this->apiClientIdentifier($request),
        };
    }

    private function userIdentifier(Request $request): string
    {
        $authenticatedUser = $request->user();

        if ($authenticatedUser !== null) {
            $identifier = $authenticatedUser->getAuthIdentifier();

            return is_scalar($identifier) ? (string) $identifier : 'authenticated';
        }

        $email = $request->string('email')->lower()->trim()->toString();

        return $email !== '' ? $email : 'guest';
    }

    private function teamIdentifier(Request $request): string
    {
        $teamId = $request->hasSession()
            ? $request->session()->get('active_team_id')
            : null;

        $teamId ??= $request->header('X-Atlas-Team');

        return is_scalar($teamId) && (string) $teamId !== '' ? (string) $teamId : 'none';
    }

    private function apiClientIdentifier(Request $request): string
    {
        $client = $request->header('X-Atlas-Api-Client');

        return is_string($client) && trim($client) !== '' ? trim($client) : 'none';
    }
}
