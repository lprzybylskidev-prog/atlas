<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;
use App\Modules\Optional\Chat\Domain\Rtc\RtcSessionMode;
use App\Modules\Optional\Chat\Infrastructure\Rtc\LiveKitRtcGateway;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tests\TestCase;

final class LiveKitRtcInfrastructureTest extends TestCase
{
    public function test_pinned_livekit_runtime_creates_room_and_issues_accepted_room_scoped_token(): void
    {
        $this->requireRtcRuntime();

        $roomName = 'atlas-test-'.bin2hex(random_bytes(8));
        $gateway = $this->gateway();
        $admission = new RtcSessionAdmission(
            sessionPublicId: $roomName,
            roomName: $roomName,
            mode: RtcSessionMode::OnlineMeeting,
            userPublicId: 'integration-user',
            participantName: 'RTC integration user',
        );

        try {
            $gateway->prepareRoom($admission);
            $access = $gateway->issueParticipantAccess($admission);
            $claims = JWT::decode($access->token, new Key($this->requiredEnv('LIVEKIT_API_SECRET'), 'HS256'));

            self::assertSame($roomName, $access->roomName);
            $video = $claims->video;
            self::assertIsObject($video);
            $videoGrant = get_object_vars($video);
            self::assertSame($roomName, $videoGrant['room']);
            self::assertTrue($videoGrant['roomJoin']);
        } finally {
            $gateway->endRoom($roomName);
        }
    }

    public function test_pinned_egress_runtime_exposes_private_health_endpoint(): void
    {
        $this->requireRtcRuntime();

        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $this->requiredEnv('ATLAS_TEST_EGRESS_HOST'), (int) $this->requiredEnv('ATLAS_TEST_EGRESS_PORT')),
            $errorCode,
            $errorMessage,
            2.0,
        );

        self::assertIsResource($socket);
        fclose($socket);
    }

    private function gateway(): LiveKitRtcGateway
    {
        return new LiveKitRtcGateway(
            serverUrl: $this->requiredEnv('LIVEKIT_URL'),
            clientUrl: 'ws://localhost:7880',
            apiKey: $this->requiredEnv('LIVEKIT_API_KEY'),
            apiSecret: $this->requiredEnv('LIVEKIT_API_SECRET'),
            tokenTtlSeconds: 300,
            emptyRoomTimeoutSeconds: 900,
            requestTimeoutSeconds: 5,
        );
    }

    private function requireRtcRuntime(): void
    {
        if (getenv('ATLAS_TEST_LIVEKIT') !== '1') {
            self::markTestSkipped('Run composer test:rtc to start and verify the pinned LiveKit/Egress runtime.');
        }
    }

    private function requiredEnv(string $name): string
    {
        $value = getenv($name);
        self::assertIsString($value, sprintf('%s must be configured for RTC integration tests.', $name));
        self::assertNotSame('', $value, sprintf('%s must be configured for RTC integration tests.', $name));

        return $value;
    }
}
