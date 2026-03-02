<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Service\ActivityLogService;
use App\Service\WheelService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WheelController extends AbstractController
{
    public function __construct(
        private WheelService $wheelService,
        private ActivityLogService $activityLog,
    ) {
    }

    #[Route('/profil/roue/spin', name: 'wheel_spin', methods: ['POST'])]
    public function spin(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wheel_spin', $request->request->get('_token'))) {
            return $this->json(['error' => 'Token CSRF invalide.'], 403);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $canResult = $this->wheelService->canSpin($user);
        if (!$canResult['can']) {
            return $this->json(['error' => $canResult['reason']], 400);
        }

        try {
            $result = $this->wheelService->spin($user);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $this->activityLog->log(
            'wheel.spin',
            ActivityLog::CAT_PROFILE,
            'WheelSpin',
            $result['spin']->getId(),
            $result['prize']['label'],
            [
                'type' => $result['spin']->getType(),
                'sectionIndex' => $result['sectionIndex'],
                'nexbitsWon' => $result['prize']['nexbits'],
                'nexboostWon' => $result['prize']['nexboost'],
                'isJackpot' => $result['prize']['index'] === 11,
            ]
        );

        return $this->json([
            'sectionIndex' => $result['sectionIndex'],
            'prize' => [
                'label' => $result['prize']['label'],
                'nexbits' => $result['prize']['nexbits'],
                'nexboost' => $result['prize']['nexboost'],
            ],
            'newBalance' => [
                'nexbits' => $user->getTokenBalance(),
                'nexboost' => $user->getBoostTokenBalance(),
            ],
            'remainingFreeSpins' => $user->getFreeSpins(),
            'isJackpot' => $result['prize']['index'] === 11,
        ]);
    }
}
