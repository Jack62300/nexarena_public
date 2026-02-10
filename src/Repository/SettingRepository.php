<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /**
     * @return array<string, Setting[]>
     */
    public function findAllGroupedByCategory(): array
    {
        $settings = $this->findBy([], ['category' => 'ASC', 'position' => 'ASC']);
        $grouped = [];
        foreach ($settings as $setting) {
            $grouped[$setting->getCategory()][] = $setting;
        }

        return $grouped;
    }

    public function findByKey(string $key): ?Setting
    {
        return $this->findOneBy(['key' => $key]);
    }
}
