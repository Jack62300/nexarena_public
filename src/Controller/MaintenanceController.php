<?php

namespace App\Controller;

use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MaintenanceController extends AbstractController
{
    #[Route('/maintenance', name: 'app_maintenance')]
    public function index(SettingsService $settings): Response
    {
        // Maintenance désactivée → accueil
        if (!$settings->getBool('maintenance_mode', false)) {
            return $this->redirectToRoute('app_home');
        }

        // Admin déjà connecté → accueil
        if ($this->isGranted('ROLE_EDITEUR')) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('maintenance.html.twig', [], new Response('', 503));
    }
}
