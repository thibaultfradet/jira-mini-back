<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

class RefreshTokenService
{
    private const TTL_DAYS = 30;

    public function __construct(
        private EntityManagerInterface $em,
        private RefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    public function create(User $user): RefreshToken
    {
        $token = new RefreshToken();
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setUser($user);
        $token->setExpiresAt(new \DateTimeImmutable(sprintf('+%d days', self::TTL_DAYS)));

        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    public function findValid(string $tokenStr): ?RefreshToken
    {
        return $this->refreshTokenRepository->findValidByToken($tokenStr);
    }

    public function findByToken(string $tokenStr): ?RefreshToken
    {
        return $this->refreshTokenRepository->findOneBy(['token' => $tokenStr]);
    }

    public function revoke(RefreshToken $token): void
    {
        $this->em->remove($token);
        $this->em->flush();
    }
}
