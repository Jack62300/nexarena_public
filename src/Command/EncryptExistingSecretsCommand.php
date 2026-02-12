<?php

namespace App\Command;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:encrypt-secrets',
    description: 'Chiffrer les valeurs en clair des settings de type secret',
)]
class EncryptExistingSecretsCommand extends Command
{
    public function __construct(
        private SettingRepository $settingRepo,
        private EncryptionService $encryptionService,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $settings = $this->settingRepo->findBy(['type' => Setting::TYPE_SECRET]);

        $encrypted = 0;
        $skipped = 0;

        foreach ($settings as $setting) {
            $value = $setting->getValue();

            if ($value === null || $value === '') {
                $skipped++;
                continue;
            }

            if ($this->encryptionService->isEncrypted($value)) {
                $io->note("Deja chiffre: {$setting->getKey()}");
                $skipped++;
                continue;
            }

            $setting->setValue($this->encryptionService->encrypt($value));
            $encrypted++;
            $io->text("Chiffre: {$setting->getKey()}");
        }

        $this->em->flush();

        $io->success("$encrypted secret(s) chiffre(s), $skipped ignore(s).");

        return Command::SUCCESS;
    }
}
