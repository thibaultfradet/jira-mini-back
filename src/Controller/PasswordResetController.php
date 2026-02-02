<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/password')]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/forgot', name: 'password_forgot', methods: ['POST'])]
    public function forgot(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['message' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        // Always return success to prevent email enumeration
        if (!$user) {
            return new JsonResponse(['message' => 'If the email exists, a reset link has been sent']);
        }

        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $user->setResetToken($token);
        $user->setResetTokenExpiresAt($expiresAt);
        $this->entityManager->flush();

        // Send the reset email
        $resetUrl = $this->getParameter('app.frontend_url') . '/reset-password?token=' . $token;

        $emailMessage = (new Email())
            ->from($this->getParameter('app.mailer_from'))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->html(sprintf(
                '<p>Bonjour %s,</p>
                <p>Vous avez demandé à réinitialiser votre mot de passe.</p>
                <p><a href="%s">Cliquez ici pour réinitialiser votre mot de passe</a></p>
                <p>Ce lien expire dans 1 heure.</p>
                <p>Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email.</p>',
                $user->getFirstName(),
                $resetUrl
            ));

        $this->mailer->send($emailMessage);

        return new JsonResponse(['message' => 'If the email exists, a reset link has been sent']);
    }

    #[Route('/reset', name: 'password_reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        $password = $data['password'] ?? null;

        if (!$token || !$password) {
            return new JsonResponse(
                ['message' => 'Token and password are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (strlen($password) < 8) {
            return new JsonResponse(
                ['message' => 'Password must be at least 8 characters'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $this->userRepository->findOneBy(['resetToken' => $token]);

        if (!$user || !$user->isResetTokenValid()) {
            return new JsonResponse(
                ['message' => 'Invalid or expired token'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Hash and set the new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Clear the reset token
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Password has been reset successfully']);
    }
}
