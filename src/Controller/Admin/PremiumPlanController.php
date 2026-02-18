<?php

namespace App\Controller\Admin;

use App\Entity\PremiumPlan;
use App\Form\Admin\PremiumPlanFormType;
use App\Repository\PremiumPlanRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/premium/plans', name: 'admin_premium_plans_')]
#[IsGranted('ROLE_MANAGER')]
class PremiumPlanController extends AbstractController
{
    private const UPLOAD_DIR = 'uploads/plans';

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
        $plan = new PremiumPlan();
        $form = $this->createForm(PremiumPlanFormType::class, $plan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ((float) $plan->getPrice() <= 0 && (int) $plan->getNexbitsPrice() <= 0) {
                $this->addFlash('danger', 'Vous devez renseigner au moins un prix (PayPal ou NexBits).');
                return $this->render('admin/premium/plans/form.html.twig', ['plan' => null, 'form' => $form]);
            }

            $plan->setSlug($this->slugService->slugify($plan->getName()));

            $iconFile = $form->get('iconFile')->getData();
            if ($iconFile) {
                $dir = $this->projectDir . '/public/' . self::UPLOAD_DIR;
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $ext = $iconFile->guessExtension() ?: $iconFile->getClientOriginalExtension() ?: 'png';
                $fileName = uniqid() . '.' . $ext;
                $iconFile->move($dir, $fileName);
                $plan->setIconFileName($fileName);
                $this->logger->info('PremiumPlan icon saved: ' . $fileName);
            }

            $this->em->persist($plan);
            $this->em->flush();

            $this->addFlash('success', 'Plan premium créé avec succès.');
            return $this->redirectToRoute('admin_premium_plans_list');
        }

        return $this->render('admin/premium/plans/form.html.twig', [
            'plan' => null,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(PremiumPlan $plan, Request $request): Response
    {
        $form = $this->createForm(PremiumPlanFormType::class, $plan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ((float) $plan->getPrice() <= 0 && (int) $plan->getNexbitsPrice() <= 0) {
                $this->addFlash('danger', 'Vous devez renseigner au moins un prix (PayPal ou NexBits).');
                return $this->render('admin/premium/plans/form.html.twig', ['plan' => $plan, 'form' => $form]);
            }

            $plan->setSlug($this->slugService->slugify($plan->getName()));

            $iconFile = $form->get('iconFile')->getData();
            if ($iconFile) {
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

                $ext = $iconFile->guessExtension() ?: $iconFile->getClientOriginalExtension() ?: 'png';
                $fileName = uniqid() . '.' . $ext;
                $iconFile->move($dir, $fileName);
                $plan->setIconFileName($fileName);
                $this->logger->info('PremiumPlan icon saved: ' . $fileName);
            }

            $this->em->flush();

            $this->addFlash('success', 'Plan premium modifié avec succès.');
            return $this->redirectToRoute('admin_premium_plans_list');
        }

        return $this->render('admin/premium/plans/form.html.twig', [
            'plan' => $plan,
            'form' => $form,
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
            $this->addFlash('success', 'Plan premium supprimé.');
        }

        return $this->redirectToRoute('admin_premium_plans_list');
    }
}
