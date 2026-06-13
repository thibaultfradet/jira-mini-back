<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\JWTCreatedListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

class JWTCreatedListenerTest extends TestCase
{
    public function testPayloadIsEnrichedForAppUser(): void
    {
        $user = new User();
        $user->setEmail('john@test.local');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $this->setId($user, 42);

        $event = new JWTCreatedEvent(['roles' => ['ROLE_USER']], $user);

        (new JWTCreatedListener())->onJWTCreated($event);

        $payload = $event->getData();
        $this->assertSame(42, $payload['id']);
        $this->assertSame('John', $payload['firstName']);
        $this->assertSame('Doe', $payload['lastName']);
        // Pre-existing data is not overwritten.
        $this->assertSame(['ROLE_USER'], $payload['roles']);
    }

    public function testPayloadUntouchedForNonAppUser(): void
    {
        $event = new JWTCreatedEvent(['roles' => ['ROLE_USER']], new InMemoryUser('foo', 'bar'));

        (new JWTCreatedListener())->onJWTCreated($event);

        $this->assertSame(['roles' => ['ROLE_USER']], $event->getData());
    }

    private function setId(User $user, int $id): void
    {
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);
    }
}
