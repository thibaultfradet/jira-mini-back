<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/projects')]
class ProjectController extends AbstractController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'projects_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $projects = $this->projectRepository->findAll();

        return $this->json(array_map(fn(Project $p) => $this->serialize($p), $projects));
    }

    #[Route('/{id}', name: 'projects_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $project = $this->projectRepository->find($id);

        if (!$project) {
            return $this->json(
                ['message' => 'Project not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Build a custom response payload without
        $data = [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'createdAt' => $project->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $project->getUpdatedAt()?->format(DATE_ATOM),
            'issues' => array_map(
                function ($issue) {
                    $assignee = $issue->getAssignee();

                    return [
                        'id' => $issue->getId(),
                        'title' => $issue->getTitle(),
                        'type' => $issue->getType(),
                        'status' => $issue->getStatus(),
                        // Safely include assignee if present
                        'assignee' => $assignee ? [
                            'id' => $assignee->getId(),
                            'firstName' => $assignee->getFirstName(),
                            'lastName' => $assignee->getLastName(),
                            'email' => $assignee->getEmail(),
                        ] : null,
                    ];
                },
                $project->getIssues()->toArray()
            ),
        ];

        return $this->json($data);
    }

    #[Route('', name: 'projects_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->json(['message' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $project = new Project();
        $project->setName($data['name']);
        $project->setDescription($data['description'] ?? '');
        $project->setCreatedAt(new \DateTimeImmutable());
        $project->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $this->json($this->serialize($project, true), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'projects_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $project = $this->projectRepository->find($id);

        if (!$project) {
            return $this->json(['message' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $project->setName($data['name']);
        }

        if (isset($data['description'])) {
            $project->setDescription($data['description']);
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json($this->serialize($project, true));
    }

    #[Route('/{id}', name: 'projects_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $project = $this->projectRepository->find($id);

        if (!$project) {
            return $this->json(['message' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function serialize(Project $project, bool $withIssues = false): array
    {
        $data = [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'createdAt' => $project->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $project->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];

        if ($withIssues) {
            $data['issues'] = [];
            foreach ($project->getIssues() as $issue) {
                // Only include root issues (epics without parent)
                if ($issue->getParent() !== null) {
                    continue;
                }
                $data['issues'][] = [
                    'id' => $issue->getId(),
                    'title' => $issue->getTitle(),
                    'type' => $issue->getType(),
                    'status' => $issue->getStatus(),
                ];
            }
        }

        return $data;
    }
}
