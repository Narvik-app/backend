<?php

namespace App\Repository\ClubDependent\Plugin\Sale;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentMode;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Interface\SortableRepositoryInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\SortableEntityRepositoryTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalePaymentMode>
 */
class SalePaymentModeRepository extends ServiceEntityRepository implements SortableRepositoryInterface, ClubLinkedInterface {
  use SortableEntityRepositoryTrait;
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, SalePaymentMode::class);
  }

  public function findOneByName(Club $club, string $name): ?SalePaymentMode {
    $qb = $this->createQueryBuilder('s');
    $this->applyClubRestriction($qb, $club);
    $query = $qb
      ->andWhere($qb->expr()->eq((string) $qb->expr()->lower('s.name'), (string) $qb->expr()->lower(':name')))
      ->setParameter('name', $name)
      ->setMaxResults(1)
      ->getQuery();

    try {
      return $query->getOneOrNullResult();
    }
    catch (\Exception) {
      return null;
    }
  }

  //    /**
  //     * @return SalePaymentMode[] Returns an array of SalePaymentMode objects
  //     */
  //    public function findByExampleField($value): array
  //    {
  //        return $this->createQueryBuilder('s')
  //            ->andWhere('s.exampleField = :val')
  //            ->setParameter('val', $value)
  //            ->orderBy('s.id', 'ASC')
  //            ->setMaxResults(10)
  //            ->getQuery()
  //            ->getResult()
  //        ;
  //    }

  //    public function findOneBySomeField($value): ?SalePaymentMode
  //    {
  //        return $this->createQueryBuilder('s')
  //            ->andWhere('s.exampleField = :val')
  //            ->setParameter('val', $value)
  //            ->getQuery()
  //            ->getOneOrNullResult()
  //        ;
  //    }
}
