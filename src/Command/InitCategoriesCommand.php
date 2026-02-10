<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\GameCategory;
use App\Repository\CategoryRepository;
use App\Repository\GameCategoryRepository;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-categories',
    description: 'Initialiser les categories et sous-categories par defaut',
)]
class InitCategoriesCommand extends Command
{
    private const DEFAULT_CATEGORIES = [
        [
            'name' => 'Serveurs de jeux',
            'icon' => 'fas fa-gamepad',
            'position' => 0,
            'description' => 'Classement des serveurs de jeux par type',
            'games' => [
                ['name' => 'Minecraft', 'position' => 0],
                ['name' => 'Rust', 'position' => 1],
                ['name' => 'ARK: Survival Evolved', 'position' => 2],
                ['name' => 'Garry\'s Mod', 'position' => 3],
                ['name' => 'FiveM / GTA RP', 'position' => 4],
                ['name' => 'Counter-Strike 2', 'position' => 5],
                ['name' => 'Palworld', 'position' => 6],
                ['name' => 'Unturned', 'position' => 7],
            ],
        ],
        [
            'name' => 'Forum communautaire',
            'icon' => 'fas fa-comments',
            'position' => 1,
            'description' => 'Forums et communautes de joueurs',
            'games' => [],
        ],
        [
            'name' => 'Serveur vocal',
            'icon' => 'fas fa-headset',
            'position' => 2,
            'description' => 'Serveurs vocaux TeamSpeak, Mumble, etc.',
            'games' => [],
        ],
        [
            'name' => 'Hebergement web',
            'icon' => 'fas fa-globe',
            'position' => 3,
            'description' => 'Hebergeurs web',
            'games' => [],
        ],
        [
            'name' => 'Hebergement VPS',
            'icon' => 'fas fa-cloud',
            'position' => 4,
            'description' => 'Serveurs prives virtuels',
            'games' => [],
        ],
        [
            'name' => 'Hebergement serveur dedie',
            'icon' => 'fas fa-server',
            'position' => 5,
            'description' => 'Serveurs dedies',
            'games' => [],
        ],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $categoryRepo,
        private GameCategoryRepository $gameCategoryRepo,
        private SlugService $slugService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $createdCategories = 0;
        $createdGames = 0;

        foreach (self::DEFAULT_CATEGORIES as $data) {
            $slug = $this->slugService->slugify($data['name']);
            $category = $this->categoryRepo->findOneBy(['slug' => $slug]);

            if (!$category) {
                $category = new Category();
                $category->setName($data['name']);
                $category->setSlug($slug);
                $category->setIcon($data['icon']);
                $category->setDescription($data['description']);
                $category->setPosition($data['position']);
                $category->setIsActive(true);
                $this->em->persist($category);
                $createdCategories++;
            }

            foreach ($data['games'] as $gameData) {
                $gameSlug = $this->slugService->slugify($gameData['name']);
                $existing = $this->gameCategoryRepo->findOneBy(['slug' => $gameSlug]);

                if (!$existing) {
                    $game = new GameCategory();
                    $game->setName($gameData['name']);
                    $game->setSlug($gameSlug);
                    $game->setPosition($gameData['position']);
                    $game->setIsActive(true);
                    $game->setCategory($category);
                    $this->em->persist($game);
                    $createdGames++;
                } elseif ($existing->getCategory() === null) {
                    $existing->setCategory($category);
                    $createdGames++;
                }
            }
        }

        $this->em->flush();

        $io->success("$createdCategories categorie(s) parente(s) creee(s), $createdGames sous-categorie(s) creee(s)/assignee(s).");

        return Command::SUCCESS;
    }
}
