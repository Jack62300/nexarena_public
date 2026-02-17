<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\GameCategory;
use App\Repository\CategoryRepository;
use App\Repository\GameCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-game-categories',
    description: 'Importe les jeux (sous-catégories) depuis la liste top-serveurs.net',
)]
class ImportGameCategoriesCommand extends Command
{
    /**
     * Liste complète des jeux scrapés depuis top-serveurs.net
     * format : [name, slug, image_filename]
     */
    private const GAMES = [
        ['GTA',                    'gta',               'gta.jpg'],
        ['Minecraft',              'minecraft',          'minecraft.png'],
        ['Hytale',                 'hytale',             'hytale.jpg'],
        ['Discord',                'discord',            'discord.png'],
        ["Garry's Mod",            'garrys-mod',         'gmod.png'],
        ['DayZ',                   'dayz',               'dayz.jpg'],
        ['Roblox',                 'roblox',             'roblox.jpg'],
        ['ARK Survival Evolved',   'ark',                'ark.png'],
        ['Rust',                   'rust',               'rust.png'],
        ['Arma 3',                 'arma3',              'arma3.jpg'],
        ['ARK Survival Ascended',  'arksa',              'arksa.jpg'],
        ['Arma Reforger',          'arma-reforger',      'arma-reforger.jpg'],
        ['Conan Exiles',           'conan-exiles',       'conan-exiles.jpg'],
        ['Nova Life',              'nova-life',          'nova-life.jpg'],
        ['Scum',                   'scum',               'scum.jpg'],
        ['Red Dead Redemption 2',  'rdr2',               'rdr.jpg'],
        ['Minecraft Bedrock',      'minecraft-bedrock',  'minecraft-bedrock.jpg'],
        ['Multigaming',            'multigaming',        'multigaming.jpg'],
        ['Palworld',               'palworld',           'palworld.png'],
        ['Project Zomboid',        'project-zomboid',    'project-zomboid.jpg'],
        ['7 Days To Die',          '7-days-to-die',      '7-days-to-die.jpg'],
        ['Counter-Strike',         'counter-strike',     'cs2.png'],
        ['Eco',                    'eco',                'eco.jpg'],
        ['Deadside',               'deadside',           'deadside.jpg'],
        ['V Rising',               'v-rising',           'v-rising.jpg'],
        ['Unturned',               'unturned',           'unturned.png'],
        ['Soulmask',               'soulmask',           'soulmask.jpg'],
        ['Space Engineers',        'space-engineers',    'space-engineers.jpg'],
        ['The Front',              'the-front',          'the-front.jpg'],
        ['Valheim',                'valheim',            'valheim.jpg'],
        ['S&Box',                  'sandbox',            'sandbox.jpg'],
        ['Hell Let Loose',         'hell-let-loose',     'hell-let-loose.jpg'],
        ['Atlas',                  'atlas',              'atlas.jpg'],
        ['The Isle',               'the-isle',           'the-isle.jpg'],
        ['Dune Awakening',         'dune-awakening',     'dune-awakening.jpg'],
        ['Empyrion',               'empyrion',           'empyrion.jpg'],
        ['Squad',                  'squad',              'squad.jpg'],
        ['Enshrouded',             'enshrouded',         'enshrouded.jpg'],
        ['Vein',                   'vein',               'vein.jpg'],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $categoryRepository,
        private GameCategoryRepository $gameCategoryRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'category-id',
                null,
                InputOption::VALUE_OPTIONAL,
                'ID de la catégorie parente (Category) à associer aux jeux',
                null
            )
            ->addOption(
                'skip-existing',
                null,
                InputOption::VALUE_NONE,
                'Passe silencieusement les jeux déjà existants (par slug)'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simule l\'import sans écrire en base'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Import des sous-catégories de jeux (top-serveurs.net)');

        $dryRun      = $input->getOption('dry-run');
        $skipExisting = $input->getOption('skip-existing');
        $categoryId  = $input->getOption('category-id');

        // ── Sélection de la catégorie parente ────────────────────────────
        $parentCategory = null;

        if ($categoryId !== null) {
            if ((int) $categoryId > 0) {
                $parentCategory = $this->categoryRepository->find((int) $categoryId);
                if (!$parentCategory) {
                    $io->error("Catégorie parente introuvable (id=$categoryId).");
                    return Command::FAILURE;
                }
            }
            // categoryId=0 → pas de catégorie parente
        } else {
            $categories = $this->categoryRepository->findAll();

            if (empty($categories)) {
                $io->warning('Aucune catégorie parente trouvée. Les jeux seront importés sans catégorie parente.');
            } else {
                $choices = ['0' => 'Aucune (null)'];
                foreach ($categories as $cat) {
                    $choices[(string) $cat->getId()] = $cat->getName() . ' (id=' . $cat->getId() . ')';
                }

                $choice = $io->choice(
                    'Choisissez la catégorie parente à associer aux jeux',
                    array_values($choices),
                    'Aucune (null)'
                );

                $choiceId = array_search($choice, $choices);
                if ($choiceId !== '0' && $choiceId !== false) {
                    $parentCategory = $this->categoryRepository->find((int) $choiceId);
                }
            }
        }

        if ($parentCategory) {
            $io->info('Catégorie parente : ' . $parentCategory->getName());
        } else {
            $io->info('Pas de catégorie parente sélectionnée.');
        }

        if ($dryRun) {
            $io->note('Mode DRY-RUN activé — aucune modification en base.');
        }

        // ── Import ───────────────────────────────────────────────────────
        $created = 0;
        $skipped = 0;
        $updated = 0;

        $io->section('Import des ' . count(self::GAMES) . ' jeux');

        $rows = [];

        foreach (self::GAMES as $position => [$name, $slug, $image]) {
            $existing = $this->gameCategoryRepository->findOneBy(['slug' => $slug]);

            if ($existing) {
                if ($skipExisting) {
                    $rows[] = [$name, $slug, $image, '<comment>ignoré</comment>'];
                    $skipped++;
                    continue;
                }

                // Mise à jour de l'image et de la catégorie si manquants
                $changed = false;
                if (!$existing->getImage()) {
                    $existing->setImage($image);
                    $changed = true;
                }
                if ($parentCategory && !$existing->getCategory()) {
                    $existing->setCategory($parentCategory);
                    $changed = true;
                }

                if ($changed) {
                    if (!$dryRun) {
                        $this->em->persist($existing);
                    }
                    $rows[] = [$name, $slug, $image, '<info>mis à jour</info>'];
                    $updated++;
                } else {
                    $rows[] = [$name, $slug, $image, '<comment>déjà à jour</comment>'];
                    $skipped++;
                }

                continue;
            }

            // Création
            $gc = new GameCategory();
            $gc->setName($name);
            $gc->setSlug($slug);
            $gc->setImage($image);
            $gc->setIsActive(true);
            $gc->setPosition($position + 1);

            if ($parentCategory) {
                $gc->setCategory($parentCategory);
            }

            if (!$dryRun) {
                $this->em->persist($gc);
            }

            $rows[] = [$name, $slug, $image, '<info>créé</info>'];
            $created++;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        // ── Résumé ───────────────────────────────────────────────────────
        $io->table(['Jeu', 'Slug', 'Image', 'Statut'], $rows);

        $io->success(sprintf(
            '%d créé(s), %d mis à jour, %d ignoré(s)%s.',
            $created,
            $updated,
            $skipped,
            $dryRun ? ' [DRY-RUN]' : ''
        ));

        return Command::SUCCESS;
    }
}
