<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Server;
use App\Entity\ServerDailyStat;
use App\Repository\CommentRepository;
use App\Repository\ServerDailyStatRepository;
use App\Repository\ServerRepository;
use App\Service\PremiumService;
use App\Service\StatusService;
use App\Service\TwitchService;
use App\Service\VoteService;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PublicServerController extends AbstractController
{
    public function __construct(
        private VoteService $voteService,
        private StatusService $statusService,
        private EntityManagerInterface $em,
        private WebhookService $webhookService,
        private TwitchService $twitchService,
        private PremiumService $premiumService,
        private ServerDailyStatRepository $dailyStatRepo,
    ) {
    }

    #[Route('/serveur/{slug}', name: 'server_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], priority: -1)]
    public function show(string $slug, ServerRepository $repo, CommentRepository $commentRepo): Response
    {
        $server = $repo->findOneBy(['slug' => $slug, 'isActive' => true, 'isApproved' => true]);
        if (!$server) {
            throw $this->createNotFoundException('Serveur introuvable.');
        }

        // Increment click count
        $server->incrementClickCount();

        // Track daily page views
        $today = new \DateTimeImmutable('today');
        $todayStat = $this->dailyStatRepo->findByServerAndDate($server, $today);
        if (!$todayStat) {
            $todayStat = new ServerDailyStat();
            $todayStat->setServer($server)->setStatDate($today);
            $this->em->persist($todayStat);
        }
        $todayStat->incrementPageViews();

        $this->em->flush();

        $similarServers = [];
        if ($server->getGameCategory()) {
            $similarServers = $repo->findByGameCategory($server->getGameCategory(), 5);
            $similarServers = array_filter($similarServers, fn(Server $s) => $s->getId() !== $server->getId());
            $similarServers = array_slice($similarServers, 0, 4);
        }

        $comments = $commentRepo->findVisibleByServer($server);
        $commentCount = $commentRepo->countVisibleByServer($server);

        $hasTwitchLive = false;
        if ($server->getTwitchChannel()) {
            $hasTwitchLive = $this->premiumService->hasTwitchLiveActive($server);
        }

        return $this->render('server/show.html.twig', [
            'server' => $server,
            'similarServers' => $similarServers,
            'comments' => $comments,
            'commentCount' => $commentCount,
            'has_twitch_live' => $hasTwitchLive,
        ]);
    }

    #[Route('/serveur/{slug}/comment', name: 'server_comment_post', methods: ['POST'], requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    public function postComment(string $slug, Request $request, ServerRepository $repo): Response
    {
        $server = $repo->findOneBy(['slug' => $slug, 'isActive' => true, 'isApproved' => true]);
        if (!$server) {
            throw $this->createNotFoundException('Serveur introuvable.');
        }

        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez etre connecte pour commenter.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        if (!$this->isCsrfTokenValid('comment_' . $server->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $content = trim((string) $request->request->get('content'));
        if (mb_strlen($content) < 3 || mb_strlen($content) > 2000) {
            $this->addFlash('error', 'Le commentaire doit contenir entre 3 et 2000 caracteres.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $comment = new Comment();
        $comment->setServer($server);
        $comment->setAuthor($user);
        $comment->setContent($content);

        $this->em->persist($comment);
        $this->em->flush();

        $this->webhookService->dispatch('comment.created', [
            'title' => 'Nouveau commentaire',
            'fields' => [
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Auteur', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'Contenu', 'value' => mb_substr($content, 0, 200), 'inline' => false],
            ],
        ]);

        $this->addFlash('success', 'Votre commentaire a ete publie.');
        return $this->redirect($this->generateUrl('server_show', ['slug' => $slug]) . '#comments');
    }

    #[Route('/serveur/{slug}/comment/{id}/flag', name: 'server_comment_flag', methods: ['POST'], requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    public function flagComment(string $slug, int $id, Request $request, ServerRepository $repo, CommentRepository $commentRepo): Response
    {
        $server = $repo->findOneBy(['slug' => $slug, 'isActive' => true, 'isApproved' => true]);
        if (!$server) {
            throw $this->createNotFoundException('Serveur introuvable.');
        }

        $user = $this->getUser();
        if (!$user || $server->getOwner() !== $user) {
            $this->addFlash('error', 'Seul le proprietaire du serveur peut signaler un commentaire.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $comment = $commentRepo->find($id);
        if (!$comment || $comment->getServer() !== $server) {
            throw $this->createNotFoundException('Commentaire introuvable.');
        }

        if (!$this->isCsrfTokenValid('flag_comment_' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $reason = trim((string) $request->request->get('reason'));

        $comment->setIsFlagged(true);
        $comment->setFlaggedBy($user);
        $comment->setFlaggedAt(new \DateTimeImmutable());
        $comment->setFlagReason($reason ?: null);

        $this->em->flush();

        $this->webhookService->dispatch('comment.flagged', [
            'title' => 'Commentaire signale',
            'fields' => [
                ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                ['name' => 'Signale par', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'Auteur du commentaire', 'value' => $comment->getAuthor()->getUsername(), 'inline' => true],
                ['name' => 'Raison', 'value' => $reason ?: 'Non specifiee', 'inline' => false],
            ],
        ]);

        $this->addFlash('success', 'Le commentaire a ete signale aux moderateurs.');
        return $this->redirect($this->generateUrl('server_show', ['slug' => $slug]) . '#comments');
    }

    #[Route('/serveur/{slug}/status', name: 'server_status', methods: ['GET'], requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    public function status(string $slug, ServerRepository $repo): JsonResponse
    {
        $server = $repo->findOneBy(['slug' => $slug]);
        if (!$server) {
            return $this->json(['online' => null], 404);
        }

        // Allow owner to check status even if not yet approved; otherwise require active+approved
        $user = $this->getUser();
        $isOwner = $user && $server->getOwner() === $user;
        if (!$isOwner && (!$server->isActive() || !$server->isApproved())) {
            return $this->json(['online' => null], 404);
        }

        if (!$server->isStatusCheckEnabled() || !$server->getIp() || !$server->getPort()) {
            return $this->json(['online' => null]);
        }

        $online = $this->statusService->check($server->getIp(), $server->getPort());

        return $this->json(['online' => $online]);
    }

    #[Route('/api/twitch-info/{id}', name: 'api_twitch_info', methods: ['GET'])]
    public function twitchInfo(int $id, ServerRepository $repo): JsonResponse
    {
        $server = $repo->find($id);
        if (!$server || !$server->getTwitchChannel()) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $data = $this->twitchService->getChannelData($server->getTwitchChannel());
        if (!$data) {
            return $this->json(['error' => 'Twitch API unavailable'], 503);
        }

        return $this->json($data);
    }

    #[Route('/serveur/{slug}/vote-status', name: 'server_vote_status', methods: ['GET'], requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    public function voteStatus(string $slug, Request $request, ServerRepository $repo): JsonResponse
    {
        $server = $repo->findOneBy(['slug' => $slug, 'isActive' => true, 'isApproved' => true]);
        if (!$server) {
            return $this->json(['error' => 'Serveur introuvable.'], 404);
        }

        $ip = $request->getClientIp();
        $check = $this->voteService->canVote($server, $ip);

        return $this->json([
            'can_vote' => $check['allowed'],
            'cooldown' => $check['cooldown'],
            'monthly_votes' => $server->getMonthlyVotes(),
            'total_votes' => $server->getTotalVotes(),
        ]);
    }

    #[Route('/serveur/{slug}/don', name: 'server_donate', methods: ['POST'], requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function donate(string $slug, Request $request, ServerRepository $repo): Response
    {
        $server = $repo->findOneBy(['slug' => $slug, 'isActive' => true, 'isApproved' => true]);
        if (!$server) {
            throw $this->createNotFoundException('Serveur introuvable.');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('donate_' . $server->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $nexbits = max(0, (int) $request->request->get('nexbits', 0));
        $nexboost = max(0, (int) $request->request->get('nexboost', 0));

        if ($nexbits === 0 && $nexboost === 0) {
            $this->addFlash('error', 'Vous devez donner au moins 1 NexBit ou 1 NexBoost.');
            return $this->redirectToRoute('server_show', ['slug' => $slug]);
        }

        $result = $this->premiumService->donateToServer($user, $server, $nexbits, $nexboost);

        if (!$result['success']) {
            $this->addFlash('error', $result['error']);
        } else {
            $parts = [];
            if ($nexbits > 0) {
                $parts[] = $nexbits . ' NexBits';
            }
            if ($nexboost > 0) {
                $parts[] = $nexboost . ' NexBoost';
            }
            $this->addFlash('success', 'Don de ' . implode(' et ', $parts) . ' envoye a ' . $server->getName() . '. Merci !');
        }

        return $this->redirectToRoute('server_show', ['slug' => $slug]);
    }
}
