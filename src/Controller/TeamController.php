<?php

namespace App\Controller;

use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/teams')]
class TeamController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private TeamRepository $teamRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'teams_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(): JsonResponse
    {
        $teams = $this->teamRepository->findAll();
        return $this->success(['data' => array_map(fn(Team $t) => $this->serialize($t), $teams)]);
    }

    #[Route('/{id}', name: 'teams_show', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(int $id): JsonResponse
    {
        $team = $this->teamRepository->find($id);
        if (!$team) {
            return $this->notFound('Team not found');
        }
        return $this->success(['data' => $this->serialize($team, true)]);
    }

    #[Route('', name: 'teams_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->error('Name is required');
        }

        $team = new Team();
        $team->setName($data['name']);
        $team->setDescription($data['description'] ?? null);

        $this->entityManager->persist($team);
        $this->entityManager->flush();

        return $this->created(['data' => $this->serialize($team)]);
    }

    #[Route('/{id}', name: 'teams_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $team = $this->teamRepository->find($id);
        if (!$team) {
            return $this->notFound('Team not found');
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $team->setName($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $team->setDescription($data['description']);
        }

        $this->entityManager->flush();

        return $this->success(['data' => $this->serialize($team)]);
    }

    #[Route('/{id}', name: 'teams_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $team = $this->teamRepository->find($id);
        if (!$team) {
            return $this->notFound('Team not found');
        }

        $this->entityManager->remove($team);
        $this->entityManager->flush();

        return $this->noContent();
    }

    #[Route('/{id}/members', name: 'teams_add_member', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addMember(int $id, Request $request): JsonResponse
    {
        $team = $this->teamRepository->find($id);
        if (!$team) {
            return $this->notFound('Team not found');
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['userId'])) {
            return $this->error('userId is required');
        }

        $user = $this->userRepository->find($data['userId']);
        if (!$user) {
            return $this->notFound('User not found');
        }

        $team->addMember($user);
        $this->entityManager->flush();

        return $this->success(['data' => $this->serialize($team, true)]);
    }

    #[Route('/{id}/members/{userId}', name: 'teams_remove_member', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function removeMember(int $id, int $userId): JsonResponse
    {
        $team = $this->teamRepository->find($id);
        if (!$team) {
            return $this->notFound('Team not found');
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->notFound('User not found');
        }

        $team->removeMember($user);
        $this->entityManager->flush();

        return $this->noContent();
    }

    private function serialize(Team $team, bool $withMembers = false): array
    {
        $data = [
            'id' => $team->getId(),
            'name' => $team->getName(),
            'description' => $team->getDescription(),
            'memberCount' => $team->getMembers()->count(),
            'createdAt' => $team->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $team->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];

        if ($withMembers) {
            $data['members'] = array_map(fn($u) => [
                'id' => $u->getId(),
                'firstName' => $u->getFirstName(),
                'lastName' => $u->getLastName(),
                'email' => $u->getEmail(),
                'roles' => $u->getRoles(),
            ], $team->getMembers()->toArray());
        }

        return $data;
    }
}
