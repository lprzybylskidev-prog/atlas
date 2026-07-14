<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Http\Middleware\AttachRequestId;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_request_id_header_is_added_to_http_responses(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $requestId = $response->headers->get(AttachRequestId::HEADER);

        self::assertIsString($requestId);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_.:-]{8,128}$/', $requestId);
    }

    public function test_incoming_request_id_is_preserved_when_valid(): void
    {
        $response = $this->withHeader(AttachRequestId::HEADER, 'atlas-test-request-1')->get('/');

        $response
            ->assertOk()
            ->assertHeader(AttachRequestId::HEADER, 'atlas-test-request-1');
    }
}
