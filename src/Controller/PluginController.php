<?php

namespace App\Controller;

use App\Entity\PluginSubmission;
use App\Repository\PluginRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class PluginController extends AbstractController
{
    public function __construct(
        private string $projectDir,
    ) {}

    #[Route('/plugins', name: 'plugins_index')]
    public function index(PluginRepository $repo): Response
    {
        $plugins = $repo->findAllActive();

        $grouped = ['game' => [], 'vocal' => [], 'hosting' => []];
        foreach ($plugins as $plugin) {
            $cat = $plugin->getCategory();
            $grouped[array_key_exists($cat, $grouped) ? $cat : 'game'][] = $plugin;
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

        $ext = pathinfo($plugin->getFileName(), PATHINFO_EXTENSION) ?: 'zip';
        $response = new BinaryFileResponse($filePath);
        $downloadName = $plugin->getSlug() . '-v' . $plugin->getVersion() . '.' . $ext;
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);

        return $response;
    }

    #[Route('/plugins/soumettre', name: 'plugins_submit', methods: ['GET', 'POST'])]
    public function submit(Request $request, EntityManagerInterface $em, CacheItemPoolInterface $cache): Response
    {
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('plugin_submit', $request->request->get('_token'))) {
                $errors[] = 'Token CSRF invalide.';
            } else {
                // Rate limit: 5 submissions per IP per 24h
                $ip = $request->getClientIp();
                $cacheKey = 'plugin_submit_' . hash('sha256', (string) $ip);
                $cacheItem = $cache->getItem($cacheKey);
                $attempts  = $cacheItem->isHit() ? (int) $cacheItem->get() : 0;

                if ($attempts >= 5) {
                    $errors[] = 'Trop de soumissions depuis cette adresse. Réessayez demain.';
                } else {
                    $pluginName      = trim((string) $request->request->get('plugin_name', ''));
                    $creatorName     = trim((string) $request->request->get('creator_name', ''));
                    $description     = trim((string) $request->request->get('description', ''));
                    $gameDescription = trim((string) $request->request->get('game_description', ''));
                    $archiveFile     = $request->files->get('archive_file');
                    $acceptTerms     = $request->request->get('accept_terms');

                    if ($pluginName === '') $errors[] = 'Le nom du plugin est obligatoire.';
                    if (strlen($pluginName) > 100) $errors[] = 'Le nom du plugin ne peut pas dépasser 100 caractères.';
                    if ($creatorName === '') $errors[] = 'Votre nom / pseudo de créateur est obligatoire.';
                    if (strlen($creatorName) > 100) $errors[] = 'Le nom du créateur ne peut pas dépasser 100 caractères.';
                    if ($description === '') $errors[] = 'La description est obligatoire.';
                    if (strlen($description) > 500) $errors[] = 'La description ne peut pas dépasser 500 caractères.';
                    if ($gameDescription === '') $errors[] = 'Le jeu est obligatoire.';
                    if (!$acceptTerms) $errors[] = "Vous devez accepter les conditions de propriété.";
                    if (!$archiveFile) {
                        $errors[] = 'Veuillez sélectionner une archive (ZIP ou RAR).';
                    } else {
                        $ext = strtolower($archiveFile->getClientOriginalExtension());
                        $allowedExts  = ['zip', 'rar'];
                        $allowedMimes = [
                            'application/zip', 'application/x-zip-compressed',
                            'application/x-zip', 'application/x-rar-compressed',
                            'application/vnd.rar', 'application/x-rar',
                            'application/octet-stream',
                        ];

                        if (!in_array($ext, $allowedExts, true)) {
                            $errors[] = 'Seuls les fichiers ZIP et RAR sont acceptés.';
                        } elseif (!in_array($archiveFile->getMimeType(), $allowedMimes, true) && !in_array($ext, ['zip', 'rar'], true)) {
                            $errors[] = 'Type de fichier non autorisé.';
                        } elseif ($archiveFile->getSize() > 50 * 1024 * 1024) {
                            $errors[] = 'Le fichier ne doit pas dépasser 50 Mo.';
                        }
                    }

                    if (empty($errors)) {
                        $uploadDir = $this->projectDir . '/public/uploads/plugin-submissions';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $ext         = strtolower($archiveFile->getClientOriginalExtension());
                        $newFilename = uniqid() . '.' . $ext;
                        $archiveFile->move($uploadDir, $newFilename);

                        $submission = new PluginSubmission();
                        $submission->setPluginName($pluginName);
                        $submission->setCreatorName($creatorName);
                        $submission->setDescription($description);
                        $submission->setGameDescription($gameDescription);
                        $submission->setFileName($newFilename);
                        $submission->setOriginalFileName(basename($archiveFile->getClientOriginalName()));
                        $submission->setFileSize(filesize($uploadDir . '/' . $newFilename) ?: null);
                        $submission->setSubmitterIp($ip);

                        if ($this->getUser()) {
                            $submission->setSubmitterUser($this->getUser());
                        }

                        $em->persist($submission);
                        $em->flush();

                        // Update rate limit counter
                        $cacheItem->set($attempts + 1);
                        $cacheItem->expiresAfter(86400);
                        $cache->save($cacheItem);

                        $this->addFlash('success', 'Votre plugin a été soumis avec succès ! Notre équipe l\'examinera prochainement.');
                        return $this->redirectToRoute('plugins_index');
                    }
                }
            }
        }

        return $this->render('plugins/submit.html.twig', [
            'errors' => $errors,
        ]);
    }
}
