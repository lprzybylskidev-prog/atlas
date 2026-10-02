<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;
use App\Modules\Optional\Chat\Application\Exceptions\RtcAccessDenied;
use App\Modules\Optional\Chat\Application\Exceptions\RtcUnavailable;
use App\Modules\Optional\Chat\Domain\Rtc\RtcSessionMode;
use App\Modules\Optional\Chat\Infrastructure\Rtc\LiveKitRtcGateway;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class LiveKitRtcGatewayTest extends TestCase
{
    public function test_participant_token_is_room_scoped_short_lived_and_does_not_grant_data_publish(): void
    {
        $gateway = $this->gateway();
        $access = $gateway->issueParticipantAccess($this->admission(RtcSessionMode::OnlineMeeting));
        $claims = JWT::decode($access->token, new Key('test-secret-at-least-32-characters-long', 'HS256'));

        self::assertSame('ws://livekit.example.test', $access->serverUrl);
        self::assertSame('user-user-1', $access->participantIdentity);
        $video = $claims->video;
        $expiresAt = $claims->exp;
        $issuedAt = $claims->iat;
        self::assertIsObject($video);
        self::assertIsInt($expiresAt);
        self::assertIsInt($issuedAt);
        $videoGrant = get_object_vars($video);
        self::assertSame('atlas-meeting-meeting-1', $videoGrant['room']);
        self::assertTrue($videoGrant['roomJoin']);
        self::assertTrue($videoGrant['canPublish']);
        self::assertTrue($videoGrant['canSubscribe']);
        self::assertFalse($videoGrant['canPublishData']);
        self::assertLessThanOrEqual(300, $expiresAt - $issuedAt);
        self::assertGreaterThanOrEqual(299, $expiresAt - $issuedAt);
    }

    public function test_gateway_defensively_rejects_in_person_meeting_tokens(): void
    {
        $this->expectException(RtcAccessDenied::class);

        $this->gateway()->issueParticipantAccess($this->admission(RtcSessionMode::InPersonMeeting));
    }

    public function test_room_creation_uses_narrow_room_service_grant_and_expected_payload(): void
    {
        $handler = new MockHandler([new Response(200, [], '{}')]);
        $gateway = $this->gateway(new Client([
            'base_uri' => 'http://livekit.example.test/',
            'handler' => HandlerStack::create($handler),
        ]));

        $gateway->prepareRoom($this->admission(RtcSessionMode::OnlineMeeting));

        $request = $handler->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('/twirp/livekit.RoomService/CreateRoom', $request->getUri()->getPath());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));

        $authorization = $request->getHeaderLine('Authorization');
        self::assertStringStartsWith('Bearer ', $authorization);
        $claims = JWT::decode(substr($authorization, 7), new Key('test-secret-at-least-32-characters-long', 'HS256'));
        $video = $claims->video;
        $expiresAt = $claims->exp;
        $issuedAt = $claims->iat;
        self::assertIsObject($video);
        self::assertIsInt($expiresAt);
        self::assertIsInt($issuedAt);
        $videoGrant = get_object_vars($video);
        self::assertTrue($videoGrant['roomCreate']);
        self::assertArrayNotHasKey('roomAdmin', $videoGrant);
        self::assertLessThanOrEqual(60, $expiresAt - $issuedAt);

        $payload = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([
            'name' => 'atlas-meeting-meeting-1',
            'empty_timeout' => 900,
            'departure_timeout' => 900,
            'max_participants' => 0,
        ], $payload);
    }

    public function test_room_service_failure_is_mapped_to_rtc_unavailable(): void
    {
        $handler = new MockHandler([new Response(503, [], '{"error":"unavailable"}')]);
        $gateway = $this->gateway(new Client([
            'base_uri' => 'http://livekit.example.test/',
            'handler' => HandlerStack::create($handler),
        ]));

        $this->expectException(RtcUnavailable::class);
        $this->expectExceptionMessage('RTC media infrastructure is currently unavailable.');

        $gateway->prepareRoom($this->admission(RtcSessionMode::OnlineMeeting));
    }

    public function test_room_composite_recording_uses_atlas_template_and_private_segment_path(): void
    {
        $handler = new MockHandler([new Response(200, [], '{"egress_id":"EG_test"}')]);
        $gateway = $this->gateway(new Client([
            'base_uri' => 'http://livekit.example.test/',
            'handler' => HandlerStack::create($handler),
        ]));

        $started = $gateway->startRoomCompositeRecording('atlas-meeting-1', '01TESTRECORDING00000000000', 2);

        self::assertSame('EG_test', $started->egressId);
        self::assertSame('meeting-recordings/01testrecording00000000000/segment-002.mp4', $started->stagingPath);
        $request = $handler->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('/twirp/livekit.Egress/StartRoomCompositeEgress', $request->getUri()->getPath());
        $payload = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('custom', $payload['layout']);
        self::assertSame('http://app/rtc/recording-template', $payload['custom_base_url']);
        $fileOutputs = $payload['file_outputs'] ?? null;
        self::assertIsArray($fileOutputs);
        $firstOutput = $fileOutputs[0] ?? null;
        self::assertIsArray($firstOutput);
        self::assertSame('/out/meeting-recordings/01testrecording00000000000/segment-002.mp4', $firstOutput['filepath']);
        $claims = JWT::decode(substr($request->getHeaderLine('Authorization'), 7), new Key('test-secret-at-least-32-characters-long', 'HS256'));
        $video = $claims->video;
        self::assertIsObject($video);
        self::assertTrue(get_object_vars($video)['roomRecord']);
    }

    private function gateway(?Client $httpClient = null): LiveKitRtcGateway
    {
        return new LiveKitRtcGateway(
            serverUrl: 'http://livekit.example.test',
            clientUrl: 'ws://livekit.example.test',
            apiKey: 'test-key',
            apiSecret: 'test-secret-at-least-32-characters-long',
            tokenTtlSeconds: 300,
            emptyRoomTimeoutSeconds: 900,
            requestTimeoutSeconds: 5,
            httpClient: $httpClient,
        );
    }

    private function admission(RtcSessionMode $mode): RtcSessionAdmission
    {
        return new RtcSessionAdmission(
            sessionPublicId: 'meeting-1',
            roomName: 'atlas-meeting-meeting-1',
            mode: $mode,
            userPublicId: 'user-1',
            participantName: 'Atlas User',
        );
    }
}
