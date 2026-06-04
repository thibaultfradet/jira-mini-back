<?php

namespace App\Controller;

use App\Repository\IssueRepository;
use App\Repository\NotificationRepository;
use App\Repository\ProjectRepository;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DashboardController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private ProjectRepository $projectRepository,
        private IssueRepository $issueRepository,
        private NotificationRepository $notificationRepository,
    ) {
    }

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getUser();

        $topProjects = $this->projectRepository->findTopActive(5);
        $projects = [];
        foreach ($topProjects as $result) {
            $project = $result[0];
            $projects[] = [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'openedIssueCount' => (int) $result['openedIssueCount'],
                'finishedIssueCount' => (int) $result['finishedIssueCount'],
            ];
        }

        $assignedIssues = $this->issueRepository->findAssignedTo($user->getId());
        $inProgress = [];
        $todo = [];
        foreach ($assignedIssues as $issue) {
            $data = [
                'id' => $issue->getId(),
                'title' => $issue->getTitle(),
                'type' => $issue->getType(),
                'status' => $issue->getStatus(),
                'storyPoints' => $issue->getStoryPoints(),
                'urgency' => $issue->getUrgency(),
                'deadline' => $issue->getDeadline()?->format(\DateTimeInterface::ATOM),
                'project' => $issue->getProject() ? [
                    'id' => $issue->getProject()->getId(),
                    'name' => $issue->getProject()->getName(),
                ] : null,
            ];

            if ($issue->getStatus() === 'in_progress') {
                $inProgress[] = $data;
            } else {
                $todo[] = $data;
            }
        }

        return $this->success([
            'data' => [
                'projects' => $projects,
                'myTasks' => [
                    'inProgress' => $inProgress,
                    'todo' => $todo,
                ],
                'unreadNotificationCount' => $this->notificationRepository->countUnreadForUser($user->getId()),
            ],
        ]);
    }
}
