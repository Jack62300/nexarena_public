<?php

namespace App\Repository;

use App\Entity\BannedWord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BannedWord> */
class BannedWordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BannedWord::class);
    }

    /** @return BannedWord[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.word', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return string[] */
    public function findAllWords(): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('b.word')
            ->getQuery()
            ->getSingleColumnResult();

        return $rows;
    }

    public function findByWord(string $word): ?BannedWord
    {
        return $this->findOneBy(['word' => mb_strtolower(trim($word))]);
    }
}
