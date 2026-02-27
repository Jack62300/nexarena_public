<?php

namespace App\Controller\Admin;

use App\Entity\BlacklistEntry;
use App\Repository\BlacklistEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/blacklist', name: 'admin_blacklist_')]
#[IsGranted('blacklist.manage')]
class BlacklistController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(BlacklistEntryRepository $repo): Response
    {
        return $this->render('admin/blacklist/index.html.twig', [
            'usernames' => $repo->findByType(BlacklistEntry::TYPE_USERNAME),
            'domains'   => $repo->findByType(BlacklistEntry::TYPE_EMAIL_DOMAIN),
        ]);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request, EntityManagerInterface $em, BlacklistEntryRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('blacklist_add', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_blacklist_index');
        }

        $type  = $request->request->get('type');
        $value = trim((string) $request->request->get('value'));
        $reason = trim((string) $request->request->get('reason')) ?: null;

        if (!in_array($type, [BlacklistEntry::TYPE_USERNAME, BlacklistEntry::TYPE_EMAIL_DOMAIN], true)) {
            $this->addFlash('error', 'Type invalide.');
            return $this->redirectToRoute('admin_blacklist_index');
        }

        if ($value === '') {
            $this->addFlash('error', 'La valeur ne peut pas être vide.');
            return $this->redirectToRoute('admin_blacklist_index');
        }

        $value = strtolower($value);

        if ($repo->isValueBlacklisted($type, $value)) {
            $this->addFlash('warning', '"' . $value . '" est déjà dans la liste noire.');
            return $this->redirectToRoute('admin_blacklist_index');
        }

        $entry = new BlacklistEntry();
        $entry->setType($type);
        $entry->setValue($value);
        $entry->setReason($reason);
        $entry->setCreatedBy($this->getUser());

        $em->persist($entry);
        $em->flush();

        $this->addFlash('success', '"' . $value . '" ajouté à la liste noire.');
        return $this->redirectToRoute('admin_blacklist_index');
    }

    #[Route('/bulk', name: 'bulk', methods: ['POST'])]
    public function addBulk(Request $request, EntityManagerInterface $em, BlacklistEntryRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('blacklist_bulk', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_blacklist_index');
        }

        $type  = $request->request->get('type');
        $lines = $request->request->get('values', '');
        $reason = trim((string) $request->request->get('reason')) ?: null;

        if (!in_array($type, [BlacklistEntry::TYPE_USERNAME, BlacklistEntry::TYPE_EMAIL_DOMAIN], true)) {
            $this->addFlash('error', 'Type invalide.');
            return $this->redirectToRoute('admin_blacklist_index');
        }

        $values = array_filter(array_map(fn($l) => strtolower(trim($l)), explode("\n", $lines)));
        $added  = 0;
        $skipped = 0;

        foreach ($values as $value) {
            if ($value === '') {
                continue;
            }

            if ($repo->isValueBlacklisted($type, $value)) {
                $skipped++;
                continue;
            }

            $entry = new BlacklistEntry();
            $entry->setType($type);
            $entry->setValue($value);
            $entry->setReason($reason);
            $entry->setCreatedBy($this->getUser());
            $em->persist($entry);
            $added++;
        }

        $em->flush();

        $this->addFlash('success', "$added entrée(s) ajoutée(s). $skipped doublon(s) ignoré(s).");
        return $this->redirectToRoute('admin_blacklist_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(BlacklistEntry $entry, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_' . $entry->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_blacklist_index');
        }

        $em->remove($entry);
        $em->flush();

        $this->addFlash('success', '"' . $entry->getValue() . '" supprimé de la liste noire.');
        return $this->redirectToRoute('admin_blacklist_index');
    }
}
