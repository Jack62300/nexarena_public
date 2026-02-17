<?php

namespace App\Controller\Admin;

use App\Entity\PremiumPlan;
use App\Repository\PremiumPlanRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/premium/plans', name: 'admin_premium_plans_')]
#[IsGranted('ROLE_MANAGER')]
class PremiumPlanController extends AbstractController
{
    private const UPLOAD_DIR = 'uploads/plans';
    private const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/webp'];

    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
        private LoggerInterface $logger,
        private string $projectDir,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(PremiumPlanRepository $repo): Response
    {
        return $this->render('admin/premium/plans/list.html.twig', [
            'plans' => $repo->findAllForAdmin(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('premium_plan_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_premium_plans_new');
            }

            $plan = new PremiumPlan();
            if (!$this->handleForm($plan, $request)) {
                return $this->render('admin/premium/plans/form.html.twig', ['plan' => null]);
            }

            $this->em->persist($plan);
            $this->em->flush();

            $this->addFlash('success', 'Plan premium cree avec succes.');
            return $this->redirectToRoute('admin_premium_plans_list');
        }

        return $this->render('admin/premium/plans/form.html.twig', [
            'plan' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(PremiumPlan $plan, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('premium_plan_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_premium_plans_edit', ['id' => $plan->getId()]);
            }

            if (!$this->handleForm($plan, $request)) {
                return $this->render('admin/premium/plans/form.html.twig', ['plan' => $plan]);
            }
            $this->em->flush();

            $this->addFlash('success', 'Plan premium modifie avec succes.');
            return $this->redirectToRoute('admin_premium_plans_list');
        }

        return $this->render('admin/premium/plans/form.html.twig', [
            'plan' => $plan,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('settings.edit')]
    public function delete(PremiumPlan $plan, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $plan->getId(), $request->request->get('_token'))) {
            if ($plan->getIconFileName()) {
                $path = $this->projectDir . '/public/' . self::UPLOAD_DIR . '/' . basename($plan->getIconFileName());
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            $this->em->remove($plan);
            $this->em->flush();
            $this->addFlash('success', 'Plan premium supprime.');
        }

        return $this->redirectToRoute('admin_premium_plans_list');
    }

    private function handleForm(PremiumPlan $plan, Request $request): bool
    {
        $name = $request->request->get('name', '');
        $plan->setName($name);
        $plan->setSlug($this->slugService->slugify($name));
        $plan->setDescription($request->request->get('description', ''));
        $price = $request->request->get('price', '0');
        $nexbitsPrice = (int) $request->request->get('nexbits_price', 0);
        $plan->setPrice($price);
        $plan->setCurrency($request->request->get('currency', 'EUR'));
        $plan->setTokensGiven((int) $request->request->get('tokens_given', 0));
        $plan->setBoostTokensGiven((int) $request->request->get('boost_tokens_given', 0));
        $plan->setNexbitsPrice($nexbitsPrice);
        $planType = $request->request->get('plan_type', 'default');
        if (in_array($planType, ['default', 'custom'], true)) {
            $plan->setPlanType($planType);
        }
        $plan->setPosition((int) $request->request->get('position', 0));
        $plan->setIsActive($request->request->getBoolean('is_active'));

        if ((float) $price <= 0 && $nexbitsPrice <= 0) {
            $this->addFlash('danger', 'Vous devez renseigner au moins un prix (PayPal ou NexBits).');
            return false;
        }

        /** @var UploadedFile|null $icon */
        $icon = $request->files->get('icon');

        if (!$icon) {
            $this->logger->info('PremiumPlan: no file in request for field "icon".');
            return true;
        }

        if (!$icon->isValid()) {
            $msg = 'Erreur upload icone : ' . $icon->getErrorMessage();
            $this->logger->warning('PremiumPlan upload invalid: ' . $msg);
            $this->addFlash('danger', $msg);
            return true;
        }

        $mime = $icon->getMimeType();
        $clientMime = $icon->getClientMimeType();
        $this->logger->info('PremiumPlan upload: mime=' . $mime . ', clientMime=' . $clientMime . ', size=' . $icon->getSize());

        if (!in_array($mime, self::ALLOWED_MIMES, true) && !in_array($clientMime, self::ALLOWED_MIMES, true)) {
            $this->addFlash('danger', 'Format d\'icone non supporte (detecte: ' . $mime . ', client: ' . $clientMime . '). Formats acceptes : PNG, JPG, GIF, SVG, WebP.');
            return true;
        }

        // Delete old icon
        if ($plan->getIconFileName()) {
            $oldPath = $this->projectDir . '/public/' . self::UPLOAD_DIR . '/' . basename($plan->getIconFileName());
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $dir = $this->projectDir . '/public/' . self::UPLOAD_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext = $icon->guessExtension() ?: $icon->getClientOriginalExtension() ?: 'png';
        $fileName = uniqid() . '.' . $ext;
        $icon->move($dir, $fileName);
        $plan->setIconFileName($fileName);

        $this->logger->info('PremiumPlan icon saved: ' . $fileName);
        return true;
    }
}
