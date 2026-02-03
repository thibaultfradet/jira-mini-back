<?php

namespace App\Controller;

use App\Repository\SprintRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sprints')]
class SprintController extends AbstractController
{
    public function __construct(
        private SprintRepository $sprintRepository,
    ) {
    }

    #[Route('/all', name: 'sprints_all', methods: ['GET'])]
    public function all(): JsonResponse
    {
        $sprints = $this->sprintRepository->findBy([], ['startDate' => 'ASC']);

        return $this->json(array_map(fn($sprint) => $this->serializeSprint($sprint), $sprints));
    }

    #[Route('', name: 'sprints_active', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $sprint = $this->sprintRepository->findOneBy(['isActive' => true]);

        if (!$sprint) {
            return $this->json(['message' => 'No active sprint found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeSprint($sprint));
    }

    private function serializeSprint($sprint): array
    {
        $issues = [];
        foreach ($sprint->getIssues() as $issue) {
            if ($issue->getType() === 'epic') {
                continue;
            }

            $issues[] = $this->serializeIssue($issue);
        }

        return [
            'id' => $sprint->getId(),
            'name' => $sprint->getName(),
            'startDate' => $sprint->getStartDate()?->format('Y-m-d'),
            'endDate' => $sprint->getEndDate()?->format('Y-m-d'),
            'isActive' => $sprint->isActive(),
            'issues' => $issues,
        ];
    }

    private function serializeIssue($issue): array
    {
        $assignee = $issue->getAssignee();
        $reporter = $issue->getReporter();

        return [
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
            'reporter' => $reporter ? [
                'id' => $reporter->getId(),
                'firstName' => $reporter->getFirstName(),
                'lastName' => $reporter->getLastName(),
                'email' => $reporter->getEmail(),
            ] : null,
        ];
    }
}
