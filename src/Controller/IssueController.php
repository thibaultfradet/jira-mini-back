<?php

namespace App\Controller;

use App\Entity\Issue;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\SprintRepository;
use App\Repository\UserRepository;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/issues')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class IssueController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private IssueRepository $issueRepository,
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
        private SprintRepository $sprintRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/backlog', name: 'issues_backlog', methods: ['GET'])]
    public function backlog(): JsonResponse
    {
        $issues = $this->issueRepository->findBacklog();

        return $this->success(['data' => array_map(fn($issue) => $this->serializeList($issue), $issues)]);
    }

    #[Route('/{id}', name: 'issues_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $issue = $this->issueRepository->find($id);
        if (!$issue) {
            return $this->notFound('Issue not found');
        }

        return $this->success(['data' => $this->serialize($issue)]);
    }

    #[Route('/{id}/children', name: 'issues_children', methods: ['GET'])]
    public function getChildren(int $id): JsonResponse
    {
        $issue = $this->issueRepository->find($id);
        if (!$issue) {
            return $this->notFound('Issue not found');
        }

        $children = array_map(fn($child) => $this->serializeList($child), $issue->getIssues()->toArray());

        return $this->success(['data' => $children]);
    }

    #[Route('', name: 'issues_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['title'])) {
            return $this->error('Title is required');
        }
        if (empty($data['type']) || !in_array($data['type'], Issue::TYPES)) {
            return $this->error('Type is required. Allowed: ' . implode(', ', Issue::TYPES));
        }

        $issue = new Issue();
        $issue->setTitle($data['title']);
        $issue->setType($data['type']);
        $issue->setDescription($data['description'] ?? '');
        $issue->setStatus('todo');
        $issue->setReporter($this->getUser());

        if ($data['type'] === 'epic') {
            if (empty($data['projectId'])) {
                return $this->error('projectId is required for epic');
            }
            $project = $this->projectRepository->find($data['projectId']);
            if (!$project) {
                return $this->notFound('Project not found');
            }
            $issue->setProject($project);
        } else {
            if (empty($data['parentId'])) {
                return $this->error('parentId is required for task/story/bug');
            }
            $parent = $this->issueRepository->find($data['parentId']);
            if (!$parent) {
                return $this->notFound('Parent issue not found');
            }
            $issue->setParent($parent);
            $issue->setProject($parent->getProject());
        }

        if (!empty($data['assigneeId'])) {
            $assignee = $this->userRepository->find($data['assigneeId']);
            if ($assignee) {
                $issue->setAssignee($assignee);
            }
        }
        if (!empty($data['storyPoints'])) {
            $issue->setStoryPoints((int) $data['storyPoints']);
        }
        if (!empty($data['urgency']) && in_array($data['urgency'], Issue::URGENCIES)) {
            $issue->setUrgency($data['urgency']);
        }
        if (!empty($data['deadline'])) {
            $issue->setDeadline(new \DateTimeImmutable($data['deadline']));
        }

        $this->entityManager->persist($issue);
        $this->entityManager->flush();

        return $this->created(['data' => $this->serialize($issue)]);
    }

    #[Route('/{id}', name: 'issues_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $issue = $this->issueRepository->find($id);
        if (!$issue) {
            return $this->notFound('Issue not found');
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) {
            $issue->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $issue->setDescription($data['description']);
        }
        if (isset($data['status'])) {
            if (!in_array($data['status'], Issue::STATUSES)) {
                return $this->error('Invalid status. Allowed: ' . implode(', ', Issue::STATUSES));
            }
            $issue->setStatus($data['status']);
        }
        if (isset($data['urgency'])) {
            if ($data['urgency'] !== null && !in_array($data['urgency'], Issue::URGENCIES)) {
                return $this->error('Invalid urgency. Allowed: ' . implode(', ', Issue::URGENCIES));
            }
            $issue->setUrgency($data['urgency']);
        }
        if (array_key_exists('deadline', $data)) {
            $issue->setDeadline($data['deadline'] ? new \DateTimeImmutable($data['deadline']) : null);
        }
        if (array_key_exists('assigneeId', $data)) {
            if ($data['assigneeId'] === null) {
                $issue->setAssignee(null);
            } else {
                $assignee = $this->userRepository->find($data['assigneeId']);
                if (!$assignee) {
                    return $this->notFound('Assignee not found');
                }
                $issue->setAssignee($assignee);
            }
        }
        if (array_key_exists('storyPoints', $data)) {
            $issue->setStoryPoints($data['storyPoints']);
        }
        if (array_key_exists('sprintId', $data)) {
            foreach ($issue->getSprints() as $sprint) {
                $issue->removeSprint($sprint);
            }
            if ($data['sprintId'] !== null) {
                $sprint = $this->sprintRepository->find($data['sprintId']);
                if (!$sprint) {
                    return $this->notFound('Sprint not found');
                }
                $issue->addSprint($sprint);
            }
        }

        $this->entityManager->flush();

        return $this->success(['data' => $this->serialize($issue)]);
    }

    #[Route('/{id}', name: 'issues_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $issue = $this->issueRepository->find($id);
        if (!$issue) {
            return $this->notFound('Issue not found');
        }

        $this->entityManager->remove($issue);
        $this->entityManager->flush();

        return $this->noContent();
    }

    private function serializeList(Issue $issue): array
    {
        $assignee = $issue->getAssignee();
        return [
            'id' => $issue->getId(),
            'title' => $issue->getTitle(),
            'type' => $issue->getType(),
            'status' => $issue->getStatus(),
            'storyPoints' => $issue->getStoryPoints(),
            'urgency' => $issue->getUrgency(),
            'deadline' => $issue->getDeadline()?->format(\DateTimeInterface::ATOM),
            'assignee' => $assignee ? [
                'id' => $assignee->getId(),
                'firstName' => $assignee->getFirstName(),
                'lastName' => $assignee->getLastName(),
                'email' => $assignee->getEmail(),
            ] : null,
        ];
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
            'urgency' => $issue->getUrgency(),
            'deadline' => $issue->getDeadline()?->format(\DateTimeInterface::ATOM),
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
                'firstName' => $issue->getAssignee()->getFirstName(),
                'lastName' => $issue->getAssignee()->getLastName(),
                'email' => $issue->getAssignee()->getEmail(),
            ];
        }

        if ($issue->getReporter()) {
            $data['reporter'] = [
                'id' => $issue->getReporter()->getId(),
                'firstName' => $issue->getReporter()->getFirstName(),
                'lastName' => $issue->getReporter()->getLastName(),
                'email' => $issue->getReporter()->getEmail(),
            ];
        }

        $children = $issue->getIssues();
        if ($children->count() > 0) {
            $data['children'] = array_map(fn($child) => [
                'id' => $child->getId(),
                'title' => $child->getTitle(),
                'type' => $child->getType(),
                'status' => $child->getStatus(),
                'storyPoints' => $child->getStoryPoints(),
            ], $children->toArray());
        }

        $data['subTasks'] = array_map(fn($s) => [
            'id' => $s->getId(),
            'title' => $s->getTitle(),
            'isDone' => $s->isDone(),
            'position' => $s->getPosition(),
        ], $issue->getSubTasks()->toArray());

        $data['comments'] = array_map(fn($c) => [
            'id' => $c->getId(),
            'content' => $c->getContent(),
            'author' => [
                'id' => $c->getAuthor()->getId(),
                'firstName' => $c->getAuthor()->getFirstName(),
                'lastName' => $c->getAuthor()->getLastName(),
            ],
            'createdAt' => $c->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $c->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ], $issue->getComments()->toArray());

        return $data;
    }
}
