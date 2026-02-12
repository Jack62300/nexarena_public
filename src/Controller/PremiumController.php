<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\PremiumPlanRepository;
use App\Repository\TransactionRepository;
use App\Service\PayPalService;
use App\Service\PremiumService;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PremiumController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PayPalService $paypal,
        private PremiumService $premiumService,
        private PremiumPlanRepository $planRepo,
        private TransactionRepository $transactionRepo,
        private LoggerInterface $logger,
        private WebhookService $webhookService,
    ) {
    }

    #[Route('/premium', name: 'premium_index')]
    public function index(): Response
    {
        return $this->render('premium/index.html.twig', [
            'defaultPlans' => $this->planRepo->findActiveByType('default'),
            'customPlans' => $this->planRepo->findActiveByType('custom'),
            'paypal_client_id' => $this->paypal->getClientId(),
            'paypal_currency' => $this->paypal->getCurrency(),
            'is_sandbox' => $this->paypal->isSandbox(),
        ]);
    }

    #[Route('/premium/buy-nexbits', name: 'premium_buy_nexbits', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function buyWithNexbits(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $planId = (int) ($data['planId'] ?? 0);

        $plan = $this->planRepo->find($planId);
        if (!$plan || !$plan->isActive()) {
            return new JsonResponse(['error' => 'Plan introuvable ou inactif.'], 400);
        }

        if ($plan->getNexbitsPrice() <= 0) {
            return new JsonResponse(['error' => 'Ce plan ne peut pas etre achete en NexBits.'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$user->hasEnoughTokens($plan->getNexbitsPrice())) {
            return new JsonResponse(['error' => 'NexBits insuffisants. Vous avez ' . $user->getTokenBalance() . ' NexBits, il en faut ' . $plan->getNexbitsPrice() . '.'], 400);
        }

        $success = $this->premiumService->purchaseWithNexbits($user, $plan);
        if (!$success) {
            return new JsonResponse(['error' => 'Erreur lors de l\'achat. Veuillez reessayer.'], 500);
        }

        $this->webhookService->dispatch('payment.completed', [
            'title' => 'Achat Premium (NexBits)',
            'fields' => [
                ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'Plan', 'value' => $plan->getName(), 'inline' => true],
                ['name' => 'Cout', 'value' => $plan->getNexbitsPrice() . ' NexBits', 'inline' => true],
            ],
        ]);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/premium/create-order', name: 'premium_create_order', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $planId = (int) ($data['planId'] ?? 0);

        $plan = $this->planRepo->find($planId);
        if (!$plan || !$plan->isActive()) {
            return new JsonResponse(['error' => 'Plan introuvable ou inactif.'], 400);
        }

        $this->logger->info('Premium: createOrder request.', ['planId' => $planId, 'price' => $plan->getPrice()]);

        $order = $this->paypal->createOrder(
            $plan->getPrice(),
            $plan->getCurrency(),
            'Nexarena - ' . $plan->getName()
        );

        if (!$order || !isset($order['id'])) {
            $this->logger->error('Premium: PayPal createOrder returned null.', ['planId' => $planId]);
            return new JsonResponse(['error' => 'Erreur PayPal. Veuillez reessayer.'], 500);
        }

        $this->logger->info('Premium: PayPal order created.', ['orderId' => $order['id']]);

        return new JsonResponse(['orderId' => $order['id']]);
    }

    #[Route('/premium/capture-order', name: 'premium_capture_order', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function captureOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $orderId = $data['orderId'] ?? '';
        $planId = (int) ($data['planId'] ?? 0);

        if (!$orderId || !$planId) {
            return new JsonResponse(['error' => 'Donnees manquantes.'], 400);
        }

        // Idempotency: don't double-credit
        if ($this->premiumService->isOrderAlreadyCaptured($orderId)) {
            return new JsonResponse(['success' => true, 'message' => 'Deja traite.']);
        }

        $plan = $this->planRepo->find($planId);
        if (!$plan) {
            return new JsonResponse(['error' => 'Plan introuvable.'], 400);
        }

        $capture = $this->paypal->captureOrder($orderId);
        if (!$capture) {
            return new JsonResponse(['error' => 'Erreur lors de la capture PayPal.'], 500);
        }

        $status = $capture['status'] ?? '';
        if ($status !== 'COMPLETED') {
            return new JsonResponse(['error' => 'Paiement non complete. Statut: ' . $status], 400);
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->premiumService->creditTokensFromPurchase($user, $plan, $orderId, $status);

        $this->webhookService->dispatch('payment.completed', [
            'title' => 'Paiement complete',
            'fields' => [
                ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                ['name' => 'Plan', 'value' => $plan->getName(), 'inline' => true],
                ['name' => 'Montant', 'value' => $plan->getPrice() . ' ' . $plan->getCurrency(), 'inline' => true],
            ],
        ]);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/premium/paypal-webhook', name: 'premium_paypal_webhook', methods: ['POST'])]
    public function paypalWebhook(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $headers = [];
        foreach (['PAYPAL-AUTH-ALGO', 'PAYPAL-CERT-URL', 'PAYPAL-TRANSMISSION-ID', 'PAYPAL-TRANSMISSION-SIG', 'PAYPAL-TRANSMISSION-TIME'] as $h) {
            $headers[$h] = $request->headers->get($h, '');
        }

        if (!$this->paypal->verifyWebhookSignature($headers, $body)) {
            return new JsonResponse(['error' => 'Signature invalide.'], 401);
        }

        $event = json_decode($body, true);
        $eventType = $event['event_type'] ?? '';

        if (in_array($eventType, ['PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED', 'CUSTOMER.DISPUTE.CREATED'], true)) {
            $resource = $event['resource'] ?? [];
            $paypalOrderId = null;

            // Try to extract the order ID from the supplement data or links
            if (isset($resource['supplementary_data']['related_ids']['order_id'])) {
                $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'];
            }

            if ($paypalOrderId) {
                $originalTx = $this->transactionRepo->findByPaypalOrderId($paypalOrderId);
                if ($originalTx && $originalTx->getUser()) {
                    $this->premiumService->processRefund(
                        $originalTx->getUser(),
                        $paypalOrderId,
                        'Webhook PayPal: ' . $eventType
                    );

                    $this->webhookService->dispatch('payment.refunded', [
                        'title' => 'Remboursement PayPal',
                        'fields' => [
                            ['name' => 'Utilisateur', 'value' => $originalTx->getUser()->getUsername(), 'inline' => true],
                            ['name' => 'Evenement', 'value' => $eventType, 'inline' => true],
                            ['name' => 'Order ID', 'value' => $paypalOrderId, 'inline' => true],
                        ],
                    ]);
                }
            }
        }

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/premium/succes', name: 'premium_success')]
    #[IsGranted('ROLE_USER')]
    public function success(): Response
    {
        return $this->render('premium/success.html.twig');
    }
}
