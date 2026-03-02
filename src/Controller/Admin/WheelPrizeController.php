<?php

namespace App\Controller\Admin;

use App\Repository\WheelPrizeRepository;
use App\Service\WheelService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/wheel', name: 'admin_wheel_')]
#[IsGranted('wheel.list')]
class WheelPrizeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private WheelPrizeRepository $wheelPrizeRepo,
        private WheelService $wheelService,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(): Response
    {
        $prizes = $this->wheelPrizeRepo->findAllOrdered();

        // If table is empty, init defaults first
        if (empty($prizes)) {
            $this->wheelService->initDefaultPrizes($this->em);
            $prizes = $this->wheelPrizeRepo->findAllOrdered();
        }

        $totalWeight = 0;
        $ev = 0.0;
        foreach ($prizes as $p) {
            $totalWeight += $p->getWeight();
        }

        $stats = [];
        foreach ($prizes as $p) {
            $proba = $totalWeight > 0 ? ($p->getWeight() / $totalWeight) * 100 : 0;
            $ev += $proba / 100 * $p->getNexbits();
            $stats[$p->getId()] = round($proba, 2);
        }

        return $this->render('admin/wheel/list.html.twig', [
            'prizes' => $prizes,
            'stats' => $stats,
            'total_weight' => $totalWeight,
            'ev' => round($ev, 2),
        ]);
    }

    #[Route('/edit/{id}', name: 'edit')]
    #[IsGranted('wheel.manage')]
    public function edit(int $id, Request $request): Response
    {
        $prize = $this->wheelPrizeRepo->find($id);
        if (!$prize) {
            $this->addFlash('error', 'Lot introuvable.');
            return $this->redirectToRoute('admin_wheel_list');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('wheel_edit_' . $id, $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_wheel_edit', ['id' => $id]);
            }

            $label = substr(strip_tags(trim((string) $request->request->get('label'))), 0, 50);
            $nexbits = max(0, (int) $request->request->get('nexbits'));
            $nexboost = max(0, (int) $request->request->get('nexboost'));
            $weight = max(1, (int) $request->request->get('weight'));
            $color = (string) $request->request->get('color', '#45f882');
            $isJackpot = (bool) $request->request->get('is_jackpot');

            if ($label === '') {
                $this->addFlash('error', 'Le label est obligatoire.');
                return $this->redirectToRoute('admin_wheel_edit', ['id' => $id]);
            }

            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#45f882';
            }

            $prize->setLabel($label);
            $prize->setNexbits($nexbits);
            $prize->setNexboost($nexboost);
            $prize->setWeight($weight);
            $prize->setColor($color);
            $prize->setIsJackpot($isJackpot);

            $this->em->flush();

            $this->addFlash('success', 'Lot "' . $label . '" mis a jour.');
            return $this->redirectToRoute('admin_wheel_list');
        }

        return $this->render('admin/wheel/edit.html.twig', [
            'prize' => $prize,
        ]);
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    #[IsGranted('wheel.manage')]
    public function reset(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('wheel_reset', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_wheel_list');
        }

        $this->wheelService->resetToDefaults($this->em);

        $this->addFlash('success', 'Les lots ont ete reinitialises aux valeurs par defaut.');
        return $this->redirectToRoute('admin_wheel_list');
    }
}
