<?php

namespace App\Controller\Admin;

use App\Entity\Partner;
use App\Repository\PartnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/partners', name: 'admin_partners_')]
#[IsGranted('ROLE_EDITEUR')]
class PartnerController extends AbstractController
{
    private const UPLOAD_DIR = 'uploads/partners';
    private const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/webp'];

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
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('partner_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_partners_new');
            }

            $partner = new Partner();
            $this->handleForm($partner, $request);

            $this->em->persist($partner);
            $this->em->flush();

            $this->addFlash('success', 'Partenaire cree avec succes.');
            return $this->redirectToRoute('admin_partners_list');
        }

        return $this->render('admin/partners/form.html.twig', [
            'partner' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    #[IsGranted('partners.manage')]
    public function edit(Partner $partner, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('partner_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_partners_edit', ['id' => $partner->getId()]);
            }

            $this->handleForm($partner, $request);
            $this->em->flush();

            $this->addFlash('success', 'Partenaire modifie avec succes.');
            return $this->redirectToRoute('admin_partners_list');
        }

        return $this->render('admin/partners/form.html.twig', [
            'partner' => $partner,
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
            $this->addFlash('success', 'Partenaire supprime.');
        }

        return $this->redirectToRoute('admin_partners_list');
    }

    private function handleForm(Partner $partner, Request $request): void
    {
        $partner->setName($request->request->get('name', ''));
        $partner->setUrl($request->request->get('url', ''));
        $partner->setType($request->request->get('type', 'partner'));
        $partner->setPosition((int) $request->request->get('position', 0));
        $partner->setIsActive($request->request->getBoolean('is_active'));

        /** @var UploadedFile|null $logo */
        $logo = $request->files->get('logo');
        if ($logo && in_array($logo->getMimeType(), self::ALLOWED_MIMES, true)) {
            // Delete old logo
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

            $fileName = uniqid() . '.' . $logo->guessExtension();
            $logo->move($dir, $fileName);
            $partner->setLogoFileName($fileName);
        }
    }
}
