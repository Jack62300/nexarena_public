<?php

namespace App\Controller\Admin;

use App\Entity\Badge;
use App\Repository\BadgeRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/badges', name: 'admin_badges_')]
#[IsGranted('ROLE_EDITEUR')]
class BadgeController extends AbstractController
{
    private const UPLOAD_DIR = 'uploads/badges';
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
        private string $projectDir,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(BadgeRepository $repo): Response
    {
        return $this->render('admin/badges/list.html.twig', [
            'badges' => $repo->findAllForAdmin(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('badge_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_badges_new');
            }

            $badge = new Badge();
            $this->handleForm($badge, $request);

            $this->em->persist($badge);
            $this->em->flush();

            $this->addFlash('success', 'Badge cree avec succes.');
            return $this->redirectToRoute('admin_badges_list');
        }

        return $this->render('admin/badges/form.html.twig', [
            'badge' => null,
            'awardedUsers' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Badge $badge, Request $request, UserBadgeRepository $ubRepo): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('badge_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_badges_edit', ['id' => $badge->getId()]);
            }

            $this->handleForm($badge, $request);
            $this->em->flush();

            $this->addFlash('success', 'Badge modifie avec succes.');
            return $this->redirectToRoute('admin_badges_list');
        }

        return $this->render('admin/badges/form.html.twig', [
            'badge' => $badge,
            'awardedUsers' => $ubRepo->findByBadge($badge),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function delete(Badge $badge, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $badge->getId(), $request->request->get('_token'))) {
            // Delete icon file
            if ($badge->getIconFileName()) {
                $path = $this->projectDir . '/public/' . self::UPLOAD_DIR . '/' . basename($badge->getIconFileName());
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            $this->em->remove($badge);
            $this->em->flush();
            $this->addFlash('success', 'Badge supprime.');
        }

        return $this->redirectToRoute('admin_badges_list');
    }

    #[Route('/{id}/award', name: 'award', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function award(Badge $badge, Request $request, UserRepository $userRepo, BadgeService $badgeService): Response
    {
        if (!$this->isCsrfTokenValid('badge_award_' . $badge->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_badges_edit', ['id' => $badge->getId()]);
        }

        $username = trim((string) $request->request->get('username', ''));
        if (!$username) {
            $this->addFlash('error', 'Nom d\'utilisateur requis.');
            return $this->redirectToRoute('admin_badges_edit', ['id' => $badge->getId()]);
        }

        $user = $userRepo->findOneByUsernameInsensitive($username);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_badges_edit', ['id' => $badge->getId()]);
        }

        if ($badgeService->awardBadge($user, $badge)) {
            $this->addFlash('success', 'Badge attribue a ' . $user->getUsername() . '.');
        } else {
            $this->addFlash('error', 'Cet utilisateur possede deja ce badge.');
        }

        return $this->redirectToRoute('admin_badges_edit', ['id' => $badge->getId()]);
    }

    #[Route('/{id}/revoke/{userId}', name: 'revoke', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function revoke(Badge $badge, int $userId, Request $request, UserRepository $userRepo, BadgeService $badgeService): Response
    {
        if (!$this->isCsrfTokenValid('badge_revoke_' . $badge->getId() . '_' . $userId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_badges_edit', ['id' => $badge->getId()]);
        }

        $user = $userRepo->find($userId);
        if ($user) {
            $badgeService->revokeBadge($user, $badge);
            $this->addFlash('success', 'Badge revoque de ' . $user->getUsername() . '.');
        }

        return $this->redirectToRoute('admin_badges_edit', ['id' => $badge->getId()]);
    }

    private function handleForm(Badge $badge, Request $request): void
    {
        $name = trim((string) $request->request->get('name', ''));
        $badge->setName($name);
        $badge->setSlug($this->slugService->slugify($name));
        $badge->setDescription(trim((string) $request->request->get('description', '')) ?: null);

        $color = $request->request->get('color');
        if ($color && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $badge->setColor($color);
        } else {
            $badge->setColor(null);
        }

        // Criteria
        $criteriaType = $request->request->get('criteria_type');
        if ($criteriaType) {
            $criteria = ['type' => $criteriaType];
            if (!in_array($criteriaType, ['custom', 'premium_purchase'])) {
                $criteria['threshold'] = max(1, (int) $request->request->get('criteria_threshold', 1));
            }
            $badge->setCriteria($criteria);
        } else {
            $badge->setCriteria(null);
        }

        $badge->setIsActive($request->request->getBoolean('is_active'));

        // Icon upload
        $iconFile = $request->files->get('icon');
        if ($iconFile && in_array($iconFile->getMimeType(), self::ALLOWED_MIMES) && $iconFile->getSize() <= 1024 * 1024) {
            // Delete old icon
            if ($badge->getIconFileName()) {
                $oldPath = $this->projectDir . '/public/' . self::UPLOAD_DIR . '/' . basename($badge->getIconFileName());
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $dir = $this->projectDir . '/public/' . self::UPLOAD_DIR;
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $filename = uniqid() . '.' . $iconFile->guessExtension();
            $iconFile->move($dir, $filename);
            $badge->setIconFileName($filename);
        }
    }
}
