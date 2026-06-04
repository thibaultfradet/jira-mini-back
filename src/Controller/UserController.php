<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'users_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $users = $this->userRepository->findAll();
        return $this->success(['data' => array_map(fn(User $u) => $this->serialize($u), $users)]);
    }

    #[Route('/me', name: 'users_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->success(['data' => $this->serialize($this->getUser())]);
    }

    #[Route('/{id}', name: 'users_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $currentUser->getId() !== $user->getId()) {
            return $this->forbidden();
        }

        return $this->success(['data' => $this->serialize($user)]);
    }

    #[Route('', name: 'users_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['firstName']) || empty($data['lastName'])) {
            return $this->error('Email, firstName and lastName are required');
        }

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return $this->error('Email already exists', 409);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setPassword('');

        $roles = ['ROLE_USER'];
        if (!empty($data['isAdmin']) && $data['isAdmin'] === true) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->created(['data' => $this->serialize($user)]);
    }

    #[Route('/{id}', name: 'users_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isSelf = $currentUser->getId() === $user->getId();

        if (!$isAdmin && !$isSelf) {
            return $this->forbidden();
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['email']) && $isAdmin) {
            $existing = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existing && $existing->getId() !== $user->getId()) {
                return $this->error('Email already exists', 409);
            }
            $user->setEmail($data['email']);
        }
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (array_key_exists('isAdmin', $data) && $isAdmin) {
            $roles = ['ROLE_USER'];
            if ($data['isAdmin'] === true) {
                $roles[] = 'ROLE_ADMIN';
            }
            $user->setRoles($roles);
        }
        if (array_key_exists('isActive', $data) && $isAdmin) {
            $user->setIsActive((bool) $data['isActive']);
        }
        if (isset($data['password']) && $isSelf && !empty($data['password'])) {
            $hashed = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashed);
        }

        $this->entityManager->flush();

        return $this->success(['data' => $this->serialize($user)]);
    }

    #[Route('/{id}', name: 'users_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->noContent();
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
            'isActive' => $user->isActive(),
            'teams' => array_map(fn($t) => ['id' => $t->getId(), 'name' => $t->getName()], $user->getTeams()->toArray()),
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
