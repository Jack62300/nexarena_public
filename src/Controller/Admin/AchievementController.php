<?php

namespace App\Controller\Admin;

use App\Entity\Achievement;
use App\Form\Admin\AchievementFormType;
use App\Repository\AchievementRepository;
use App\Repository\UserAchievementRepository;
use App\Repository\UserRepository;
use App\Service\AchievementService;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/achievements', name: 'admin_achievements_')]
#[IsGranted('achievements.list')]
class AchievementController extends AbstractController
{
    private const UPLOAD_DIR  = 'uploads/achievements';
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
        private string $projectDir,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(AchievementRepository $repo): Response
    {
        return $this->render('admin/achievements/list.html.twig', [
            'achievements' => $repo->findAllForAdmin(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $achievement = new Achievement();
        $form = $this->createForm(AchievementFormType::class, $achievement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $achievement->setSlug($this->slugService->slugify($achievement->getName()));
            $this->handleCriteria($form, $achievement);
            $this->handleIconUpload($form, $achievement);

            $this->em->persist($achievement);
            $this->em->flush();

            $this->addFlash('success', 'Succès créé avec succès.');
            return $this->redirectToRoute('admin_achievements_list');
        }

        return $this->render('admin/achievements/form.html.twig', [
            'achievement'  => null,
            'awardedUsers' => [],
            'form'         => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Achievement $achievement, Request $request, UserAchievementRepository $uaRepo): Response
    {
        $form = $this->createForm(AchievementFormType::class, $achievement);

        if ($achievement->getCriteria()) {
            $form->get('criteriaType')->setData($achievement->getCriteria()['type'] ?? null);
            $form->get('criteriaThreshold')->setData($achievement->getCriteria()['threshold'] ?? 1);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $achievement->setSlug($this->slugService->slugify($achievement->getName()));
            $this->handleCriteria($form, $achievement);
            $this->handleIconUpload($form, $achievement);

            $this->em->flush();

            $this->addFlash('success', 'Succès modifié.');
            return $this->redirectToRoute('admin_achievements_list');
        }

        return $this->render('admin/achievements/form.html.twig', [
            'achievement'  => $achievement,
            'awardedUsers' => $uaRepo->findByAchievement($achievement),
            'form'         => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('achievements.manage')]
    public function delete(Achievement $achievement, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $achievement->getId(), $request->request->get('_token'))) {
            $this->deleteIcon($achievement);
            $this->em->remove($achievement);
            $this->em->flush();
            $this->addFlash('success', 'Succès supprimé.');
        }

        return $this->redirectToRoute('admin_achievements_list');
    }

    #[Route('/{id}/award', name: 'award', methods: ['POST'])]
    #[IsGranted('achievements.manage')]
    public function award(Achievement $achievement, Request $request, UserRepository $userRepo, AchievementService $achievementService): Response
    {
        if (!$this->isCsrfTokenValid('achievement_award_' . $achievement->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_achievements_edit', ['id' => $achievement->getId()]);
        }

        $username = trim((string) $request->request->get('username', ''));
        if (!$username) {
            $this->addFlash('error', "Nom d'utilisateur requis.");
            return $this->redirectToRoute('admin_achievements_edit', ['id' => $achievement->getId()]);
        }

        $user = $userRepo->findOneByUsernameInsensitive($username);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_achievements_edit', ['id' => $achievement->getId()]);
        }

        if ($achievementService->awardAchievement($user, $achievement)) {
            $this->addFlash('success', 'Succès attribué à ' . $user->getUsername() . '.');
        } else {
            $this->addFlash('error', 'Cet utilisateur possède déjà ce succès.');
        }

        return $this->redirectToRoute('admin_achievements_edit', ['id' => $achievement->getId()]);
    }

    #[Route('/{id}/revoke/{userId}', name: 'revoke', methods: ['POST'])]
    #[IsGranted('achievements.manage')]
    public function revoke(Achievement $achievement, int $userId, Request $request, UserRepository $userRepo, AchievementService $achievementService): Response
    {
        if (!$this->isCsrfTokenValid('achievement_revoke_' . $achievement->getId() . '_' . $userId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_achievements_edit', ['id' => $achievement->getId()]);
        }

        $user = $userRepo->find($userId);
        if ($user) {
            $achievementService->revokeAchievement($user, $achievement);
            $this->addFlash('success', 'Succès révoqué de ' . $user->getUsername() . '.');
        }

        return $this->redirectToRoute('admin_achievements_edit', ['id' => $achievement->getId()]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function handleCriteria(\Symfony\Component\Form\FormInterface $form, Achievement $achievement): void
    {
        $criteriaType = $form->get('criteriaType')->getData();
        if ($criteriaType) {
            $criteria = ['type' => $criteriaType];
            if (!in_array($criteriaType, ['custom', 'premium_purchase'], true)) {
                $criteria['threshold'] = max(1, (int) $form->get('criteriaThreshold')->getData());
            }
            $achievement->setCriteria($criteria);
        } else {
            $achievement->setCriteria(null);
        }
    }

    private function handleIconUpload(\Symfony\Component\Form\FormInterface $form, Achievement $achievement): void
    {
        $iconFile = $form->get('iconFile')->getData();
        if (!$iconFile) {
            return;
        }

        if ($achievement->getIconFileName()) {
            $this->deleteIcon($achievement);
        }

        $dir = $this->projectDir . '/public/' . self::UPLOAD_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = uniqid() . '.' . $iconFile->guessExtension();
        $iconFile->move($dir, $filename);
        $achievement->setIconFileName($filename);
    }

    private function deleteIcon(Achievement $achievement): void
    {
        if (!$achievement->getIconFileName()) {
            return;
        }
        $path = $this->projectDir . '/public/' . self::UPLOAD_DIR . '/' . basename($achievement->getIconFileName());
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
