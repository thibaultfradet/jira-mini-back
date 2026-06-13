<?php

namespace App\Tests\Functional\Auth;

use App\Tests\FunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

class LoginTest extends FunctionalTestCase
{
    public function testLoginSuccessReturnsTokens(): void
    {
        $this->createUser('active@test.local');

        $this->jsonRequest('POST', '/auth', [
            'email' => 'active@test.local',
            'password' => 'password123',
        ]);

        $this->assertResponseIsSuccessful();
        $data = $this->responseData();
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testLoginTokenPayloadIsEnriched(): void
    {
        $this->createUser('active@test.local', roles: ['ROLE_USER']);

        $this->jsonRequest('POST', '/auth', [
            'email' => 'active@test.local',
            'password' => 'password123',
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $this->decodeJwtPayload($this->responseData()['token']);

        // JWTCreatedListener must enrich the payload (see CLAUDE.md).
        $this->assertArrayHasKey('id', $payload);
        $this->assertSame('Test', $payload['firstName']);
        $this->assertSame('User', $payload['lastName']);
        $this->assertSame('active@test.local', $payload['email'] ?? $payload['username'] ?? null);
    }

    public function testLoginWithWrongPasswordIsRejected(): void
    {
        $this->createUser('active@test.local');

        $this->jsonRequest('POST', '/auth', [
            'email' => 'active@test.local',
            'password' => 'wrong-password',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertArrayHasKey('message', $this->responseData());
    }

    public function testLoginWithUnknownEmailIsRejected(): void
    {
        $this->jsonRequest('POST', '/auth', [
            'email' => 'ghost@test.local',
            'password' => 'password123',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginWithDisabledAccountIsBlocked(): void
    {
        // Correct password but disabled account → blocked by the UserChecker.
        $this->createUser('disabled@test.local', active: false);

        $this->jsonRequest('POST', '/auth', [
            'email' => 'disabled@test.local',
            'password' => 'password123',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertSame('Ce compte est désactivé', $this->responseData()['message'] ?? null);
    }

    public function testLoginWithMissingFieldsIsRejected(): void
    {
        $this->jsonRequest('POST', '/auth', ['email' => 'active@test.local']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertSame('Email and password are required', $this->responseData()['message'] ?? null);
    }

    private function decodeJwtPayload(string $jwt): array
    {
        $segments = explode('.', $jwt);
        $payload = strtr($segments[1], '-_', '+/');

        return json_decode(base64_decode($payload), true) ?? [];
    }
}
