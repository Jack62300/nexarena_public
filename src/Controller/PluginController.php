<?php

namespace App\Controller;

use App\Repository\PluginRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class PluginController extends AbstractController
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    #[Route('/plugins', name: 'plugins_index')]
    public function index(PluginRepository $repo): Response
    {
        $plugins = $repo->findAllActive();

        $grouped = [
            'game' => [],
            'vocal' => [],
            'hosting' => [],
        ];

        foreach ($plugins as $plugin) {
            $cat = $plugin->getCategory();
            if (isset($grouped[$cat])) {
                $grouped[$cat][] = $plugin;
            } else {
                $grouped['game'][] = $plugin;
            }
        }

        return $this->render('plugins/index.html.twig', [
            'plugins' => $plugins,
            'grouped' => $grouped,
        ]);
    }

    #[Route('/plugins/{slug}/download', name: 'plugins_download')]
    public function download(string $slug, PluginRepository $repo, EntityManagerInterface $em): Response
    {
        $plugin = $repo->findBySlug($slug);
        if (!$plugin) {
            throw $this->createNotFoundException('Plugin introuvable.');
        }

        if (!$plugin->getFileName()) {
            throw $this->createNotFoundException('Aucun fichier disponible.');
        }

        if ($plugin->getVirusTotalStatus() === 'flagged') {
            $this->addFlash('error', 'Ce fichier a ete signale comme potentiellement dangereux.');
            return $this->redirectToRoute('plugins_index');
        }

        $filePath = $this->projectDir . '/public/uploads/plugins/' . $plugin->getFileName();
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $plugin->incrementDownloadCount();
        $em->flush();

        $response = new BinaryFileResponse($filePath);
        $downloadName = $plugin->getSlug() . '-v' . $plugin->getVersion() . '.zip';
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);

        return $response;
    }
}
