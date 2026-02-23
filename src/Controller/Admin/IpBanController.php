<?php

namespace App\Controller\Admin;

use App\Entity\IpBan;
use App\Repository\IpBanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ip-bans', name: 'admin_ip_bans_')]
#[IsGranted('ROLE_MANAGER')]
class IpBanController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(IpBanRepository $repo, Request $request): Response
    {
        $filter = $request->query->get('filter', 'active');

        return $this->render('admin/ip_ban/index.html.twig', [
            'bans'   => $repo->findAllForAdmin($filter),
            'filter' => $filter,
            'total'  => $repo->countActive(),
        ]);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('ip_ban_add', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_ip_bans_index');
        }

        $ip     = trim((string) $request->request->get('ip_address', ''));
        $type   = $request->request->get('type', IpBan::TYPE_PERMANENT);
        $reason = trim((string) $request->request->get('reason', '')) ?: null;

        // Validation IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->addFlash('error', 'Adresse IP invalide.');
            return $this->redirectToRoute('admin_ip_bans_index');
        }

        if (!in_array($type, [IpBan::TYPE_PERMANENT, IpBan::TYPE_TEMPORARY], true)) {
            $this->addFlash('error', 'Type de ban invalide.');
            return $this->redirectToRoute('admin_ip_bans_index');
        }

        $ban = new IpBan();
        $ban->setIpAddress($ip);
        $ban->setType($type);
        $ban->setReason($reason);
        $ban->setBannedBy($this->getUser());

        if ($type === IpBan::TYPE_TEMPORARY) {
            $duration = (int) $request->request->get('duration', 0);
            $unit     = $request->request->get('duration_unit', IpBan::UNIT_HOURS);

            if ($duration <= 0) {
                $this->addFlash('error', 'La durée doit être supérieure à 0.');
                return $this->redirectToRoute('admin_ip_bans_index');
            }

            if (!in_array($unit, [IpBan::UNIT_HOURS, IpBan::UNIT_DAYS], true)) {
                $this->addFlash('error', 'Unité de durée invalide.');
                return $this->redirectToRoute('admin_ip_bans_index');
            }

            // Limite raisonnable
            $maxDuration = $unit === IpBan::UNIT_HOURS ? 8760 : 365; // 1 an max
            if ($duration > $maxDuration) {
                $this->addFlash('error', "Durée trop longue (max {$maxDuration} {$unit}).");
                return $this->redirectToRoute('admin_ip_bans_index');
            }

            $ban->setDuration($duration);
            $ban->setDurationUnit($unit);
            $ban->computeExpiresAt();
        }

        $em->persist($ban);
        $em->flush();

        $this->addFlash('success', "IP {$ip} bannie avec succès.");
        return $this->redirectToRoute('admin_ip_bans_index');
    }

    #[Route('/{id}/revoke', name: 'revoke', methods: ['POST'])]
    public function revoke(IpBan $ban, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('ip_ban_revoke_' . $ban->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_ip_bans_index');
        }

        if (!$ban->isActive()) {
            $this->addFlash('warning', 'Ce ban est déjà inactif.');
            return $this->redirectToRoute('admin_ip_bans_index');
        }

        $ban->setIsActive(false);
        $ban->setRevokedBy($this->getUser());
        $ban->setRevokedAt(new \DateTimeImmutable());

        $em->flush();

        $this->addFlash('success', "Ban de {$ban->getIpAddress()} levé.");
        return $this->redirectToRoute('admin_ip_bans_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function delete(IpBan $ban, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('ip_ban_delete_' . $ban->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_ip_bans_index');
        }

        $ip = $ban->getIpAddress();
        $em->remove($ban);
        $em->flush();

        $this->addFlash('success', "Ban de {$ip} supprimé.");
        return $this->redirectToRoute('admin_ip_bans_index');
    }
}
