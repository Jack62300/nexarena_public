<?php

namespace App\Controller\Admin;

use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/premium/transactions', name: 'admin_transactions_')]
#[IsGranted('ROLE_MANAGER')]
class TransactionController extends AbstractController
{
    #[Route('', name: 'list')]
    public function list(Request $request, TransactionRepository $repo): Response
    {
        $type = $request->query->get('type');
        $validTypes = ['purchase', 'spend', 'refund', 'admin_credit'];
        if ($type && !in_array($type, $validTypes, true)) {
            $type = null;
        }

        return $this->render('admin/premium/transactions/list.html.twig', [
            'transactions' => $repo->findForAdmin($type),
            'current_type' => $type,
        ]);
    }
}
