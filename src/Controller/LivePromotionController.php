<?php

namespace App\Controller;

use App\Entity\LivePromotion;
use App\Entity\Transaction;
use App\Repository\LivePromotionRepository;
use App\Repository\ServerRepository;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/promotions', name: 'app_promotions_')]
#[IsGranted('ROLE_USER')]
class LivePromotionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SettingsService $settings,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(LivePromotionRepository $repo): Response
    {
        $promos = $repo->findByUser($this->getUser());

        return $this->render('live_promotion/index.html.twig', [
            'promotions' => $promos,
            'live_promo_enabled' => $this->settings->get('discord_live_promo_enabled', '0') === '1',
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, ServerRepository $serverRepo): Response
    {
        if ($this->settings->get('discord_live_promo_enabled', '0') !== '1') {
            $this->addFlash('error', 'Les promotions live ne sont pas activees.');
            return $this->redirectToRoute('app_promotions_index');
        }

        $user = $this->getUser();
        $servers = $serverRepo->findBy(['owner' => $user, 'isActive' => true]);
        $costPerDay = (int) $this->settings->get('discord_live_promo_cost_per_day', '10');
        $maxDays = (int) $this->settings->get('discord_live_promo_max_days', '30');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('live_promo', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_promotions_new');
            }

            $platform = $request->request->get('platform', '');
            $channelUrl = trim($request->request->get('channel_url', ''));
            $channelName = trim($request->request->get('channel_name', ''));
            $duration = min((int) $request->request->get('duration', 1), $maxDays);
            $serverId = $request->request->get('server_id');

            if (!in_array($platform, ['twitch', 'youtube'], true)) {
                $this->addFlash('error', 'Plateforme invalide.');
                return $this->redirectToRoute('app_promotions_new');
            }

            if (!$channelUrl || !$channelName || $duration < 1) {
                $this->addFlash('error', 'Tous les champs sont requis.');
                return $this->redirectToRoute('app_promotions_new');
            }

            if (!preg_match('#^https?://#', $channelUrl)) {
                $this->addFlash('error', 'L\'URL doit commencer par http:// ou https://.');
                return $this->redirectToRoute('app_promotions_new');
            }

            $totalCost = $costPerDay * $duration;

            $server = null;
            if ($serverId) {
                $server = $serverRepo->find($serverId);
                if ($server && $server->getOwner() !== $user) {
                    $server = null;
                }
            }

            // Deduct from server if linked, otherwise from user
            if ($server) {
                if ($server->getTokenBalance() < $totalCost) {
                    $this->addFlash('error', "NexBits insuffisants sur le serveur. Cout: $totalCost NexBits.");
                    return $this->redirectToRoute('app_promotions_new');
                }
                $server->removeTokens($totalCost);
            } else {
                if ($user->getTokenBalance() < $totalCost) {
                    $this->addFlash('error', "Solde insuffisant. Cout: $totalCost NexBits.");
                    return $this->redirectToRoute('app_promotions_new');
                }
                $user->removeTokens($totalCost);
            }

            $transaction = new Transaction();
            $transaction->setUser($user);
            if ($server) {
                $transaction->setServer($server);
            }
            $transaction->setType(Transaction::TYPE_SPEND);
            $transaction->setTokensAmount(-$totalCost);
            $transaction->setDescription("Promotion live {$channelName} ({$duration}j)");

            $promo = new LivePromotion();
            $promo->setUser($user);
            $promo->setServer($server);
            $promo->setPlatform($platform);
            $promo->setChannelUrl($channelUrl);
            $promo->setChannelName($channelName);
            $promo->setStartDate(new \DateTimeImmutable());
            $promo->setEndDate(new \DateTimeImmutable("+{$duration} days"));
            $promo->setCost($totalCost);

            $this->em->persist($transaction);
            $this->em->persist($promo);
            $this->em->flush();

            $this->addFlash('success', "Promotion live activee pour {$duration} jours !");
            return $this->redirectToRoute('app_promotions_index');
        }

        return $this->render('live_promotion/form.html.twig', [
            'servers' => $servers,
            'cost_per_day' => $costPerDay,
            'max_days' => $maxDays,
            'balance' => $user->getTokenBalance(),
        ]);
    }
}
