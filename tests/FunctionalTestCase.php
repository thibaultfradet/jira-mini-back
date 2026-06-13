<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for functional tests.
 *
 * Thanks to DAMADoctrineTestBundle, each test runs inside a transaction that is
 * rolled back at the end: data created via createUser() needs no cleanup and
 * does not leak between tests.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    protected UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Start each test from a clean slate even if app_test still holds the
        // E2E fixtures (loaded committed by `doctrine:fixtures:load`). The purge
        // runs inside the DAMA transaction, so it is rolled back, not destructive.
        (new ORMPurger($this->em))->purge();
    }

    /**
     * Creates and persists a minimal user for the current test.
     *
     * @param list<string> $roles
     */
    protected function createUser(
        string $email,
        string $password = 'password123',
        bool $active = true,
        array $roles = ['ROLE_USER'],
        ?string $resetToken = null,
        ?\DateTimeImmutable $resetTokenExpiresAt = null,
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRoles($roles);
        $user->setIsActive($active);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        if ($resetToken !== null) {
            $user->setResetToken($resetToken);
            $user->setResetTokenExpiresAt($resetTokenExpiresAt);
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Sends a JSON request (encoded body + Content-Type header).
     */
    protected function jsonRequest(string $method, string $uri, array $payload = []): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload),
        );
    }

    /**
     * Decodes the JSON body of the last response.
     */
    protected function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }
}
