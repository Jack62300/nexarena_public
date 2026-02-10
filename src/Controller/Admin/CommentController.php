<?php

namespace App\Controller\Admin;

use App\Entity\Comment;
use App\Repository\CommentRepository;
use App\Repository\ServerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/comments', name: 'admin_comments_')]
#[IsGranted('ROLE_EDITEUR')]
class CommentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/flagged', name: 'flagged')]
    public function flagged(CommentRepository $repo): Response
    {
        return $this->render('admin/comments/list.html.twig', [
            'tab' => 'flagged',
            'flaggedComments' => $repo->findFlagged(),
            'allComments' => [],
            'servers' => [],
            'filters' => ['server' => null],
        ]);
    }

    #[Route('', name: 'list')]
    public function list(Request $request, CommentRepository $commentRepo, ServerRepository $serverRepo): Response
    {
        $serverId = $request->query->get('server');
        $server = $serverId ? $serverRepo->find((int) $serverId) : null;

        return $this->render('admin/comments/list.html.twig', [
            'tab' => 'all',
            'flaggedComments' => [],
            'allComments' => $commentRepo->findForAdminList($server),
            'servers' => $serverRepo->findBy([], ['name' => 'ASC']),
            'filters' => ['server' => $serverId],
        ]);
    }

    #[Route('/{id}/approve-delete', name: 'approve_delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function approveDelete(Comment $comment, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('comment_action_' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_comments_flagged');
        }

        $comment->setIsDeleted(true);
        $comment->setDeletedAt(new \DateTimeImmutable());
        $comment->setDeletedBy($this->getUser());
        $this->em->flush();

        $this->addFlash('success', 'Le commentaire signale a ete supprime.');
        return $this->redirectToRoute('admin_comments_flagged');
    }

    #[Route('/{id}/dismiss-flag', name: 'dismiss_flag', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function dismissFlag(Comment $comment, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('comment_action_' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_comments_flagged');
        }

        $comment->setIsFlagged(false);
        $comment->setFlagReason(null);
        $comment->setFlaggedBy(null);
        $comment->setFlaggedAt(null);
        $this->em->flush();

        $this->addFlash('success', 'Le signalement a ete rejete.');
        return $this->redirectToRoute('admin_comments_flagged');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function delete(Comment $comment, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('comment_delete_' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_comments_list');
        }

        $this->em->remove($comment);
        $this->em->flush();

        $this->addFlash('success', 'Le commentaire a ete supprime definitivement.');
        return $this->redirectToRoute('admin_comments_list');
    }
}
