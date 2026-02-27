<?php

namespace App\Controller\Admin;

use App\Entity\AdminWebhook;
use App\Repository\AdminWebhookRepository;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/webhooks', name: 'admin_webhooks_')]
#[IsGranted('webhooks.manage')]
class WebhookController extends AbstractController
{
    private const EVENT_TYPES = [
        'Serveurs' => [
            'server.created' => 'Nouveau serveur cree',
            'server.approved' => 'Serveur approuve',
            'server.deleted' => 'Serveur supprime',
        ],
        'Utilisateurs' => [
            'user.registered' => 'Inscription formulaire',
            'user.oauth_created' => 'Inscription OAuth',
        ],
        'Votes' => [
            'vote.cast' => 'Vote enregistre',
        ],
        'Paiements' => [
            'payment.completed' => 'Paiement complete',
            'payment.refunded' => 'Remboursement PayPal',
        ],
        'Premium' => [
            'premium.feature_unlocked' => 'Fonctionnalite debloquee',
            'premium.boost_booked' => 'Boost reserve',
            'admin.tokens_credited' => 'Tokens credites par admin',
        ],
        'Recrutement' => [
            'recruitment.submitted' => 'Annonce soumise',
            'recruitment.approved' => 'Annonce approuvee',
            'recruitment.application' => 'Nouvelle candidature',
        ],
        'Commentaires' => [
            'comment.created' => 'Nouveau commentaire',
            'comment.flagged' => 'Commentaire signale',
            'comment.flag_dismissed' => 'Signalement rejete par admin',
            'comment.deleted' => 'Commentaire supprime par admin',
        ],
        'Recrutement — Moderation' => [
            'recruitment.revision_requested' => 'Revision demandee',
            'recruitment.rejected' => 'Annonce rejetee',
        ],
        'Recrutement — Candidatures' => [
            'recruitment.application_accepted' => 'Candidature acceptee',
            'recruitment.application_rejected' => 'Candidature refusee',
        ],
    ];

    private const CATEGORY_ICONS = [
        'Serveurs' => 'fas fa-server',
        'Utilisateurs' => 'fas fa-users',
        'Votes' => 'fas fa-vote-yea',
        'Paiements' => 'fas fa-credit-card',
        'Premium' => 'fas fa-crown',
        'Recrutement' => 'fas fa-briefcase',
        'Commentaires' => 'fas fa-comments',
        'Recrutement — Moderation' => 'fas fa-gavel',
        'Recrutement — Candidatures' => 'fas fa-user-check',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private AdminWebhookRepository $webhookRepo,
        private WebhookService $webhookService,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(): Response
    {
        $this->seedMissingEventTypes();

        return $this->render('admin/webhooks/list.html.twig', [
            'grouped' => $this->webhookRepo->findAllGroupedByCategory(),
            'category_icons' => self::CATEGORY_ICONS,
        ]);
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('webhooks_save', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_webhooks_list');
        }

        $urls = $request->request->all('webhook_url');
        $enabled = $request->request->all('webhook_enabled');

        foreach ($urls as $id => $url) {
            $webhook = $this->webhookRepo->find((int) $id);
            if (!$webhook) {
                continue;
            }

            $url = trim($url);
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $webhook->setWebhookUrl($url !== '' ? $url : null);
            $webhook->setIsEnabled(isset($enabled[$id]));
        }

        $this->em->flush();

        $this->addFlash('success', 'Webhooks mis a jour.');
        return $this->redirectToRoute('admin_webhooks_list');
    }

    #[Route('/{id}/test', name: 'test', methods: ['POST'])]
    public function test(AdminWebhook $webhook, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('webhook_test_' . $webhook->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_webhooks_list');
        }

        if (!$webhook->getWebhookUrl()) {
            $this->addFlash('error', 'Aucune URL configuree pour ce webhook.');
            return $this->redirectToRoute('admin_webhooks_list');
        }

        $success = $this->webhookService->sendTestWebhook($webhook);

        if ($success) {
            $this->addFlash('success', 'Webhook test envoye avec succes pour "' . $webhook->getLabel() . '".');
        } else {
            $this->addFlash('error', 'Echec de l\'envoi du webhook test. Verifiez l\'URL.');
        }

        return $this->redirectToRoute('admin_webhooks_list');
    }

    private function seedMissingEventTypes(): void
    {
        $existing = $this->webhookRepo->findAll();
        $existingTypes = array_map(fn(AdminWebhook $w) => $w->getEventType(), $existing);
        $needsFlush = false;

        foreach (self::EVENT_TYPES as $category => $events) {
            foreach ($events as $eventType => $label) {
                if (!in_array($eventType, $existingTypes, true)) {
                    $webhook = new AdminWebhook();
                    $webhook->setEventType($eventType);
                    $webhook->setLabel($label);
                    $webhook->setCategory($category);
                    $this->em->persist($webhook);
                    $needsFlush = true;
                }
            }
        }

        if ($needsFlush) {
            $this->em->flush();
        }
    }
}
