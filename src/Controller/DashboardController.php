<?php

namespace App\Controller;

use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private IssueRepository $issueRepository,
    ) {
    }

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getUser();

        // Top 5 most active projects
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

        // Current user's assigned tasks (in_progress + todo)
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

        return $this->json([
            'projects' => $projects,
            'myTasks' => [
                'inProgress' => $inProgress,
                'todo' => $todo,
            ],
        ]);
    }
}
