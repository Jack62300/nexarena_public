<?php

namespace App\Command;

use App\Entity\DiscordAnnouncement;
use App\Repository\DiscordAnnouncementRepository;
use App\Service\DiscordBotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-scheduled-announcements',
    description: 'Envoyer les annonces Discord programmees',
)]
class SendScheduledAnnouncementsCommand extends Command
{
    public function __construct(
        private DiscordAnnouncementRepository $announcementRepo,
        private DiscordBotService $botService,
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $announcements = $this->announcementRepo->findScheduledReady();

        if (empty($announcements)) {
            $io->info('Aucune annonce a envoyer.');
            return Command::SUCCESS;
        }

        $sent = 0;
        foreach ($announcements as $ann) {
            $payload = $this->buildPayload($ann);
            $result = $this->botService->sendAnnouncement($ann->getChannelId(), $payload);

            if ($result && isset($result['messageId'])) {
                $ann->setSentAt(new \DateTimeImmutable());
                $ann->setDiscordMessageId($result['messageId']);
                $sent++;
                $io->success("Annonce #{$ann->getId()} envoyee.");
            } else {
                $io->warning("Echec envoi annonce #{$ann->getId()}.");
            }
        }

        $this->em->flush();
        $io->success("$sent annonce(s) envoyee(s).");

        return Command::SUCCESS;
    }

    private function buildPayload(DiscordAnnouncement $ann): array
    {
        $publicDir = $this->projectDir . '/public';

        $payload = [
            'title' => $ann->getTitle(),
            'content' => $ann->getContent(),
            'color' => $ann->getEmbedColor(),
            'type' => $ann->getType(),
        ];

        $imageUrl = $ann->getImageUrl();
        if ($imageUrl && str_starts_with($imageUrl, '/uploads/')) {
            $filePath = $publicDir . $imageUrl;
            if (file_exists($filePath)) {
                $payload['imageBase64'] = base64_encode(file_get_contents($filePath));
                $payload['imageName'] = basename($filePath);
            }
        } elseif ($imageUrl) {
            $payload['imageUrl'] = $imageUrl;
        }

        if ($ann->getEmbedData()) {
            $embedData = $ann->getEmbedData();
            if (!empty($embedData['thumbnailFile'])) {
                $thumbPath = $publicDir . '/uploads/announcements/' . basename($embedData['thumbnailFile']);
                if (file_exists($thumbPath)) {
                    $embedData['thumbnailBase64'] = base64_encode(file_get_contents($thumbPath));
                    $embedData['thumbnailName'] = basename($thumbPath);
                }
            }
            $payload['embedData'] = $embedData;
        }

        return $payload;
    }
}
