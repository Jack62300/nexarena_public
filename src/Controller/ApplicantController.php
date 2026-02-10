<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\RecruitmentApplication;
use App\Entity\RecruitmentMessage;
use App\Repository\RecruitmentApplicationRepository;
use App\Repository\RecruitmentMessageRepository;
use App\Repository\ServerCollaboratorRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ApplicantController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
        private ServerCollaboratorRepository $collabRepo,
    ) {
    }

    #[Route('/mes-candidatures', name: 'applicant_list')]
    public function list(RecruitmentApplicationRepository $appRepo): Response
    {
        $applications = $appRepo->createQueryBuilder('a')
            ->leftJoin('a.listing', 'l')
            ->addSelect('l')
            ->leftJoin('l.server', 's')
            ->addSelect('s')
            ->where('a.applicantUser = :user')
            ->setParameter('user', $this->getUser())
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('applicant/list.html.twig', [
            'applications' => $applications,
        ]);
    }

    private function isApplicant(RecruitmentApplication $application): bool
    {
        $user = $this->getUser();
        return $application->getApplicantUser() && $application->getApplicantUser()->getId() === $user->getId();
    }

    #[Route('/mes-candidatures/{id}', name: 'applicant_show', requirements: ['id' => '\d+'])]
    public function show(int $id, RecruitmentApplicationRepository $appRepo): Response
    {
        $application = $appRepo->find($id);
        if (!$application || !$this->isApplicant($application)) {
            throw $this->createNotFoundException('Candidature introuvable.');
        }

        return $this->render('applicant/show.html.twig', [
            'application' => $application,
            'listing' => $application->getListing(),
        ]);
    }

    // ── Chat API (applicant side) ──

    #[Route('/api/applicant/chat/{appId}/messages', name: 'api_applicant_chat_messages', methods: ['GET'], requirements: ['appId' => '\d+'])]
    public function chatMessages(int $appId, Request $request, RecruitmentApplicationRepository $appRepo, RecruitmentMessageRepository $msgRepo): JsonResponse
    {
        $application = $appRepo->find($appId);
        if (!$application || !$this->isApplicant($application)) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        if (!$application->isChatEnabled()) {
            return new JsonResponse(['error' => 'Chat disabled'], 403);
        }

        $afterId = (int) $request->query->get('after', 0);
        $messages = $afterId > 0
            ? $msgRepo->findNewMessages($application, $afterId)
            : $msgRepo->findByApplication($application);

        return new JsonResponse($this->formatMessages($messages));
    }

    #[Route('/api/applicant/chat/{appId}/send', name: 'api_applicant_chat_send', methods: ['POST'], requirements: ['appId' => '\d+'])]
    public function chatSend(int $appId, Request $request, RecruitmentApplicationRepository $appRepo): JsonResponse
    {
        $application = $appRepo->find($appId);
        if (!$application || !$this->isApplicant($application)) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        if (!$application->isChatEnabled()) {
            return new JsonResponse(['error' => 'Chat disabled'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > 2000) {
            return new JsonResponse(['error' => 'Message invalide (1-2000 caracteres)'], 422);
        }

        $msg = new RecruitmentMessage();
        $msg->setApplication($application);
        $msg->setSender($this->getUser());
        $msg->setContent($content);
        $this->em->persist($msg);
        $this->em->flush();

        // Notify server owner + collabs with manage_recruitment
        $listing = $application->getListing();
        $server = $listing->getServer();
        $owner = $server->getOwner();

        $currentUserId = $this->getUser()->getId();

        if ($owner && $owner->getId() !== $currentUserId) {
            $this->notificationService->create(
                $owner,
                Notification::TYPE_NEW_MESSAGE,
                'Nouveau message',
                $application->getApplicantName() . ' a envoye un message pour "' . $listing->getTitle() . '".',
                $this->generateUrl('user_recruitment_application_detail', ['id' => $listing->getId(), 'appId' => $application->getId()])
            );
        }

        $collabs = $this->collabRepo->findBy(['server' => $server]);
        foreach ($collabs as $collab) {
            if ($collab->hasPermission('manage_recruitment') && $collab->getUser()->getId() !== $currentUserId && $collab->getUser() !== $owner) {
                $this->notificationService->create(
                    $collab->getUser(),
                    Notification::TYPE_NEW_MESSAGE,
                    'Nouveau message',
                    $application->getApplicantName() . ' a envoye un message pour "' . $listing->getTitle() . '".',
                    $this->generateUrl('user_recruitment_application_detail', ['id' => $listing->getId(), 'appId' => $application->getId()])
                );
            }
        }

        return new JsonResponse($this->formatMessage($msg));
    }

    private function formatMessages(array $messages): array
    {
        return array_map(fn(RecruitmentMessage $m) => $this->formatMessage($m), $messages);
    }

    private function formatMessage(RecruitmentMessage $m): array
    {
        return [
            'id' => $m->getId(),
            'senderId' => $m->getSender()->getId(),
            'senderName' => $m->getSender()->getUsername(),
            'senderAvatar' => $m->getSender()->getAvatar(),
            'content' => $m->getContent(),
            'createdAt' => $m->getCreatedAt()->format('d/m/Y H:i'),
        ];
    }
}
