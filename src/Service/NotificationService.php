<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationRepository $notificationRepo,
    ) {
    }

    public function create(User $user, string $type, string $title, string $message, ?string $link = null): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle(mb_substr($title, 0, 255));
        $notification->setMessage(mb_substr($message, 0, 500));
        $notification->setLink($link ? mb_substr($link, 0, 500) : null);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    public function markRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $this->em->flush();
    }

    public function markAllRead(User $user): void
    {
        $this->notificationRepo->markAllRead($user);
    }

    public function countUnread(User $user): int
    {
        return $this->notificationRepo->countUnread($user);
    }

    /**
     * @return Notification[]
     */
    public function getRecent(User $user, int $limit = 10): array
    {
        return $this->notificationRepo->findByUser($user, $limit);
    }
}
