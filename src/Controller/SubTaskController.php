<?php

namespace App\Controller;

use App\Entity\SubTask;
use App\Repository\IssueRepository;
use App\Repository\SubTaskRepository;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/issues/{issueId}/subtasks')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class SubTaskController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private IssueRepository $issueRepository,
        private SubTaskRepository $subTaskRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'subtasks_list', methods: ['GET'])]
    public function list(int $issueId): JsonResponse
    {
        $issue = $this->issueRepository->find($issueId);
        if (!$issue) {
            return $this->notFound('Issue not found');
        }

        $subTasks = array_map(fn(SubTask $s) => $this->serialize($s), $issue->getSubTasks()->toArray());
        return $this->success(['data' => $subTasks]);
    }

    #[Route('', name: 'subtasks_create', methods: ['POST'])]
    public function create(int $issueId, Request $request): JsonResponse
    {
        $issue = $this->issueRepository->find($issueId);
        if (!$issue) {
            return $this->notFound('Issue not found');
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['title'])) {
            return $this->error('Title is required');
        }

        $maxPosition = $issue->getSubTasks()->isEmpty()
            ? -1
            : max(array_map(fn(SubTask $s) => $s->getPosition(), $issue->getSubTasks()->toArray()));

        $subTask = new SubTask();
        $subTask->setTitle($data['title']);
        $subTask->setIssue($issue);
        $subTask->setPosition($maxPosition + 1);

        $this->entityManager->persist($subTask);
        $this->entityManager->flush();

        return $this->created(['data' => $this->serialize($subTask)]);
    }

    #[Route('/{id}', name: 'subtasks_update', methods: ['PATCH'])]
    public function update(int $issueId, int $id, Request $request): JsonResponse
    {
        $subTask = $this->subTaskRepository->find($id);
        if (!$subTask || $subTask->getIssue()->getId() !== $issueId) {
            return $this->notFound('SubTask not found');
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) {
            $subTask->setTitle($data['title']);
        }
        if (isset($data['isDone'])) {
            $subTask->setIsDone((bool) $data['isDone']);
        }
        if (isset($data['position'])) {
            $subTask->setPosition((int) $data['position']);
        }

        $this->entityManager->flush();

        return $this->success(['data' => $this->serialize($subTask)]);
    }

    #[Route('/{id}', name: 'subtasks_delete', methods: ['DELETE'])]
    public function delete(int $issueId, int $id): JsonResponse
    {
        $subTask = $this->subTaskRepository->find($id);
        if (!$subTask || $subTask->getIssue()->getId() !== $issueId) {
            return $this->notFound('SubTask not found');
        }

        $this->entityManager->remove($subTask);
        $this->entityManager->flush();

        return $this->noContent();
    }

    private function serialize(SubTask $subTask): array
    {
        return [
            'id' => $subTask->getId(),
            'title' => $subTask->getTitle(),
            'isDone' => $subTask->isDone(),
            'position' => $subTask->getPosition(),
            'createdAt' => $subTask->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $subTask->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
