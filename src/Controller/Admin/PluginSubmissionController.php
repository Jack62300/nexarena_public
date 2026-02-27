<?php

namespace App\Controller\Admin;

use App\Entity\Plugin;
use App\Entity\PluginSubmission;
use App\Entity\Transaction;
use App\Repository\PluginSubmissionRepository;
use App\Service\SettingsService;
use App\Service\SlugService;
use App\Service\VirusTotalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/plugin-submissions', name: 'admin_plugin_submissions_')]
#[IsGranted('plugins.list')]
class PluginSubmissionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
        private readonly SlugService $slugService,
        private readonly VirusTotalService $virusTotalService,
        private readonly SettingsService $settings,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(PluginSubmissionRepository $repo): Response
    {
        return $this->render('admin/plugin_submissions/list.html.twig', [
            'submissions' => $repo->findAllForAdmin(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(PluginSubmission $submission): Response
    {
        return $this->render('admin/plugin_submissions/show.html.twig', [
            'submission' => $submission,
        ]);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    #[IsGranted('plugins.manage')]
    public function approve(PluginSubmission $submission, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('approve_' . $submission->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_plugin_submissions_show', ['id' => $submission->getId()]);
        }

        if ($submission->getStatus() !== PluginSubmission::STATUS_PENDING) {
            $this->addFlash('error', 'Cette soumission a déjà été traitée.');
            return $this->redirectToRoute('admin_plugin_submissions_show', ['id' => $submission->getId()]);
        }

        $pluginName  = trim((string) $request->request->get('plugin_name', $submission->getPluginName()));
        $platform    = trim((string) $request->request->get('platform', 'other'));
        $category    = trim((string) $request->request->get('category', 'game'));
        $version     = trim((string) $request->request->get('version', '1.0.0')) ?: '1.0.0';
        $longDesc    = trim((string) $request->request->get('long_description', ''));

        if ($pluginName === '') {
            $this->addFlash('error', 'Le nom du plugin est obligatoire.');
            return $this->redirectToRoute('admin_plugin_submissions_show', ['id' => $submission->getId()]);
        }

        // Move file from submissions folder to plugins folder
        $srcPath = $this->projectDir . '/public/uploads/plugin-submissions/' . basename($submission->getFileName());
        $pluginDir = $this->projectDir . '/public/uploads/plugins';
        if (!is_dir($pluginDir)) {
            mkdir($pluginDir, 0755, true);
        }

        $ext = pathinfo($submission->getFileName(), PATHINFO_EXTENSION);
        $newFilename = uniqid() . '.' . $ext;
        $destPath = $pluginDir . '/' . $newFilename;

        if (file_exists($srcPath)) {
            copy($srcPath, $destPath);
        }

        // Create the Plugin entity
        $plugin = new Plugin();
        $plugin->setName($pluginName);
        $plugin->setSlug($this->slugService->slugify($pluginName));
        $plugin->setDescription($submission->getDescription());
        $plugin->setLongDescription($longDesc ?: null);
        $plugin->setPlatform($platform);
        $plugin->setCategory($category);
        $plugin->setVersion($version);
        $plugin->setCreatorName($submission->getCreatorName());
        $plugin->setFileName($newFilename);
        $plugin->setFileSize($submission->getFileSize());
        $plugin->setIsActive(false); // inactive until VT scan passes
        $plugin->setUpdatedAt(new \DateTimeImmutable());

        // Kick off VT scan
        if (file_exists($destPath)) {
            $analysisId = $this->virusTotalService->scanFile($destPath);
            $plugin->setVirusTotalAnalysisId($analysisId ?: null);
            $plugin->setVirusTotalStatus('pending');
        }

        $this->em->persist($plugin);

        // Mark submission as approved
        $submission->setStatus(PluginSubmission::STATUS_APPROVED);
        $submission->setReviewedBy($this->getUser());
        $submission->setReviewedAt(new \DateTimeImmutable());
        $submission->setLinkedPlugin($plugin);

        // Credit reward tokens to submitter if they have an account
        $reward = $this->settings->getInt('plugin_submission_reward', 200);
        $submitter = $submission->getSubmitterUser();
        if ($reward > 0 && $submitter !== null) {
            $submitter->addTokens($reward);

            $tx = new Transaction();
            $tx->setUser($submitter);
            $tx->setType(Transaction::TYPE_ADMIN_CREDIT);
            $tx->setTokensAmount($reward);
            $tx->setDescription('Récompense soumission plugin : ' . $pluginName);
            $tx->setIsCredited(true);
            $tx->setCreditedAt(new \DateTimeImmutable());
            $this->em->persist($tx);
        }

        $this->em->flush();

        $rewardMsg = ($reward > 0 && $submitter !== null) ? " $reward NexBits ont été crédités à {$submitter->getUsername()}." : '';
        $this->addFlash('success', 'Soumission approuvée. Plugin "' . $pluginName . '" créé.' . $rewardMsg . ' Complétez ses informations ci-dessous.');
        return $this->redirectToRoute('admin_plugins_edit', ['id' => $plugin->getId()]);
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    #[IsGranted('plugins.manage')]
    public function reject(PluginSubmission $submission, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reject_' . $submission->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_plugin_submissions_show', ['id' => $submission->getId()]);
        }

        if ($submission->getStatus() !== PluginSubmission::STATUS_PENDING) {
            $this->addFlash('error', 'Cette soumission a déjà été traitée.');
            return $this->redirectToRoute('admin_plugin_submissions_show', ['id' => $submission->getId()]);
        }

        $reason = trim((string) $request->request->get('reason', ''));

        $submission->setStatus(PluginSubmission::STATUS_REJECTED);
        $submission->setRejectionReason($reason ?: null);
        $submission->setReviewedBy($this->getUser());
        $submission->setReviewedAt(new \DateTimeImmutable());

        $this->em->flush();

        $this->addFlash('success', 'Soumission rejetée.');
        return $this->redirectToRoute('admin_plugin_submissions_list');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('plugins.manage')]
    public function delete(PluginSubmission $submission, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_submission_' . $submission->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_plugin_submissions_list');
        }

        $filePath = $this->projectDir . '/public/uploads/plugin-submissions/' . basename($submission->getFileName());
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->em->remove($submission);
        $this->em->flush();

        $this->addFlash('success', 'Soumission supprimée.');
        return $this->redirectToRoute('admin_plugin_submissions_list');
    }
}
