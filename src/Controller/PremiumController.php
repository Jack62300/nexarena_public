<?php

namespace App\Controller;

use App\Entity\Server;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\PremiumPlanRepository;
use App\Repository\ServerRepository;
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
        private ServerRepository $serverRepo,
        private LoggerInterface $logger,
        private WebhookService $webhookService,
    ) {}


    #[Route('/premium', name: 'premium_index')]
    public function index(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $userServers = $user ? $this->serverRepo->findByOwner($user) : [];

        return $this->render('premium/index.html.twig', [
            'defaultPlans' => $this->planRepo->findActiveByType('default'),
            'customPlans' => $this->planRepo->findActiveByType('custom'),
            'paypal_client_id' => $this->paypal->getClientId(),
            'paypal_currency' => $this->paypal->getCurrency(),
            'is_sandbox' => $this->paypal->isSandbox(),
            'userServers' => $userServers,
        ]);
    }

    #[Route('/premium/buy-nexbits', name: 'premium_buy_nexbits', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function buyWithNexbits(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $planId = (int) ($data['planId'] ?? 0);
        $serverId = isset($data['serverId']) ? (int) $data['serverId'] : null;

        $plan = $this->planRepo->find($planId);
        if (!$plan || !$plan->isActive()) {
            return new JsonResponse(['error' => 'Plan introuvable ou inactif.'], 400);
        }

        if ($plan->getNexbitsPrice() <= 0) {
            return new JsonResponse(['error' => 'Ce plan ne peut pas etre achete en NexBits.'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        // Server source: deduct from server balance
        if ($serverId !== null) {
            $server = $this->serverRepo->find($serverId);
            if (!$server || $server->getOwner() !== $user) {
                return new JsonResponse(['error' => 'Serveur introuvable ou non autorise.'], 403);
            }

            if (!$server->hasEnoughTokens($plan->getNexbitsPrice())) {
                return new JsonResponse(['error' => 'NexBits du serveur insuffisants. Solde : ' . $server->getTokenBalance() . ' NexBits, requis : ' . $plan->getNexbitsPrice() . '.'], 400);
            }

            $success = $this->premiumService->purchaseWithServerNexbits($user, $server, $plan);
            if (!$success) {
                return new JsonResponse(['error' => 'Erreur lors de l\'achat. Veuillez reessayer.'], 500);
            }

            $this->webhookService->dispatch('payment.completed', [
                'title' => 'Achat Premium (NexBits serveur)',
                'fields' => [
                    ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                    ['name' => 'Serveur', 'value' => $server->getName(), 'inline' => true],
                    ['name' => 'Plan', 'value' => $plan->getName(), 'inline' => true],
                    ['name' => 'Cout', 'value' => $plan->getNexbitsPrice() . ' NexBits', 'inline' => true],
                ],
            ]);

            return new JsonResponse(['success' => true, 'source' => 'server']);
        }

        // Personal source: deduct from user balance
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

        return new JsonResponse(['success' => true, 'source' => 'personal']);
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
        try {
            $data = json_decode($request->getContent(), true);
            $orderId = $data['orderId'] ?? '';
            $planId = (int) ($data['planId'] ?? 0);

            $this->logger->info('Premium: captureOrder request.', ['orderId' => $orderId, 'planId' => $planId]);

            if (!$orderId || !$planId) {
                $this->logger->warning('Premium: captureOrder missing data.', ['orderId' => $orderId, 'planId' => $planId]);
                return new JsonResponse(['error' => 'Donnees manquantes.'], 400);
            }

            // Idempotency: don't double-credit
            if ($this->premiumService->isOrderAlreadyCaptured($orderId)) {
                $this->logger->info('Premium: Order already captured.', ['orderId' => $orderId]);
                return new JsonResponse(['success' => true, 'message' => 'Deja traite.']);
            }

            $plan = $this->planRepo->find($planId);
            if (!$plan) {
                $this->logger->error('Premium: Plan not found.', ['planId' => $planId]);
                return new JsonResponse(['error' => 'Plan introuvable.'], 400);
            }

            $this->logger->info('Premium: Calling PayPal captureOrder.', ['orderId' => $orderId]);
            $capture = $this->paypal->captureOrder($orderId);

            if (!$capture) {
                $this->logger->error('Premium: PayPal captureOrder returned null.', ['orderId' => $orderId]);
                return new JsonResponse(['error' => 'Erreur lors de la capture PayPal.'], 500);
            }

            $status = $capture['status'] ?? '';
            $this->logger->info('Premium: PayPal capture status.', ['orderId' => $orderId, 'status' => $status]);

            if (!in_array($status, [Transaction::PAYPAL_STATUS_COMPLETED, Transaction::PAYPAL_STATUS_PENDING], true)) {
                $this->logger->warning('Premium: Payment not completed.', ['orderId' => $orderId, 'status' => $status]);
                return new JsonResponse(['error' => 'Paiement non complete. Statut: ' . $status], 400);
            }

            /** @var User $user */
            $user = $this->getUser();
            $this->logger->info('Premium: Processing payment.', ['userId' => $user->getId(), 'orderId' => $orderId, 'status' => $status]);
            $this->premiumService->creditTokensFromPurchase($user, $plan, $orderId, $status);

            if ($status === Transaction::PAYPAL_STATUS_COMPLETED) {
                $this->webhookService->dispatch('payment.completed', [
                    'title' => 'Paiement complete',
                    'fields' => [
                        ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                        ['name' => 'Plan', 'value' => $plan->getName(), 'inline' => true],
                        ['name' => 'Montant', 'value' => $plan->getPrice() . ' ' . $plan->getCurrency(), 'inline' => true],
                    ],
                ]);
                $this->logger->info('Premium: Payment completed successfully.', ['orderId' => $orderId]);
                return new JsonResponse(['success' => true]);
            }

            // PENDING (virement bancaire)
            $this->logger->info('Premium: Payment pending (bank transfer).', ['orderId' => $orderId]);
            return new JsonResponse(['success' => true, 'pending' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('Premium: Exception in captureOrder.', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new JsonResponse(['error' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
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

        // Virement bancaire confirmé → créditer les tokens
        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource = $event['resource'] ?? [];
            $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

            if ($paypalOrderId) {
                $pendingTx = $this->transactionRepo->findPendingByPaypalOrderId($paypalOrderId);
                if ($pendingTx) {
                    $completed = $this->premiumService->completePendingTransaction($pendingTx);
                    if ($completed && $pendingTx->getUser()) {
                        $this->webhookService->dispatch('payment.completed', [
                            'title' => 'Virement bancaire confirme',
                            'fields' => [
                                ['name' => 'Utilisateur', 'value' => $pendingTx->getUser()->getUsername(), 'inline' => true],
                                ['name' => 'Plan', 'value' => $pendingTx->getPlan()?->getName() ?? '-', 'inline' => true],
                                ['name' => 'Montant', 'value' => $pendingTx->getAmount() . ' ' . $pendingTx->getCurrency(), 'inline' => true],
                            ],
                        ]);
                    }
                }
            }
        }

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
