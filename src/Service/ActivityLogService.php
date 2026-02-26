<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ActivityLogService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TokenStorageInterface  $tokenStorage,
        private readonly RequestStack           $requestStack,
    ) {}

    /**
     * Record an activity log entry.
     *
     * @param string      $action      e.g. "server.delete", "settings.save"
     * @param string      $category    One of ActivityLog::CAT_* constants
     * @param string|null $objectType  e.g. "Server", "User", "Setting"
     * @param int|null    $objectId    DB id of affected object
     * @param string|null $objectLabel Human-readable name
     * @param array|null  $details     Extra context (key → value pairs)
     * @param User|null   $actor       Override the current user (null = auto-detect)
     */
    public function log(
        string  $action,
        string  $category,
        ?string $objectType  = null,
        ?int    $objectId    = null,
        ?string $objectLabel = null,
        ?array  $details     = null,
        ?User   $actor       = null,
    ): void {
        try {
            // Resolve the actor
            if ($actor === null) {
                $token = $this->tokenStorage->getToken();
                if ($token !== null) {
                    $user = $token->getUser();
                    if ($user instanceof User) {
                        $actor = $user;
                    }
                }
            }

            $log = new ActivityLog();
            $log->setAction($action);
            $log->setCategory($category);
            $log->setObjectType($objectType);
            $log->setObjectId($objectId);
            $log->setObjectLabel($objectLabel);
            $log->setDetails($details);

            if ($actor) {
                $log->setUser($actor);
                $log->setUsername($actor->getUsername());
            }

            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $log->setIpAddress($request->getClientIp());
            }

            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable) {
            // Never let logging break the application
        }
    }
}
