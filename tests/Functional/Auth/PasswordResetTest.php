<?php

namespace App\Tests\Functional\Auth;

use App\Entity\User;
use App\Tests\FunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetTest extends FunctionalTestCase
{
    use MailerAssertionsTrait;

    public function testForgotKnownEmailGeneratesTokenAndQueuesEmail(): void
    {
        $this->createUser('user@test.local');

        $this->jsonRequest('POST', '/password/forgot', ['email' => 'user@test.local']);

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@test.local']);
        $this->assertNotNull($user->getResetToken());
        $this->assertNotNull($user->getResetTokenExpiresAt());
        $this->assertTrue($user->isResetTokenValid());
    }

    public function testForgotUnknownEmailIsEnumerationSafe(): void
    {
        $this->jsonRequest('POST', '/password/forgot', ['email' => 'ghost@test.local']);

        // Same generic response as a known email, and no email sent.
        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(0);
    }

    public function testForgotWithoutEmailIsRejected(): void
    {
        $this->jsonRequest('POST', '/password/forgot', []);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testResetWithValidTokenChangesPassword(): void
    {
        $token = 'valid-reset-token-1234567890';
        $user = $this->createUser(
            'user@test.local',
            resetToken: $token,
            resetTokenExpiresAt: new \DateTimeImmutable('+1 hour'),
        );

        $this->jsonRequest('POST', '/password/reset', [
            'token' => $token,
            'password' => 'brandnewpassword',
        ]);

        $this->assertResponseIsSuccessful();

        $this->em->refresh($user);
        $this->assertNull($user->getResetToken());
        $this->assertTrue($this->passwordHasher->isPasswordValid($user, 'brandnewpassword'));
    }

    public function testResetWithExpiredTokenIsRejected(): void
    {
        $token = 'expired-reset-token-1234567890';
        $this->createUser(
            'user@test.local',
            resetToken: $token,
            resetTokenExpiresAt: new \DateTimeImmutable('-1 hour'),
        );

        $this->jsonRequest('POST', '/password/reset', [
            'token' => $token,
            'password' => 'brandnewpassword',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('Invalid or expired token', $this->responseData()['message'] ?? null);
    }

    public function testResetWithUnknownTokenIsRejected(): void
    {
        $this->jsonRequest('POST', '/password/reset', [
            'token' => 'does-not-exist',
            'password' => 'brandnewpassword',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testResetWithShortPasswordIsRejected(): void
    {
        $token = 'valid-reset-token-1234567890';
        $this->createUser(
            'user@test.local',
            resetToken: $token,
            resetTokenExpiresAt: new \DateTimeImmutable('+1 hour'),
        );

        $this->jsonRequest('POST', '/password/reset', [
            'token' => $token,
            'password' => 'short',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('Password must be at least 8 characters', $this->responseData()['message'] ?? null);
    }

    public function testResetWithMissingFieldsIsRejected(): void
    {
        $this->jsonRequest('POST', '/password/reset', ['token' => 'whatever']);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
