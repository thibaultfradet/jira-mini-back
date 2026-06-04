<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class NotificationController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'notifications_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        $notifications = $this->notificationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            50
        );

        return $this->success([
            'data' => array_map(fn($n) => [
                'id' => $n->getId(),
                'type' => $n->getType(),
                'message' => $n->getMessage(),
                'isRead' => $n->isRead(),
                'relatedIssueId' => $n->getRelatedIssueId(),
                'createdAt' => $n->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ], $notifications),
            'unreadCount' => $this->notificationRepository->countUnreadForUser($user->getId()),
        ]);
    }

    #[Route('/{id}/read', name: 'notifications_mark_read', methods: ['PATCH'])]
    public function markRead(int $id): JsonResponse
    {
        $notification = $this->notificationRepository->find($id);
        if (!$notification || $notification->getUser()->getId() !== $this->getUser()->getId()) {
            return $this->notFound('Notification not found');
        }

        $notification->setIsRead(true);
        $this->entityManager->flush();

        return $this->success(['data' => ['id' => $id, 'isRead' => true]]);
    }

    #[Route('/read-all', name: 'notifications_mark_all_read', methods: ['PATCH'])]
    public function markAllRead(): JsonResponse
    {
        $user = $this->getUser();
        $unread = $this->notificationRepository->findBy(['user' => $user, 'isRead' => false]);

        foreach ($unread as $notification) {
            $notification->setIsRead(true);
        }
        $this->entityManager->flush();

        return $this->success(['data' => ['marked' => count($unread)]]);
    }
}
