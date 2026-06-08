<?php

namespace App\Controller;

use App\Entity\Sprint;
use App\Repository\SprintRepository;
use App\Repository\TeamRepository;
use App\Service\StatsService;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stats')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class StatsController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private StatsService $statsService,
        private SprintRepository $sprintRepository,
        private TeamRepository $teamRepository,
    ) {
    }

    #[Route('/sprint/{id}', name: 'stats_sprint', methods: ['GET'])]
    public function sprintStats(int $id): JsonResponse
    {
        $sprint = $this->sprintRepository->find($id);
        if (!$sprint) {
            return $this->notFound('Sprint not found');
        }

        return $this->success(['data' => $this->statsService->getSprintStats($sprint)]);
    }

    #[Route('/velocity', name: 'stats_velocity', methods: ['GET'])]
    public function velocity(Request $request): JsonResponse
    {
        $teamId = $request->query->get('teamId');
        if (!$teamId) {
            return $this->error('teamId is required');
        }

        $team = $this->teamRepository->find($teamId);
        if (!$team) {
            return $this->notFound('Team not found');
        }

        $limit = max(1, min(20, (int) ($request->query->get('limit', 5))));

        return $this->success(['data' => $this->statsService->getVelocity($team, $limit)]);
    }
}
