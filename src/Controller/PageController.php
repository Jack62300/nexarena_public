<?php

namespace App\Controller;

use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    public function __construct(
        private SettingsService $settings,
    ) {
    }

    #[Route('/cgu', name: 'page_cgu')]
    public function cgu(): Response
    {
        return $this->render('pages/legal.html.twig', [
            'title' => 'Conditions Generales d\'Utilisation',
            'content' => $this->settings->get('legal_cgu', ''),
        ]);
    }

    #[Route('/cgv', name: 'page_cgv')]
    public function cgv(): Response
    {
        return $this->render('pages/legal.html.twig', [
            'title' => 'Conditions Generales de Vente',
            'content' => $this->settings->get('legal_cgv', ''),
        ]);
    }
}
