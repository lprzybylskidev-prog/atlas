<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Rtc;

use App\Modules\Optional\Chat\Application\Contracts\RtcGateway;
use App\Modules\Optional\Chat\Application\DTOs\RtcParticipantAccess;
use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;
use App\Modules\Optional\Chat\Application\Exceptions\RtcAccessDenied;
use App\Modules\Optional\Chat\Application\Exceptions\RtcUnavailable;
use DateTimeImmutable;
use DateTimeZone;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use Throwable;

final readonly class LiveKitRtcGateway implements RtcGateway
{
    private const int SERVICE_TOKEN_TTL_SECONDS = 60;

    private ClientInterface $httpClient;

    public function __construct(
        private string $serverUrl,
        private string $clientUrl,
        private string $apiKey,
        private string $apiSecret,
        private int $tokenTtlSeconds,
        private int $emptyRoomTimeoutSeconds,
        int $requestTimeoutSeconds,
        ?ClientInterface $httpClient = null,
    ) {
        foreach ([$serverUrl, $clientUrl, $apiKey, $apiSecret] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('LiveKit endpoint and credential configuration must be complete.');
            }
        }

        if ($tokenTtlSeconds < 30 || $tokenTtlSeconds > 900) {
            throw new InvalidArgumentException('LiveKit participant token TTL must be between 30 and 900 seconds.');
        }

        if ($emptyRoomTimeoutSeconds < 60) {
            throw new InvalidArgumentException('LiveKit empty-room timeout must be at least 60 seconds.');
        }

        if ($requestTimeoutSeconds < 1 || $requestTimeoutSeconds > 30) {
            throw new InvalidArgumentException('LiveKit request timeout must be between 1 and 30 seconds.');
        }

        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => rtrim($serverUrl, '/').'/',
            'connect_timeout' => min(2, $requestTimeoutSeconds),
            'timeout' => $requestTimeoutSeconds,
        ]);
    }

    public function prepareRoom(RtcSessionAdmission $admission): void
    {
        $this->ensureRtcCapable($admission);
        $this->callRoomService('CreateRoom', [
            'name' => $admission->roomName,
            'empty_timeout' => $this->emptyRoomTimeoutSeconds,
            'departure_timeout' => $this->emptyRoomTimeoutSeconds,
            'max_participants' => 0,
        ], [
            'roomCreate' => true,
        ]);
    }

    public function issueParticipantAccess(RtcSessionAdmission $admission): RtcParticipantAccess
    {
        $this->ensureRtcCapable($admission);

        try {
            $participantIdentity = 'user-'.$admission->userPublicId;
            $issuedAt = time();
            $expiresAt = $issuedAt + $this->tokenTtlSeconds;
            $token = $this->encodeToken([
                'sub' => $participantIdentity,
                'jti' => $participantIdentity,
                'name' => $admission->participantName,
                'video' => [
                    'roomJoin' => true,
                    'room' => $admission->roomName,
                    'canPublish' => $admission->canPublish,
                    'canSubscribe' => $admission->canSubscribe,
                    'canPublishData' => false,
                ],
            ], $issuedAt, $expiresAt);

            return new RtcParticipantAccess(
                serverUrl: $this->clientUrl,
                roomName: $admission->roomName,
                participantIdentity: $participantIdentity,
                token: $token,
                expiresAt: (new DateTimeImmutable('@'.$expiresAt))->setTimezone(new DateTimeZone('UTC')),
            );
        } catch (RtcAccessDenied $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw RtcUnavailable::infrastructure($exception);
        }
    }

    public function removeParticipant(string $roomName, string $participantIdentity): void
    {
        $this->callRoomService('RemoveParticipant', [
            'room' => $roomName,
            'identity' => $participantIdentity,
        ], [
            'roomAdmin' => true,
            'room' => $roomName,
        ]);
    }

    public function endRoom(string $roomName): void
    {
        $this->callRoomService('DeleteRoom', [
            'room' => $roomName,
        ], [
            'roomCreate' => true,
        ]);
    }

    /**
     * @param  array<string, bool|string|int>  $payload
     * @param  array<string, bool|string>  $videoGrant
     */
    private function callRoomService(string $method, array $payload, array $videoGrant): void
    {
        try {
            $issuedAt = time();
            $token = $this->encodeToken(
                ['video' => $videoGrant],
                $issuedAt,
                $issuedAt + self::SERVICE_TOKEN_TTL_SECONDS,
            );

            $this->httpClient->request('POST', 'twirp/livekit.RoomService/'.$method, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (Throwable $exception) {
            throw RtcUnavailable::infrastructure($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function encodeToken(array $claims, int $issuedAt, int $expiresAt): string
    {
        return JWT::encode([
            ...$claims,
            'iss' => $this->apiKey,
            'nbf' => $issuedAt,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ], $this->apiSecret, 'HS256');
    }

    private function ensureRtcCapable(RtcSessionAdmission $admission): void
    {
        if (! $admission->mode->supportsRtc()) {
            throw RtcAccessDenied::inPersonMeeting();
        }
    }
}
