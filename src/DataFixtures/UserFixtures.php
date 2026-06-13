<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Deterministic dataset for E2E tests (also usable in dev).
 *
 * Loaded via `doctrine:fixtures:load --env=test` before the Playwright tests.
 * The values (emails, password, reset token) are fixed on purpose so they can
 * be referenced from the front-end E2E specs.
 */
class UserFixtures extends Fixture
{
    public const PASSWORD = 'password123';

    public const ACTIVE_EMAIL = 'active@test.local';
    public const ADMIN_EMAIL = 'admin@test.local';
    public const DISABLED_EMAIL = 'disabled@test.local';
    public const RESET_EMAIL = 'reset@test.local';

    /** Known reset token, seeded valid (+1h) for the E2E reset scenario. */
    public const RESET_TOKEN = 'e2eknownresettoken00000000000000000000000000000000000000000000ff';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist($this->makeUser(self::ACTIVE_EMAIL, 'Active', 'User', ['ROLE_USER'], true));
        $manager->persist($this->makeUser(self::ADMIN_EMAIL, 'Admin', 'User', ['ROLE_ADMIN'], true));
        $manager->persist($this->makeUser(self::DISABLED_EMAIL, 'Disabled', 'User', ['ROLE_USER'], false));

        $resetUser = $this->makeUser(self::RESET_EMAIL, 'Reset', 'User', ['ROLE_USER'], true);
        $resetUser->setResetToken(self::RESET_TOKEN);
        $resetUser->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $manager->persist($resetUser);

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function makeUser(string $email, string $firstName, string $lastName, array $roles, bool $isActive): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles($roles);
        $user->setIsActive($isActive);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));

        return $user;
    }
}
