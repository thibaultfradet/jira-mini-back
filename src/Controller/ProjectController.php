<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/projects')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ProjectController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'projects_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $projects = $this->projectRepository->findAll();
        return $this->success(['data' => array_map(fn(Project $p) => $this->serialize($p), $projects)]);
    }

    #[Route('/{id}', name: 'projects_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $project = $this->projectRepository->find($id);
        if (!$project) {
            return $this->notFound('Project not found');
        }

        return $this->success(['data' => $this->serialize($project, true)]);
    }

    #[Route('', name: 'projects_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->error('Name is required');
        }

        $project = new Project();
        $project->setName($data['name']);
        $project->setDescription($data['description'] ?? '');

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $this->created(['data' => $this->serialize($project)]);
    }

    #[Route('/{id}', name: 'projects_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $project = $this->projectRepository->find($id);
        if (!$project) {
            return $this->notFound('Project not found');
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $project->setName($data['name']);
        }
        if (isset($data['description'])) {
            $project->setDescription($data['description']);
        }

        $this->entityManager->flush();

        return $this->success(['data' => $this->serialize($project)]);
    }

    #[Route('/{id}', name: 'projects_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $project = $this->projectRepository->find($id);
        if (!$project) {
            return $this->notFound('Project not found');
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();

        return $this->noContent();
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
                if ($issue->getParent() !== null) {
                    continue;
                }
                $assignee = $issue->getAssignee();
                $data['issues'][] = [
                    'id' => $issue->getId(),
                    'title' => $issue->getTitle(),
                    'type' => $issue->getType(),
                    'status' => $issue->getStatus(),
                    'storyPoints' => $issue->getStoryPoints(),
                    'assignee' => $assignee ? [
                        'id' => $assignee->getId(),
                        'firstName' => $assignee->getFirstName(),
                        'lastName' => $assignee->getLastName(),
                        'email' => $assignee->getEmail(),
                    ] : null,
                ];
            }
        }

        return $data;
    }
}
