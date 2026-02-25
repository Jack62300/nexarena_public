<?php

namespace App\Controller;

use App\Entity\Server;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\PremiumPlanRepository;
use App\Repository\ServerRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\CryptoPayService;
use App\Service\PayPalService;
use App\Service\PremiumService;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PremiumController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PayPalService $paypal,
        private CryptoPayService $crypto,
        private PremiumService $premiumService,
        private PremiumPlanRepository $planRepo,
        private TransactionRepository $transactionRepo,
        private ServerRepository $serverRepo,
        private UserRepository $userRepo,
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
            'crypto_enabled' => $this->crypto->isEnabled(),
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

    // =============================================
    // CRYPTO.COM PAY
    // =============================================

    #[Route('/premium/crypto/create', name: 'premium_create_crypto_order', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createCryptoOrder(Request $request): JsonResponse
    {
        if (!$this->crypto->isEnabled()) {
            return new JsonResponse(['error' => 'Paiement crypto non active.'], 400);
        }

        $data   = json_decode($request->getContent(), true);
        $planId = (int) ($data['planId'] ?? 0);

        $plan = $this->planRepo->find($planId);
        if (!$plan || !$plan->isActive() || (float) $plan->getPrice() <= 0) {
            return new JsonResponse(['error' => 'Plan introuvable, inactif ou sans prix.'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        $amountCents = (int) round((float) $plan->getPrice() * 100);
        $orderId     = 'nx-plan-' . $plan->getId() . '-u-' . $user->getId() . '-' . time();

        $returnUrl = $this->generateUrl('premium_crypto_return', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->generateUrl('premium_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $payment = $this->crypto->createPayment(
            $amountCents,
            $plan->getCurrency(),
            'Nexarena - ' . $plan->getName(),
            $orderId,
            $plan->getId(),
            $user->getId(),
            $returnUrl,
            $cancelUrl,
        );

        if (!$payment || !isset($payment['id'], $payment['payment_url'])) {
            $this->logger->error('Premium: CryptoPay createPayment returned null.', ['planId' => $planId]);
            return new JsonResponse(['error' => 'Erreur Crypto.com Pay. Veuillez reessayer.'], 500);
        }

        // Store in session for the return flow
        $request->getSession()->set('crypto_payment_id', $payment['id']);
        $request->getSession()->set('crypto_plan_id', $plan->getId());

        $this->logger->info('Premium: CryptoPay payment created.', [
            'paymentId' => $payment['id'],
            'planId'    => $planId,
        ]);

        return new JsonResponse([
            'paymentId'  => $payment['id'],
            'paymentUrl' => $payment['payment_url'],
        ]);
    }

    #[Route('/premium/crypto/return', name: 'premium_crypto_return')]
    #[IsGranted('ROLE_USER')]
    public function cryptoReturn(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // payment_id can come from query string (Crypto.com redirect) or session
        $paymentId = $request->query->get('payment_id')
            ?? $request->getSession()->get('crypto_payment_id');
        $planId = (int) ($request->getSession()->get('crypto_plan_id', 0));

        if (!$paymentId) {
            $this->addFlash('danger', 'Paiement invalide. Veuillez reessayer.');
            return $this->redirectToRoute('premium_index');
        }

        // Idempotency check
        if ($this->premiumService->isCryptoPaymentAlreadyCaptured($paymentId)) {
            $this->cleanCryptoSession($request);
            return $this->redirectToRoute('premium_success');
        }

        $plan = $planId ? $this->planRepo->find($planId) : null;
        if (!$plan) {
            $this->addFlash('danger', 'Plan introuvable. Contactez le support si votre paiement a ete debite.');
            $this->cleanCryptoSession($request);
            return $this->redirectToRoute('premium_index');
        }

        $payment = $this->crypto->getPayment($paymentId);
        if (!$payment) {
            $this->addFlash('danger', 'Impossible de verifier le paiement. Contactez le support avec votre Payment ID : ' . htmlspecialchars($paymentId));
            $this->cleanCryptoSession($request);
            return $this->redirectToRoute('premium_index');
        }

        $status = $payment['status'] ?? '';

        $this->logger->info('Premium: CryptoPay return.', [
            'paymentId' => $paymentId,
            'status'    => $status,
            'planId'    => $planId,
        ]);

        if ($status === Transaction::CRYPTO_STATUS_CAPTURED) {
            $this->premiumService->creditTokensFromCryptoPurchase($user, $plan, $paymentId, $status);
            $this->webhookService->dispatch('payment.completed', [
                'title' => 'Paiement Crypto.com Pay',
                'fields' => [
                    ['name' => 'Utilisateur', 'value' => $user->getUsername(), 'inline' => true],
                    ['name' => 'Plan', 'value' => $plan->getName(), 'inline' => true],
                    ['name' => 'Montant', 'value' => $plan->getPrice() . ' ' . $plan->getCurrency(), 'inline' => true],
                    ['name' => 'Payment ID', 'value' => $paymentId, 'inline' => false],
                ],
            ]);
            $this->cleanCryptoSession($request);
            return $this->redirectToRoute('premium_success');
        }

        if (in_array($status, [Transaction::CRYPTO_STATUS_PENDING, 'processing'], true)) {
            // Save pending transaction; webhook will complete it
            $this->premiumService->creditTokensFromCryptoPurchase($user, $plan, $paymentId, $status);
            $this->cleanCryptoSession($request);
            return $this->redirectToRoute('premium_success', ['crypto_pending' => '1']);
        }

        // cancelled, expired or unknown
        $this->cleanCryptoSession($request);
        $this->addFlash('danger', 'Paiement annule ou expire (statut : ' . htmlspecialchars($status) . ').');
        return $this->redirectToRoute('premium_index');
    }

    #[Route('/premium/crypto/webhook', name: 'premium_crypto_webhook', methods: ['POST'])]
    public function cryptoWebhook(Request $request): JsonResponse
    {
        $body      = $request->getContent();
        $signature = $request->headers->get('X-Signature', '');

        if (!$this->crypto->verifyWebhookSignature($body, $signature)) {
            $this->logger->warning('CryptoPay webhook: signature invalide.');
            return new JsonResponse(['error' => 'Signature invalide.'], 401);
        }

        $event     = json_decode($body, true);
        $eventType = $event['type'] ?? '';

        if ($eventType === 'payment.captured') {
            $paymentId = $event['data']['id'] ?? null;
            if (!$paymentId) {
                return new JsonResponse(['status' => 'ok']);
            }

            // If there's a pending transaction, complete it
            $pendingTx = $this->transactionRepo->findPendingByCryptoPaymentId($paymentId);
            if ($pendingTx) {
                $completed = $this->premiumService->completePendingCryptoTransaction($pendingTx);
                if ($completed && $pendingTx->getUser()) {
                    $this->webhookService->dispatch('payment.completed', [
                        'title' => 'Crypto.com Pay confirme (webhook)',
                        'fields' => [
                            ['name' => 'Utilisateur', 'value' => $pendingTx->getUser()->getUsername(), 'inline' => true],
                            ['name' => 'Plan', 'value' => $pendingTx->getPlan()?->getName() ?? '-', 'inline' => true],
                        ],
                    ]);
                }
                return new JsonResponse(['status' => 'ok']);
            }

            // No transaction found yet (user never hit the return URL) → create it now
            if (!$this->premiumService->isCryptoPaymentAlreadyCaptured($paymentId)) {
                $planId = (int) ($event['data']['metadata']['plan_id'] ?? 0);
                $userId = (int) ($event['data']['metadata']['user_id'] ?? 0);

                $plan = $planId ? $this->planRepo->find($planId) : null;
                $user = $userId ? $this->userRepo->find($userId) : null;

                if ($plan && $user) {
                    $this->premiumService->creditTokensFromCryptoPurchase(
                        $user,
                        $plan,
                        $paymentId,
                        Transaction::CRYPTO_STATUS_CAPTURED
                    );
                    $this->logger->info('CryptoPay webhook: paiement credite via webhook (retour manque).', [
                        'paymentId' => $paymentId,
                        'userId'    => $userId,
                        'planId'    => $planId,
                    ]);
                }
            }
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function cleanCryptoSession(Request $request): void
    {
        $request->getSession()->remove('crypto_payment_id');
        $request->getSession()->remove('crypto_plan_id');
    }

    #[Route('/premium/succes', name: 'premium_success')]
    #[IsGranted('ROLE_USER')]
    public function success(): Response
    {
        return $this->render('premium/success.html.twig');
    }
}
