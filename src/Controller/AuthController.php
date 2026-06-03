<?php

namespace App\Controller;

use App\Service\RefreshTokenService;
use App\Trait\ApiResponseTrait;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
class AuthController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private RefreshTokenService $refreshTokenService,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $tokenStr = $data['refresh_token'] ?? '';

        if (!$tokenStr) {
            return $this->error('Token de rafraîchissement manquant', 400);
        }

        $refreshToken = $this->refreshTokenService->findValid($tokenStr);
        if (!$refreshToken) {
            return $this->error('Token de rafraîchissement invalide ou expiré', 401);
        }

        $user = $refreshToken->getUser();

        $this->refreshTokenService->revoke($refreshToken);
        $newRefreshToken = $this->refreshTokenService->create($user);

        $jwt = $this->jwtManager->create($user);

        return $this->success([
            'token' => $jwt,
            'refresh_token' => $newRefreshToken->getToken(),
        ]);
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $tokenStr = $data['refresh_token'] ?? '';

        if ($tokenStr) {
            $refreshToken = $this->refreshTokenService->findByToken($tokenStr);
            if ($refreshToken) {
                $this->refreshTokenService->revoke($refreshToken);
            }
        }

        return $this->success(null);
    }
}
