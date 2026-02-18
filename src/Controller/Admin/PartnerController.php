<?php

namespace App\Controller\Admin;

use App\Entity\Partner;
use App\Form\Admin\PartnerFormType;
use App\Repository\PartnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/partners', name: 'admin_partners_')]
#[IsGranted('ROLE_EDITEUR')]
class PartnerController extends AbstractController
{
    private const UPLOAD_DIR = 'uploads/partners';

    public function __construct(
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {
    }

    #[Route('', name: 'list')]
    public function list(PartnerRepository $repo): Response
    {
        return $this->render('admin/partners/list.html.twig', [
            'partners' => $repo->findAllForAdmin(),
        ]);
    }

    #[Route('/new', name: 'new')]
    #[IsGranted('partners.manage')]
    public function new(Request $request): Response
    {
        $partner = new Partner();
        $form = $this->createForm(PartnerFormType::class, $partner);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $dir = $this->projectDir . '/public/' . self::UPLOAD_DIR;
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $fileName = uniqid() . '.' . $logoFile->guessExtension();
                $logoFile->move($dir, $fileName);
                $partner->setLogoFileName($fileName);
            }

            $this->em->persist($partner);
            $this->em->flush();

            $this->addFlash('success', 'Partenaire créé avec succès.');
            return $this->redirectToRoute('admin_partners_list');
        }

        return $this->render('admin/partners/form.html.twig', [
            'partner' => null,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    #[IsGranted('partners.manage')]
    public function edit(Partner $partner, Request $request): Response
    {
        $form = $this->createForm(PartnerFormType::class, $partner);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                if ($partner->getLogoFileName()) {
                    $oldPath = $this->projectDir . '/public/' . self::UPLOAD_DIR . '/' . basename($partner->getLogoFileName());
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }

                $dir = $this->projectDir . '/public/' . self::UPLOAD_DIR;
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $fileName = uniqid() . '.' . $logoFile->guessExtension();
                $logoFile->move($dir, $fileName);
                $partner->setLogoFileName($fileName);
            }

            $this->em->flush();

            $this->addFlash('success', 'Partenaire modifié avec succès.');
            return $this->redirectToRoute('admin_partners_list');
        }

        return $this->render('admin/partners/form.html.twig', [
            'partner' => $partner,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('partners.manage')]
    public function delete(Partner $partner, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $partner->getId(), $request->request->get('_token'))) {
            if ($partner->getLogoFileName()) {
                $path = $this->projectDir . '/public/' . self::UPLOAD_DIR . '/' . basename($partner->getLogoFileName());
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            $this->em->remove($partner);
            $this->em->flush();
            $this->addFlash('success', 'Partenaire supprimé.');
        }

        return $this->redirectToRoute('admin_partners_list');
    }
}
