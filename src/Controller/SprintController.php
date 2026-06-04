<?php

namespace App\Controller;

use App\Entity\Sprint;
use App\Repository\SprintRepository;
use App\Repository\TeamRepository;
use App\Repository\IssueRepository;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/sprints')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class SprintController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private SprintRepository $sprintRepository,
        private TeamRepository $teamRepository,
        private IssueRepository $issueRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'sprints_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $teamId = $request->query->get('teamId');
        if ($teamId) {
            $team = $this->teamRepository->find($teamId);
            if (!$team) {
                return $this->notFound('Team not found');
            }
            $sprints = $this->sprintRepository->findByTeamOrderedByDate($team);
        } else {
            $sprints = $this->sprintRepository->findAll();
        }

        return $this->success(['data' => array_map(fn(Sprint $s) => $this->serializeSprint($s), $sprints)]);
    }

    #[Route('/active', name: 'sprints_active', methods: ['GET'])]
    public function active(Request $request): JsonResponse
    {
        $teamId = $request->query->get('teamId');
        $criteria = ['status' => Sprint::STATUS_ACTIVE];
        if ($teamId) {
            $team = $this->teamRepository->find($teamId);
            if (!$team) {
                return $this->notFound('Team not found');
            }
            $criteria['team'] = $team;
        }

        $sprint = $this->sprintRepository->findOneBy($criteria);
        if (!$sprint) {
            return $this->notFound('No active sprint found');
        }

        return $this->success(['data' => $this->serializeSprint($sprint, true)]);
    }

    #[Route('/{id}', name: 'sprints_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $sprint = $this->sprintRepository->find($id);
        if (!$sprint) {
            return $this->notFound('Sprint not found');
        }

        return $this->success(['data' => $this->serializeSprint($sprint, true)]);
    }

    #[Route('', name: 'sprints_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->error('Name is required');
        }
        if (empty($data['teamId'])) {
            return $this->error('teamId is required');
        }
        if (empty($data['startDate']) || empty($data['endDate'])) {
            return $this->error('startDate and endDate are required');
        }

        $team = $this->teamRepository->find($data['teamId']);
        if (!$team) {
            return $this->notFound('Team not found');
        }

        $sprint = new Sprint();
        $sprint->setName($data['name']);
        $sprint->setTeam($team);
        $sprint->setGoal($data['goal'] ?? null);
        $sprint->setStartDate(new \DateTime($data['startDate']));
        $sprint->setEndDate(new \DateTime($data['endDate']));
        $sprint->setStatus(Sprint::STATUS_PLANNED);

        $this->entityManager->persist($sprint);
        $this->entityManager->flush();

        return $this->created(['data' => $this->serializeSprint($sprint)]);
    }

    #[Route('/{id}', name: 'sprints_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $sprint = $this->sprintRepository->find($id);
        if (!$sprint) {
            return $this->notFound('Sprint not found');
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $sprint->setName($data['name']);
        }
        if (array_key_exists('goal', $data)) {
            $sprint->setGoal($data['goal']);
        }
        if (isset($data['startDate'])) {
            $sprint->setStartDate(new \DateTime($data['startDate']));
        }
        if (isset($data['endDate'])) {
            $sprint->setEndDate(new \DateTime($data['endDate']));
        }

        $this->entityManager->flush();

        return $this->success(['data' => $this->serializeSprint($sprint)]);
    }

    #[Route('/{id}/start', name: 'sprints_start', methods: ['POST'])]
    public function start(int $id): JsonResponse
    {
        $sprint = $this->sprintRepository->find($id);
        if (!$sprint) {
            return $this->notFound('Sprint not found');
        }

        if ($sprint->getStatus() !== Sprint::STATUS_PLANNED) {
            return $this->error('Only planned sprints can be started', Response::HTTP_CONFLICT);
        }

        $activeSprint = $this->sprintRepository->findActiveByTeam($sprint->getTeam());
        if ($activeSprint) {
            return $this->error(
                "L'équipe a déjà un sprint actif : \"{$activeSprint->getName()}\"",
                Response::HTTP_CONFLICT
            );
        }

        $sprint->setStatus(Sprint::STATUS_ACTIVE);
        $this->entityManager->flush();

        return $this->success(['data' => $this->serializeSprint($sprint)]);
    }

    #[Route('/{id}/complete', name: 'sprints_complete', methods: ['POST'])]
    public function complete(int $id, Request $request): JsonResponse
    {
        $sprint = $this->sprintRepository->find($id);
        if (!$sprint) {
            return $this->notFound('Sprint not found');
        }

        if ($sprint->getStatus() !== Sprint::STATUS_ACTIVE) {
            return $this->error('Only active sprints can be completed', Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);
        // action: 'next_sprint' (default) or 'backlog'
        $action = $data['action'] ?? 'next_sprint';

        // Find unfinished tasks
        $unfinishedIssues = array_filter(
            $sprint->getIssues()->toArray(),
            fn($issue) => $issue->getStatus() !== 'done' && $issue->getType() !== 'epic'
        );

        if ($action === 'next_sprint') {
            $nextSprint = $this->sprintRepository->findNextPlannedByTeam($sprint->getTeam());
            if (!$nextSprint && !empty($unfinishedIssues)) {
                return $this->error(
                    "Aucun sprint planifié trouvé pour cette équipe. Créez-en un avant de terminer ce sprint.",
                    Response::HTTP_CONFLICT
                );
            }

            if ($nextSprint) {
                foreach ($unfinishedIssues as $issue) {
                    $issue->removeSprint($sprint);
                    $issue->addSprint($nextSprint);
                }
            }
        } else {
            // Move to backlog: remove from sprint
            foreach ($unfinishedIssues as $issue) {
                $issue->removeSprint($sprint);
            }
        }

        $sprint->setStatus(Sprint::STATUS_COMPLETED);
        $this->entityManager->flush();

        return $this->success([
            'data' => $this->serializeSprint($sprint),
            'movedCount' => count($unfinishedIssues),
        ]);
    }

    #[Route('/{id}/issues', name: 'sprints_add_issue', methods: ['POST'])]
    public function addIssue(int $id, Request $request): JsonResponse
    {
        $sprint = $this->sprintRepository->find($id);
        if (!$sprint) {
            return $this->notFound('Sprint not found');
        }

        if ($sprint->getStatus() === Sprint::STATUS_COMPLETED) {
            return $this->error('Cannot add issues to a completed sprint', Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['issueId'])) {
            return $this->error('issueId is required');
        }

        $issue = $this->issueRepository->find($data['issueId']);
        if (!$issue) {
            return $this->notFound('Issue not found');
        }

        $issue->addSprint($sprint);
        $this->entityManager->flush();

        return $this->success(['data' => ['sprintId' => $id, 'issueId' => $issue->getId()]]);
    }

    #[Route('/{id}/issues/{issueId}', name: 'sprints_remove_issue', methods: ['DELETE'])]
    public function removeIssue(int $id, int $issueId): JsonResponse
    {
        $sprint = $this->sprintRepository->find($id);
        if (!$sprint) {
            return $this->notFound('Sprint not found');
        }

        $issue = $this->issueRepository->find($issueId);
        if (!$issue) {
            return $this->notFound('Issue not found');
        }

        $issue->removeSprint($sprint);
        $this->entityManager->flush();

        return $this->noContent();
    }

    #[Route('/{id}', name: 'sprints_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $sprint = $this->sprintRepository->find($id);
        if (!$sprint) {
            return $this->notFound('Sprint not found');
        }

        if ($sprint->getStatus() === Sprint::STATUS_ACTIVE) {
            return $this->error('Cannot delete an active sprint. Complete it first.', Response::HTTP_CONFLICT);
        }

        $this->entityManager->remove($sprint);
        $this->entityManager->flush();

        return $this->noContent();
    }

    private function serializeSprint(Sprint $sprint, bool $withIssues = false): array
    {
        $team = $sprint->getTeam();
        $data = [
            'id' => $sprint->getId(),
            'name' => $sprint->getName(),
            'goal' => $sprint->getGoal(),
            'startDate' => $sprint->getStartDate()?->format('Y-m-d'),
            'endDate' => $sprint->getEndDate()?->format('Y-m-d'),
            'status' => $sprint->getStatus(),
            'team' => $team ? [
                'id' => $team->getId(),
                'name' => $team->getName(),
            ] : null,
            'issueCount' => $sprint->getIssues()->count(),
            'createdAt' => $sprint->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $sprint->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];

        if ($withIssues) {
            $data['issues'] = [];
            foreach ($sprint->getIssues() as $issue) {
                if ($issue->getType() === 'epic') {
                    continue;
                }
                $assignee = $issue->getAssignee();
                $data['issues'][] = [
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
        }

        return $data;
    }
}
