<?php

namespace App\Controller\Api;

use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService,
        private NotificationRepository $notificationRepo,
    ) {
    }

    #[Route('/api/notifications', name: 'api_notifications', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $unreadCount = $this->notificationService->countUnread($user);

        $countOnly = $request->query->get('count_only');
        if ($countOnly) {
            return new JsonResponse(['unreadCount' => $unreadCount]);
        }

        $notifications = $this->notificationService->getRecent($user, 10);

        return new JsonResponse([
            'unreadCount' => $unreadCount,
            'notifications' => array_map(function ($n) {
                return [
                    'id' => $n->getId(),
                    'type' => $n->getType(),
                    'title' => $n->getTitle(),
                    'message' => $n->getMessage(),
                    'link' => $n->getLink(),
                    'isRead' => $n->isRead(),
                    'createdAt' => $n->getCreatedAt()->format('d/m/Y H:i'),
                ];
            }, $notifications),
        ]);
    }

    #[Route('/api/notifications/{id}/read', name: 'api_notification_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markRead(int $id): JsonResponse
    {
        $notification = $this->notificationRepo->find($id);
        if (!$notification || $notification->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $this->notificationService->markRead($notification);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/notifications/read-all', name: 'api_notifications_read_all', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        $this->notificationService->markAllRead($this->getUser());

        return new JsonResponse(['success' => true]);
    }
}
