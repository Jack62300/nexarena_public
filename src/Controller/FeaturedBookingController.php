<?php

namespace App\Controller;

use App\Entity\FeaturedBooking;
use App\Entity\Server;
use App\Entity\ServerCollaborator;
use App\Repository\FeaturedBookingRepository;
use App\Repository\ServerCollaboratorRepository;
use App\Service\PremiumService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class FeaturedBookingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PremiumService $premiumService,
        private FeaturedBookingRepository $bookingRepo,
        private ServerCollaboratorRepository $collabRepo,
    ) {
    }

    private function canAccessBoost(Server $server): bool
    {
        $user = $this->getUser();
        if ($server->getOwner() === $user) {
            return true;
        }
        $collab = $this->collabRepo->findByServerAndUser($server, $user);
        return $collab !== null && $collab->hasPermission('manage_boost');
    }

    #[Route('/serveur/{id}/boost', name: 'featured_booking')]
    public function index(Server $server): Response
    {
        if (!$this->canAccessBoost($server)) {
            throw $this->createAccessDeniedException();
        }

        $homepageCosts = $this->premiumService->getAllPositionCosts(FeaturedBooking::SCOPE_HOMEPAGE);
        $gameCosts = $this->premiumService->getAllPositionCosts(FeaturedBooking::SCOPE_GAME);

        $startRange = new \DateTime('today 00:00');
        $endRange = (clone $startRange)->modify('+30 days');

        $homepageAvailability = $this->bookingRepo->getPositionAvailabilityForRange(
            FeaturedBooking::SCOPE_HOMEPAGE, $startRange, $endRange
        );

        // The server's game category (if any) — used for scope "game"
        $serverGc = $server->getGameCategory();
        $hasGameScope = $serverGc !== null;

        return $this->render('premium/featured_booking.html.twig', [
            'server' => $server,
            'homepage_costs' => $homepageCosts,
            'game_costs' => $gameCosts,
            'homepage_availability' => $homepageAvailability,
            'user_boost_balance' => $server->getBoostTokenBalance(),
            'has_game_scope' => $hasGameScope,
            'server_gc_id' => $serverGc ? $serverGc->getId() : 0,
            'server_gc_name' => $serverGc ? $serverGc->getName() : '',
        ]);
    }

    #[Route('/serveur/{id}/boost/availability', name: 'featured_booking_availability', methods: ['GET'])]
    public function availability(Server $server, Request $request): JsonResponse
    {
        if (!$this->canAccessBoost($server)) {
            return new JsonResponse(['error' => 'Acces refuse.'], 403);
        }

        $scope = $request->query->get('scope', FeaturedBooking::SCOPE_HOMEPAGE);
        $position = $request->query->getInt('position', 1);

        if (!in_array($scope, [FeaturedBooking::SCOPE_HOMEPAGE, FeaturedBooking::SCOPE_GAME], true)) {
            return new JsonResponse(['error' => 'Scope invalide.'], 400);
        }
        if ($position < 1 || $position > 5) {
            return new JsonResponse(['error' => 'Position invalide.'], 400);
        }

        // For game scope, always use the server's own gameCategory
        $gc = null;
        if ($scope === FeaturedBooking::SCOPE_GAME) {
            $gc = $server->getGameCategory();
            if (!$gc) {
                return new JsonResponse(['error' => 'Ce serveur n\'a pas de categorie de jeu.'], 400);
            }
        }

        $startRange = new \DateTime('today 00:00');
        $endRange = (clone $startRange)->modify('+30 days');

        $availability = $this->bookingRepo->getPositionAvailabilityForRange($scope, $startRange, $endRange, $gc);

        $slotTaken = [];
        foreach ($availability as $slotKey => $positions) {
            $slotTaken[$slotKey] = isset($positions[$position]);
        }

        $serverBookedSlots = [];
        $bookings = $this->bookingRepo->findByServerScoped($server, $scope, $gc);
        $current = clone $startRange;
        while ($current < $endRange) {
            $slotEnd = (clone $current)->modify('+12 hours');
            $key = $current->format('Y-m-d H:i');

            foreach ($bookings as $b) {
                if ($b->getPosition() === $position && $b->getStartsAt() < $slotEnd && $b->getEndsAt() > $current) {
                    $serverBookedSlots[] = $key;
                    break;
                }
            }

            $current = $slotEnd;
        }

        $cost = $this->premiumService->getPositionCost($scope, $position);

        return new JsonResponse([
            'slot_taken' => $slotTaken,
            'server_booked_slots' => $serverBookedSlots,
            'cost_per_12h' => $cost,
        ]);
    }

    #[Route('/serveur/{id}/boost/book', name: 'featured_booking_book', methods: ['POST'])]
    public function book(Server $server, Request $request): Response
    {
        if (!$this->canAccessBoost($server)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('featured_booking', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('featured_booking', ['id' => $server->getId()]);
        }

        $scope = $request->request->get('scope', FeaturedBooking::SCOPE_HOMEPAGE);
        $position = $request->request->getInt('position', 1);
        $startsAtStr = $request->request->get('startsAt', '');
        $duration = $request->request->getInt('duration', 12);

        if (!in_array($scope, [FeaturedBooking::SCOPE_HOMEPAGE, FeaturedBooking::SCOPE_GAME], true)) {
            $this->addFlash('danger', 'Scope invalide.');
            return $this->redirectToRoute('featured_booking', ['id' => $server->getId()]);
        }
        if ($position < 1 || $position > 5) {
            $this->addFlash('danger', 'Position invalide.');
            return $this->redirectToRoute('featured_booking', ['id' => $server->getId()]);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $startsAtStr)) {
            $this->addFlash('danger', 'Date invalide.');
            return $this->redirectToRoute('featured_booking', ['id' => $server->getId()]);
        }
        if (!in_array($duration, [12, 24, 36, 48], true)) {
            $this->addFlash('danger', 'Duree invalide.');
            return $this->redirectToRoute('featured_booking', ['id' => $server->getId()]);
        }

        // For game scope, always use the server's own gameCategory
        $gc = null;
        if ($scope === FeaturedBooking::SCOPE_GAME) {
            $gc = $server->getGameCategory();
            if (!$gc) {
                $this->addFlash('danger', 'Ce serveur n\'a pas de categorie de jeu.');
                return $this->redirectToRoute('featured_booking', ['id' => $server->getId()]);
            }
        }

        try {
            $startsAt = new \DateTime($startsAtStr);
        } catch (\Exception) {
            $this->addFlash('danger', 'Format de date invalide.');
            return $this->redirectToRoute('featured_booking', ['id' => $server->getId()]);
        }
        $user = $this->getUser();

        $cost = $this->premiumService->calculatePositionBookingCost($scope, $position, $duration);
        if (!$server->hasEnoughBoostTokens($cost)) {
            $this->addFlash('danger', 'NexBoost insuffisants sur le serveur. Deposez des NexBoost depuis la page de gestion.');
            return $this->redirectToRoute('featured_booking', ['id' => $server->getId()]);
        }

        $result = $this->premiumService->bookPosition($server, $user, $scope, $position, $startsAt, $duration, $gc);
        if ($result) {
            $endsAt = (clone $startsAt)->modify("+{$duration} hours");
            $scopeLabel = $scope === FeaturedBooking::SCOPE_HOMEPAGE ? 'Page d\'accueil' : $gc->getName();
            $this->addFlash('success', 'Position #' . $position . ' reservee (' . $scopeLabel . ') du ' . $startsAt->format('d/m/Y H:i') . ' au ' . $endsAt->format('d/m/Y H:i') . ' !');
        } else {
            $this->addFlash('danger', 'Reservation impossible. La position est peut-etre deja prise.');
        }

        return $this->redirectToRoute('featured_booking', ['id' => $server->getId()]);
    }
}
