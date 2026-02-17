<?php

namespace App\Controller\Admin;

use App\Entity\Vote;
use App\Repository\ServerRepository;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/votes', name: 'admin_votes_')]
#[IsGranted('ROLE_EDITEUR')]
class VoteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(Request $request, VoteRepository $voteRepo, ServerRepository $serverRepo): Response
    {
        $serverId = $request->query->get('server');
        $server = $serverId ? $serverRepo->find((int) $serverId) : null;

        return $this->render('admin/votes/list.html.twig', [
            'votes' => $voteRepo->findForAdminList($server),
            'servers' => $serverRepo->findBy([], ['name' => 'ASC']),
            'currentServer' => $server,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('votes.manage')]
    public function delete(Vote $vote, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_vote_' . $vote->getId(), $request->request->get('_token'))) {
            $server = $vote->getServer();
            if ($server) {
                $server->setTotalVotes(max(0, $server->getTotalVotes() - 1));
                $server->setMonthlyVotes(max(0, $server->getMonthlyVotes() - 1));
            }

            $this->em->remove($vote);
            $this->em->flush();
            $this->addFlash('success', 'Vote supprime.');
        }

        return $this->redirectToRoute('admin_votes_list');
    }
}
