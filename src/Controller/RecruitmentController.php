<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\RecruitmentApplication;
use App\Entity\RecruitmentListing;
use App\Repository\CategoryRepository;
use App\Repository\RecruitmentApplicationRepository;
use App\Repository\RecruitmentListingRepository;
use App\Repository\ServerCollaboratorRepository;
use App\Service\NotificationService;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RecruitmentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
        private ServerCollaboratorRepository $collabRepo,
        private WebhookService $webhookService,
        private RecruitmentApplicationRepository $applicationRepo,
    ) {
    }

    #[Route('/recrutement', name: 'recruitment_index')]
    public function index(Request $request, RecruitmentListingRepository $repo, CategoryRepository $categoryRepo): Response
    {
        $categoryId = $request->query->get('category');
        $category = $categoryId ? $categoryRepo->find((int) $categoryId) : null;

        $listings = $repo->findPubliclyVisible($category);
        $categories = $categoryRepo->findBy(['isActive' => true], ['position' => 'ASC']);

        return $this->render('recruitment/index.html.twig', [
            'listings' => $listings,
            'categories' => $categories,
            'currentCategory' => $categoryId,
        ]);
    }

    #[Route('/recrutement/{slug}', name: 'recruitment_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    public function show(string $slug, RecruitmentListingRepository $repo): Response
    {
        $listing = $repo->findOneBy(['slug' => $slug]);

        if (!$listing || !$listing->isPubliclyVisible()) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $alreadyApplied = false;
        if ($this->getUser()) {
            $alreadyApplied = $this->applicationRepo->findByListingAndUser($listing, $this->getUser()) !== null;
        }

        return $this->render('recruitment/show.html.twig', [
            'listing' => $listing,
            'alreadyApplied' => $alreadyApplied,
        ]);
    }

    #[Route('/recrutement/{slug}/postuler', name: 'recruitment_apply', methods: ['POST'], requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    #[IsGranted('ROLE_USER')]
    public function apply(string $slug, Request $request, RecruitmentListingRepository $repo): Response
    {
        $listing = $repo->findOneBy(['slug' => $slug]);

        if (!$listing || !$listing->isPubliclyVisible()) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        // Block duplicate application
        if ($this->applicationRepo->findByListingAndUser($listing, $this->getUser()) !== null) {
            $this->addFlash('error', 'Vous avez deja postule a cette annonce.');
            return $this->redirectToRoute('recruitment_show', ['slug' => $slug]);
        }

        if (!$this->isCsrfTokenValid('recruitment_apply_' . $listing->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('recruitment_show', ['slug' => $slug]);
        }

        // Validate applicant info
        $name = trim((string) $request->request->get('applicant_name'));
        $email = trim((string) $request->request->get('applicant_email'));

        if ($name === '' || mb_strlen($name) > 255) {
            $this->addFlash('error', 'Veuillez indiquer votre nom.');
            return $this->redirectToRoute('recruitment_show', ['slug' => $slug]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $this->addFlash('error', 'Veuillez indiquer un email valide.');
            return $this->redirectToRoute('recruitment_show', ['slug' => $slug]);
        }

        // Collect responses
        $formFields = $listing->getFormFields();
        $responses = [];
        $hasError = false;

        foreach ($formFields as $index => $field) {
            $value = $request->request->get('field_' . $index, '');

            if (is_array($value)) {
                $value = array_map('trim', $value);
                $value = array_filter($value, fn($v) => $v !== '');
            } else {
                $value = trim((string) $value);
            }

            // Required validation
            if (!empty($field['required'])) {
                $empty = is_array($value) ? empty($value) : ($value === '');
                if ($empty) {
                    $this->addFlash('error', 'Le champ "' . ($field['label'] ?? 'Champ ' . $index) . '" est obligatoire.');
                    $hasError = true;
                    break;
                }
            }

            $responses[(string) $index] = $value;
        }

        if ($hasError) {
            return $this->redirectToRoute('recruitment_show', ['slug' => $slug]);
        }

        $application = new RecruitmentApplication();
        $application->setListing($listing);
        $application->setApplicantName($name);
        $application->setApplicantEmail($email);
        $application->setResponses($responses);

        if ($this->getUser()) {
            $application->setApplicantUser($this->getUser());
        }

        $this->em->persist($application);
        $this->em->flush();

        $this->webhookService->dispatch('recruitment.application', [
            'title' => 'Nouvelle candidature',
            'fields' => [
                ['name' => 'Annonce', 'value' => $listing->getTitle(), 'inline' => true],
                ['name' => 'Candidat', 'value' => $name, 'inline' => true],
                ['name' => 'Serveur', 'value' => $listing->getServer()?->getName() ?? 'Annonce libre', 'inline' => true],
            ],
        ]);

        // Notify listing author + collabs with manage_recruitment
        $server = $listing->getServer();
        $author = $listing->getAuthor();
        $notifLink = $this->generateUrl('user_recruitment_application_detail', [
            'id' => $listing->getId(),
            'appId' => $application->getId(),
        ]);

        if ($author) {
            $this->notificationService->create(
                $author,
                Notification::TYPE_NEW_APPLICATION,
                'Nouvelle candidature',
                $name . ' a postule pour "' . $listing->getTitle() . '".',
                $notifLink
            );
        }

        if ($server) {
            $collabs = $this->collabRepo->findBy(['server' => $server]);
            foreach ($collabs as $collab) {
                if ($collab->hasPermission('manage_recruitment') && $collab->getUser() !== $author) {
                    $this->notificationService->create(
                        $collab->getUser(),
                        Notification::TYPE_NEW_APPLICATION,
                        'Nouvelle candidature',
                        $name . ' a postule pour "' . $listing->getTitle() . '".',
                        $notifLink
                    );
                }
            }
        }

        $this->addFlash('success', 'Votre candidature a ete envoyee avec succes !');
        return $this->redirectToRoute('recruitment_show', ['slug' => $slug]);
    }
}
