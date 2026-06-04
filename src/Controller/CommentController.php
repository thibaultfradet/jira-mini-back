<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Repository\CommentRepository;
use App\Repository\IssueRepository;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/issues/{issueId}/comments')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class CommentController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private IssueRepository $issueRepository,
        private CommentRepository $commentRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'comments_list', methods: ['GET'])]
    public function list(int $issueId): JsonResponse
    {
        $issue = $this->issueRepository->find($issueId);
        if (!$issue) {
            return $this->notFound('Issue not found');
        }

        $comments = array_map(fn(Comment $c) => $this->serialize($c), $issue->getComments()->toArray());
        return $this->success(['data' => $comments]);
    }

    #[Route('', name: 'comments_create', methods: ['POST'])]
    public function create(int $issueId, Request $request): JsonResponse
    {
        $issue = $this->issueRepository->find($issueId);
        if (!$issue) {
            return $this->notFound('Issue not found');
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['content'])) {
            return $this->error('Content is required');
        }

        $comment = new Comment();
        $comment->setContent($data['content']);
        $comment->setIssue($issue);
        $comment->setAuthor($this->getUser());

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $this->created(['data' => $this->serialize($comment)]);
    }

    #[Route('/{id}', name: 'comments_update', methods: ['PATCH'])]
    public function update(int $issueId, int $id, Request $request): JsonResponse
    {
        $comment = $this->commentRepository->find($id);
        if (!$comment || $comment->getIssue()->getId() !== $issueId) {
            return $this->notFound('Comment not found');
        }

        if ($comment->getAuthor()->getId() !== $this->getUser()->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->forbidden('You can only edit your own comments');
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['content'])) {
            $comment->setContent($data['content']);
        }

        $this->entityManager->flush();

        return $this->success(['data' => $this->serialize($comment)]);
    }

    #[Route('/{id}', name: 'comments_delete', methods: ['DELETE'])]
    public function delete(int $issueId, int $id): JsonResponse
    {
        $comment = $this->commentRepository->find($id);
        if (!$comment || $comment->getIssue()->getId() !== $issueId) {
            return $this->notFound('Comment not found');
        }

        if ($comment->getAuthor()->getId() !== $this->getUser()->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->forbidden('You can only delete your own comments');
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        return $this->noContent();
    }

    private function serialize(Comment $comment): array
    {
        $author = $comment->getAuthor();
        return [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'author' => [
                'id' => $author->getId(),
                'firstName' => $author->getFirstName(),
                'lastName' => $author->getLastName(),
                'email' => $author->getEmail(),
            ],
            'createdAt' => $comment->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $comment->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
