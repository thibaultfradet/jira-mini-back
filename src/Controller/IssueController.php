<?php

namespace App\Controller;

use App\Entity\Issue;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/issues')]
class IssueController extends AbstractController
{
    public function __construct(
        private IssueRepository $issueRepository,
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/{id}', name: 'issues_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $issue = $this->issueRepository->find($id);

        if (!$issue) {
            return $this->json(['message' => 'Issue not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($issue));
    }

    #[Route('/{id}/children', name: 'issues_children', methods: ['GET'])]
    public function getChildren(int $id): JsonResponse
    {
        $issue = $this->issueRepository->find($id);

        if (!$issue) {
            return $this->json(['message' => 'Issue not found'], Response::HTTP_NOT_FOUND);
        }

        $children = [];
        foreach ($issue->getIssues() as $child) {
            $children[] = [
                'id' => $child->getId(),
                'title' => $child->getTitle(),
                'type' => $child->getType(),
                'status' => $child->getStatus(),
                'storyPoints' => $child->getStoryPoints(),
                'assignee' => $child->getAssignee() ? [
                    'id' => $child->getAssignee()->getId(),
                    'email' => $child->getAssignee()->getEmail(),
                ] : null,
            ];
        }

        return $this->json($children);
    }

    #[Route('', name: 'issues_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['title'])) {
            return $this->json(['message' => 'Title is required'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['type'])) {
            return $this->json(['message' => 'Type is required'], Response::HTTP_BAD_REQUEST);
        }

        $issue = new Issue();
        $issue->setTitle($data['title']);
        $issue->setType($data['type']);
        $issue->setDescription($data['description'] ?? '');
        $issue->setStatus('todo');
        $issue->setCreatedAt(new \DateTimeImmutable());
        $issue->setUpdatedAt(new \DateTimeImmutable());

        // Set reporter as current user
        $issue->setReporter($this->getUser());

        if ($data['type'] === 'epic') {
            // Epic requires projectId
            if (empty($data['projectId'])) {
                return $this->json(['message' => 'projectId is required for epic'], Response::HTTP_BAD_REQUEST);
            }

            $project = $this->projectRepository->find($data['projectId']);
            if (!$project) {
                return $this->json(['message' => 'Project not found'], Response::HTTP_NOT_FOUND);
            }

            $issue->setProject($project);
        } else {
            // Task requires parentId
            if (empty($data['parentId'])) {
                return $this->json(['message' => 'parentId is required for task'], Response::HTTP_BAD_REQUEST);
            }

            $parent = $this->issueRepository->find($data['parentId']);
            if (!$parent) {
                return $this->json(['message' => 'Parent issue not found'], Response::HTTP_NOT_FOUND);
            }

            $issue->setParent($parent);
            // Inherit project from parent
            $issue->setProject($parent->getProject());
        }

        $this->entityManager->persist($issue);
        $this->entityManager->flush();

        return $this->json($this->serialize($issue), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'issues_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $issue = $this->issueRepository->find($id);

        if (!$issue) {
            return $this->json(['message' => 'Issue not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) {
            $issue->setTitle($data['title']);
        }

        if (isset($data['description'])) {
            $issue->setDescription($data['description']);
        }

        if (isset($data['status'])) {
            $allowedStatuses = ['todo', 'in_progress', 'done'];
            if (!in_array($data['status'], $allowedStatuses)) {
                return $this->json(['message' => 'Invalid status. Allowed: todo, in_progress, done'], Response::HTTP_BAD_REQUEST);
            }
            $issue->setStatus($data['status']);
        }

        if (isset($data['assigneeId'])) {
            $assignee = $this->userRepository->find($data['assigneeId']);
            if (!$assignee) {
                return $this->json(['message' => 'Assignee not found'], Response::HTTP_NOT_FOUND);
            }
            $issue->setAssignee($assignee);
        }

        if (array_key_exists('storyPoints', $data)) {
            $issue->setStoryPoints($data['storyPoints']);
        }

        $issue->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json($this->serialize($issue));
    }

    #[Route('/{id}', name: 'issues_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $issue = $this->issueRepository->find($id);

        if (!$issue) {
            return $this->json(['message' => 'Issue not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($issue);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function serialize(Issue $issue): array
    {
        $data = [
            'id' => $issue->getId(),
            'title' => $issue->getTitle(),
            'description' => $issue->getDescription(),
            'type' => $issue->getType(),
            'status' => $issue->getStatus(),
            'storyPoints' => $issue->getStoryPoints(),
            'createdAt' => $issue->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $issue->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];

        if ($issue->getProject()) {
            $data['project'] = [
                'id' => $issue->getProject()->getId(),
                'name' => $issue->getProject()->getName(),
            ];
        }

        if ($issue->getParent()) {
            $data['parent'] = [
                'id' => $issue->getParent()->getId(),
                'title' => $issue->getParent()->getTitle(),
            ];
        }

        if ($issue->getAssignee()) {
            $data['assignee'] = [
                'id' => $issue->getAssignee()->getId(),
                'email' => $issue->getAssignee()->getEmail(),
            ];
        }

        if ($issue->getReporter()) {
            $data['reporter'] = [
                'id' => $issue->getReporter()->getId(),
                'email' => $issue->getReporter()->getEmail(),
            ];
        }

        // Include children issues for epics
        $children = $issue->getIssues();
        if ($children->count() > 0) {
            $data['children'] = [];
            foreach ($children as $child) {
                $data['children'][] = [
                    'id' => $child->getId(),
                    'title' => $child->getTitle(),
                    'type' => $child->getType(),
                    'status' => $child->getStatus(),
                ];
            }
        }

        return $data;
    }
}
