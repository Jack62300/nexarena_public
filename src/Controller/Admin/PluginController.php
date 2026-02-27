<?php

namespace App\Controller\Admin;

use App\Entity\Plugin;
use App\Form\Admin\PluginFormType;
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
#[IsGranted('plugins.list')]
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
    #[IsGranted('plugins.manage')]
    public function new(Request $request): Response
    {
        $plugin = new Plugin();
        $form = $this->createForm(PluginFormType::class, $plugin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plugin->setSlug($this->slugService->slugify($plugin->getName()));
            $plugin->setUpdatedAt(new \DateTimeImmutable());

            $this->handleFileUploads($plugin, $form);

            $this->em->persist($plugin);
            $this->em->flush();

            $this->addFlash('success', 'Plugin créé avec succès.');
            return $this->redirectToRoute('admin_plugins_list');
        }

        return $this->render('admin/plugins/form.html.twig', [
            'plugin' => null,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    #[IsGranted('plugins.manage')]
    public function edit(Plugin $plugin, Request $request): Response
    {
        $form = $this->createForm(PluginFormType::class, $plugin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plugin->setSlug($this->slugService->slugify($plugin->getName()));
            $plugin->setUpdatedAt(new \DateTimeImmutable());

            $this->handleFileUploads($plugin, $form);

            $this->em->flush();

            $this->addFlash('success', 'Plugin modifié avec succès.');
            return $this->redirectToRoute('admin_plugins_list');
        }

        return $this->render('admin/plugins/form.html.twig', [
            'plugin' => $plugin,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('plugins.manage')]
    public function delete(Plugin $plugin, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $plugin->getId(), $request->request->get('_token'))) {
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
            $this->addFlash('success', 'Plugin supprimé.');
        }

        return $this->redirectToRoute('admin_plugins_list');
    }

    #[Route('/{id}/refresh-scan', name: 'refresh_scan', methods: ['POST'])]
    #[IsGranted('plugins.manage')]
    public function refreshScan(Plugin $plugin, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('refresh_scan_' . $plugin->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_plugins_list');
        }

        $analysisId = $plugin->getVirusTotalAnalysisId();
        if (!$analysisId) {
            if ($plugin->getFileName()) {
                $filePath = $this->projectDir . '/public/uploads/plugins/' . $plugin->getFileName();
                if (file_exists($filePath)) {
                    $newAnalysisId = $this->virusTotalService->scanFile($filePath);
                    if ($newAnalysisId) {
                        $plugin->setVirusTotalAnalysisId($newAnalysisId);
                        $plugin->setVirusTotalStatus('pending');
                        $this->em->flush();
                        $this->addFlash('success', 'Nouveau scan lancé.');
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

        $this->addFlash('success', 'Statut mis à jour : ' . $result);
        return $this->redirectToRoute('admin_plugins_list');
    }

    private function handleFileUploads(Plugin $plugin, \Symfony\Component\Form\FormInterface $form): void
    {
        $zipFile = $form->get('zipFile')->getData();
        if ($zipFile) {
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

            $analysisId = $this->virusTotalService->scanFile($uploadDir . '/' . $newFilename);
            if ($analysisId) {
                $plugin->setVirusTotalAnalysisId($analysisId);
                $plugin->setVirusTotalStatus('pending');
            } else {
                $plugin->setVirusTotalStatus('pending');
                $plugin->setVirusTotalAnalysisId(null);
            }
        }

        $iconFile = $form->get('iconFile')->getData();
        if ($iconFile) {
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
