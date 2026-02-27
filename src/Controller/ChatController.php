<?php

namespace App\Controller;

use App\Repository\RecruitmentApplicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    /**
     * Returns all active recruitment chat conversations for the current user.
     * Used by the global bottom chat bar.
     */
    #[Route('/api/chat/conversations', name: 'api_chat_conversations', methods: ['GET'])]
    public function conversations(RecruitmentApplicationRepository $appRepo): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user   = $this->getUser();
        $result = [];
        $seen   = [];

        // As manager (server owner or collaborator)
        foreach ($appRepo->findActiveChatsByManager($user) as $app) {
            $seen[$app->getId()] = true;

            $applicantUser = $app->getApplicantUser();
            $contactName   = $applicantUser ? $applicantUser->getUsername() : $app->getApplicantName();
            $contactAvatar = $applicantUser ? $applicantUser->getAvatar() : null;

            $result[] = [
                'appId'         => $app->getId(),
                'role'          => 'manager',
                'contactName'   => $contactName,
                'contactAvatar' => $contactAvatar,
                'listingTitle'  => $app->getListing()->getTitle(),
                'serverName'    => $app->getListing()->getServer()?->getName() ?? 'Annonce libre',
                'messagesUrl'   => $this->generateUrl('api_recruitment_chat_messages', ['appId' => $app->getId()]),
                'sendUrl'       => $this->generateUrl('api_recruitment_chat_send',     ['appId' => $app->getId()]),
            ];
        }

        // As applicant
        foreach ($appRepo->findActiveChatsByApplicant($user) as $app) {
            if (isset($seen[$app->getId()])) {
                continue;
            }

            $author = $app->getListing()->getAuthor();

            $result[] = [
                'appId'         => $app->getId(),
                'role'          => 'applicant',
                'contactName'   => $author ? $author->getUsername() : 'Gestionnaire',
                'contactAvatar' => $author ? $author->getAvatar() : null,
                'listingTitle'  => $app->getListing()->getTitle(),
                'serverName'    => $app->getListing()->getServer()?->getName() ?? 'Annonce libre',
                'messagesUrl'   => $this->generateUrl('api_applicant_chat_messages', ['appId' => $app->getId()]),
                'sendUrl'       => $this->generateUrl('api_applicant_chat_send',     ['appId' => $app->getId()]),
            ];
        }

        return new JsonResponse($result);
    }
}
