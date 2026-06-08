<?php

namespace App\Service;

use App\Entity\Sprint;
use App\Entity\Team;
use App\Repository\IssueStatusHistoryRepository;
use App\Repository\SprintRepository;

class StatsService
{
    public function __construct(
        private IssueStatusHistoryRepository $historyRepository,
        private SprintRepository $sprintRepository,
    ) {
    }

    /**
     * Returns daily burndown/burnup data points for a sprint.
     */
    public function getSprintStats(Sprint $sprint): array
    {
        $issues = $sprint->getIssues()->filter(
            fn($issue) => $issue->getType() !== 'epic'
        )->toArray();

        $totalPoints = array_sum(array_map(
            fn($issue) => $issue->getStoryPoints() ?? 1,
            $issues
        ));

        $issueIds = array_map(fn($issue) => $issue->getId(), $issues);

        // Map issueId → completion date (from history or updatedAt fallback)
        $historyMap = $this->historyRepository->findLastDoneTransitionByIssueIds($issueIds);
        $completionDates = [];
        foreach ($issues as $issue) {
            if ($issue->getStatus() !== 'done') {
                continue;
            }
            $id = $issue->getId();
            if (isset($historyMap[$id])) {
                $completionDates[$id] = $historyMap[$id];
            } elseif ($issue->getUpdatedAt() !== null) {
                // Fallback for issues completed before history tracking was added
                $completionDates[$id] = \DateTimeImmutable::createFromMutable(
                    \DateTime::createFromInterface($issue->getUpdatedAt())
                );
            }
        }

        // Map issueId → storyPoints
        $pointsMap = [];
        foreach ($issues as $issue) {
            $pointsMap[$issue->getId()] = $issue->getStoryPoints() ?? 1;
        }

        $startDate = \DateTimeImmutable::createFromMutable($sprint->getStartDate());
        $endDate = \DateTimeImmutable::createFromMutable($sprint->getEndDate());
        $today = new \DateTimeImmutable('today');
        $lastDay = $endDate < $today ? $endDate : $today;

        $totalDays = max(1, (int) $startDate->diff($endDate)->days);
        $dailyData = [];

        $current = $startDate;
        while ($current <= $lastDay) {
            $dayStr = $current->format('Y-m-d');
            $elapsed = max(1, (int) $startDate->diff($current)->days + 1);

            $completedPoints = 0;
            foreach ($completionDates as $issueId => $completedAt) {
                if ($completedAt->format('Y-m-d') <= $dayStr) {
                    $completedPoints += $pointsMap[$issueId] ?? 0;
                }
            }

            $ratio = min(1.0, $elapsed / $totalDays);

            $dailyData[] = [
                'date' => $dayStr,
                'completedPoints' => $completedPoints,
                'remainingPoints' => $totalPoints - $completedPoints,
                'idealCompleted' => (int) round($totalPoints * $ratio),
                'idealRemaining' => (int) round($totalPoints * (1 - $ratio)),
            ];

            $current = $current->modify('+1 day');
        }

        $breakdown = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
        foreach ($issues as $issue) {
            $breakdown[$issue->getStatus()] = ($breakdown[$issue->getStatus()] ?? 0) + 1;
        }

        return [
            'sprint' => [
                'id' => $sprint->getId(),
                'name' => $sprint->getName(),
                'startDate' => $sprint->getStartDate()->format(\DateTimeInterface::ATOM),
                'endDate' => $sprint->getEndDate()->format(\DateTimeInterface::ATOM),
                'status' => $sprint->getStatus(),
                'team' => $sprint->getTeam() ? [
                    'id' => $sprint->getTeam()->getId(),
                    'name' => $sprint->getTeam()->getName(),
                ] : null,
            ],
            'totalPoints' => $totalPoints,
            'dailyData' => $dailyData,
            'issueBreakdown' => $breakdown,
        ];
    }

    /**
     * Returns velocity data for the last $limit completed/active sprints of a team.
     */
    public function getVelocity(Team $team, int $limit = 5): array
    {
        $sprints = $this->sprintRepository->findLastCompletedOrActiveByTeam($team, $limit);

        $result = [];
        foreach ($sprints as $sprint) {
            $issues = $sprint->getIssues()->filter(
                fn($issue) => $issue->getType() !== 'epic'
            );

            $totalPoints = 0;
            $completedPoints = 0;
            foreach ($issues as $issue) {
                $sp = $issue->getStoryPoints() ?? 1;
                $totalPoints += $sp;
                if ($issue->getStatus() === 'done') {
                    $completedPoints += $sp;
                }
            }

            $result[] = [
                'sprintId' => $sprint->getId(),
                'sprintName' => $sprint->getName(),
                'startDate' => $sprint->getStartDate()->format(\DateTimeInterface::ATOM),
                'endDate' => $sprint->getEndDate()->format(\DateTimeInterface::ATOM),
                'status' => $sprint->getStatus(),
                'totalPoints' => $totalPoints,
                'completedPoints' => $completedPoints,
            ];
        }

        return $result;
    }
}
