<?php

namespace App\Controller\Admin;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/premium/transactions', name: 'admin_transactions_')]
#[IsGranted('transactions.list')]
class TransactionController extends AbstractController
{
    #[Route('', name: 'list')]
    public function list(Request $request, TransactionRepository $repo): Response
    {
        $type = $request->query->get('type');
        $validTypes = ['purchase', 'spend', 'refund', 'admin_credit', 'wheel_reward'];
        if ($type && !in_array($type, $validTypes, true)) {
            $type = null;
        }

        return $this->render('admin/premium/transactions/list.html.twig', [
            'transactions' => $repo->findForAdmin($type),
            'current_type' => $type,
        ]);
    }

    #[Route('/bulk-delete', name: 'bulk_delete', methods: ['POST'])]
    #[IsGranted('transactions.delete')]
    public function bulkDelete(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('transactions_bulk_delete', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_transactions_list');
        }

        $rawIds = $request->request->all('ids');
        $ids = array_values(array_filter(array_map('intval', is_array($rawIds) ? $rawIds : []), fn($id) => $id > 0));

        if (empty($ids)) {
            $this->addFlash('error', 'Aucune transaction sélectionnée.');
            return $this->redirectToRoute('admin_transactions_list');
        }

        $deleted = $em->createQueryBuilder()
            ->delete(Transaction::class, 't')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();

        $this->addFlash('success', $deleted . ' transaction(s) supprimée(s).');
        return $this->redirectToRoute('admin_transactions_list');
    }
}
