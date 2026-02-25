<?php

namespace App\Controller;

use App\Repository\ServerRepository;
use App\Repository\UserAchievementRepository;
use App\Repository\UserRepository;
use App\Service\AchievementService;
use App\Service\PremiumService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicProfileController extends AbstractController
{
    #[Route('/profil/{username}', name: 'profile_show', priority: -1)]
    public function show(
        string $username,
        UserRepository $userRepo,
        UserAchievementRepository $userAchievementRepo,
        ServerRepository $serverRepo,
        AchievementService $achievementService,
        PremiumService $premiumService,
    ): Response {
        $profileUser = $userRepo->findOneByUsernameInsensitive($username);
        if (!$profileUser) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $currentUser = $this->getUser();
        $isOwnProfile = $currentUser && $currentUser->getId() === $profileUser->getId();

        // Auto-check achievements when viewing own profile (fallback trigger)
        if ($isOwnProfile) {
            $achievementService->checkAndAwardAchievements($profileUser);
        }

        $achievements = $userAchievementRepo->findByUser($profileUser);

        $servers = [];
        if ($profileUser->isFieldVisible('servers') || $isOwnProfile) {
            $servers = $serverRepo->findActiveByOwner($profileUser);
        }

        $hasTwitchLive = $profileUser->getTwitchUsername()
            && $premiumService->hasUserTwitchLiveActive($profileUser);

        return $this->render('user/public_profile.html.twig', [
            'profileUser'   => $profileUser,
            'achievements'  => $achievements,
            'servers'       => $servers,
            'isOwnProfile'  => $isOwnProfile,
            'hasTwitchLive' => $hasTwitchLive,
        ]);
    }
}
