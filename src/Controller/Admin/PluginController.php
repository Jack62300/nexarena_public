<?php

namespace App\Controller\Admin;

use App\Entity\Plugin;
use App\Repository\PluginRepository;
use App\Service\SlugService;
use App\Service\VirusTotalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/plugins', name: 'admin_plugins_')]
#[IsGranted('ROLE_EDITEUR')]
class PluginController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
        private VirusTotalService $virusTotalService,
        private string $projectDir,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(PluginRepository $repo): Response
    {
        return $this->render('admin/plugins/list.html.twig', [
            'plugins' => $repo->findAllForAdmin(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('plugin_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_plugins_new');
            }

            $plugin = new Plugin();
            $this->handleForm($plugin, $request);

            $this->em->persist($plugin);
            $this->em->flush();

            $this->addFlash('success', 'Plugin cree avec succes.');
            return $this->redirectToRoute('admin_plugins_list');
        }

        return $this->render('admin/plugins/form.html.twig', [
            'plugin' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Plugin $plugin, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('plugin_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_plugins_edit', ['id' => $plugin->getId()]);
            }

            $this->handleForm($plugin, $request);
            $this->em->flush();

            $this->addFlash('success', 'Plugin modifie avec succes.');
            return $this->redirectToRoute('admin_plugins_list');
        }

        return $this->render('admin/plugins/form.html.twig', [
            'plugin' => $plugin,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function delete(Plugin $plugin, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $plugin->getId(), $request->request->get('_token'))) {
            // Delete associated files
            if ($plugin->getFileName()) {
                $filePath = $this->projectDir . '/public/uploads/plugins/' . basename($plugin->getFileName());
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            if ($plugin->getIconFileName()) {
                $iconPath = $this->projectDir . '/public/uploads/plugins/icons/' . basename($plugin->getIconFileName());
                if (file_exists($iconPath)) {
                    unlink($iconPath);
                }
            }

            $this->em->remove($plugin);
            $this->em->flush();
            $this->addFlash('success', 'Plugin supprime.');
        }

        return $this->redirectToRoute('admin_plugins_list');
    }

    #[Route('/{id}/refresh-scan', name: 'refresh_scan', methods: ['POST'])]
    public function refreshScan(Plugin $plugin, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('refresh_scan_' . $plugin->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_plugins_list');
        }

        $analysisId = $plugin->getVirusTotalAnalysisId();
        if (!$analysisId) {
            // Try to re-scan the file
            if ($plugin->getFileName()) {
                $filePath = $this->projectDir . '/public/uploads/plugins/' . $plugin->getFileName();
                if (file_exists($filePath)) {
                    $newAnalysisId = $this->virusTotalService->scanFile($filePath);
                    if ($newAnalysisId) {
                        $plugin->setVirusTotalAnalysisId($newAnalysisId);
                        $plugin->setVirusTotalStatus('pending');
                        $this->em->flush();
                        $this->addFlash('success', 'Nouveau scan lance.');
                        return $this->redirectToRoute('admin_plugins_list');
                    }
                }
            }
            $this->addFlash('error', 'Impossible de lancer le scan.');
            return $this->redirectToRoute('admin_plugins_list');
        }

        $result = $this->virusTotalService->getAnalysisResult($analysisId);
        $plugin->setVirusTotalStatus($result);
        $this->em->flush();

        $this->addFlash('success', 'Statut mis a jour : ' . $result);
        return $this->redirectToRoute('admin_plugins_list');
    }

    private function handleForm(Plugin $plugin, Request $request): void
    {
        $plugin->setName($request->request->get('name', ''));
        $plugin->setSlug($this->slugService->slugify($request->request->get('name', '')));
        $plugin->setDescription($request->request->get('description', ''));
        $plugin->setLongDescription($request->request->get('long_description'));
        $plugin->setPlatform($request->request->get('platform', ''));
        $plugin->setCategory($request->request->get('category', ''));
        $plugin->setVersion($request->request->get('version', ''));
        $plugin->setIsActive($request->request->getBoolean('is_active'));
        $plugin->setUpdatedAt(new \DateTimeImmutable());

        // Handle ZIP upload
        $zipFile = $request->files->get('zip_file');
        if ($zipFile) {
            $allowedZipMimes = ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'];
            if (!in_array($zipFile->getMimeType(), $allowedZipMimes, true) || $zipFile->getSize() > 50 * 1024 * 1024) {
                return;
            }

            // Delete old file
            if ($plugin->getFileName()) {
                $oldPath = $this->projectDir . '/public/uploads/plugins/' . basename($plugin->getFileName());
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $uploadDir = $this->projectDir . '/public/uploads/plugins';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFilename = uniqid() . '.zip';
            $zipFile->move($uploadDir, $newFilename);

            $plugin->setFileName($newFilename);
            $plugin->setFileSize(filesize($uploadDir . '/' . $newFilename));

            // Launch VirusTotal scan
            $analysisId = $this->virusTotalService->scanFile($uploadDir . '/' . $newFilename);
            if ($analysisId) {
                $plugin->setVirusTotalAnalysisId($analysisId);
                $plugin->setVirusTotalStatus('pending');
            } else {
                $plugin->setVirusTotalStatus('pending');
                $plugin->setVirusTotalAnalysisId(null);
            }
        }

        // Handle icon upload
        $iconFile = $request->files->get('icon_file');
        if ($iconFile) {
            $allowedIconMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($iconFile->getMimeType(), $allowedIconMimes, true) || $iconFile->getSize() > 2 * 1024 * 1024) {
                return;
            }

            // Delete old icon
            if ($plugin->getIconFileName()) {
                $oldIconPath = $this->projectDir . '/public/uploads/plugins/icons/' . basename($plugin->getIconFileName());
                if (file_exists($oldIconPath)) {
                    unlink($oldIconPath);
                }
            }

            $iconDir = $this->projectDir . '/public/uploads/plugins/icons';
            if (!is_dir($iconDir)) {
                mkdir($iconDir, 0755, true);
            }

            $newIconFilename = uniqid() . '.' . $iconFile->guessExtension();
            $iconFile->move($iconDir, $newIconFilename);
            $plugin->setIconFileName($newIconFilename);
        }
    }
}
