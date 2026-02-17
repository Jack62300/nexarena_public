<?php

namespace App\Controller;

use App\Repository\ServerRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicProfileController extends AbstractController
{
    #[Route('/profil/{username}', name: 'profile_show', priority: -1)]
    public function show(
        string $username,
        UserRepository $userRepo,
        UserBadgeRepository $userBadgeRepo,
        ServerRepository $serverRepo,
        BadgeService $badgeService,
    ): Response {
        $profileUser = $userRepo->findOneByUsernameInsensitive($username);
        if (!$profileUser) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $currentUser = $this->getUser();
        $isOwnProfile = $currentUser && $currentUser->getId() === $profileUser->getId();

        // Lazy badge check on own profile view
        if ($isOwnProfile) {
            $badgeService->checkAndAwardBadges($profileUser);
        }

        $badges = $userBadgeRepo->findByUser($profileUser);

        $servers = [];
        if ($profileUser->isFieldVisible('servers') || $isOwnProfile) {
            $servers = $serverRepo->findActiveByOwner($profileUser);
        }

        return $this->render('user/public_profile.html.twig', [
            'profileUser' => $profileUser,
            'badges' => $badges,
            'servers' => $servers,
            'isOwnProfile' => $isOwnProfile,
        ]);
    }
}
