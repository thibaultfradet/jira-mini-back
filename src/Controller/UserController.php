<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users')]
class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'users_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(): JsonResponse
    {
        $users = $this->userRepository->findAll();

        return $this->json(array_map(fn(User $u) => $this->serialize($u), $users));
    }

    #[Route('/{id}', name: 'users_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $currentUser->getId() !== $user->getId()) {
            return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serialize($user));
    }

    #[Route('', name: 'users_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['firstName']) || empty($data['lastName'])) {
            return $this->json(['message' => 'Email, firstName and lastName are required'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return $this->json(['message' => 'Email already exists'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setPassword('');
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());

        $roles = ['ROLE_USER'];
        if (!empty($data['isAdmin']) && $data['isAdmin'] === true) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json($this->serialize($user), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'users_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['email'])) {
            $existing = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existing && $existing->getId() !== $user->getId()) {
                return $this->json(['message' => 'Email already exists'], Response::HTTP_CONFLICT);
            }
            $user->setEmail($data['email']);
        }

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        if (array_key_exists('isAdmin', $data)) {
            $roles = ['ROLE_USER'];
            if ($data['isAdmin'] === true) {
                $roles[] = 'ROLE_ADMIN';
            }
            $user->setRoles($roles);
        }

        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json($this->serialize($user));
    }

    #[Route('/{id}', name: 'users_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function serialize(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles()),
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
