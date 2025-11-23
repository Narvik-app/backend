<?php

namespace App\Repository\ClubDependent\Plugin\Sale;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Sale\InventoryCategory;
use App\Entity\ClubDependent\Plugin\Sale\InventoryItem;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Interface\SortableRepositoryInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\SortableEntityRepositoryTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryCategory>
 *
 * @method InventoryCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryCategory[]    findAll()
 * @method InventoryCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryCategoryRepository extends ServiceEntityRepository implements SortableRepositoryInterface, ClubLinkedInterface {
  use SortableEntityRepositoryTrait;
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, InventoryCategory::class);
  }

  public function findOneByName(Club $club, string $name): ?InventoryCategory {
    $qb = $this->createQueryBuilder('i');
    $this->applyClubRestriction($qb, $club);
    $query = $qb
      ->andWhere($qb->expr()->eq((string) $qb->expr()->lower('i.name'), (string) $qb->expr()->lower(':name')))
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
  //     * @return InventoryCategory[] Returns an array of InventoryCategory objects
  //     */
  //    public function findByExampleField($value): array
  //    {
  //        return $this->createQueryBuilder('i')
  //            ->andWhere('i.exampleField = :val')
  //            ->setParameter('val', $value)
  //            ->orderBy('i.id', 'ASC')
  //            ->setMaxResults(10)
  //            ->getQuery()
  //            ->getResult()
  //        ;
  //    }

  //    public function findOneBySomeField($value): ?InventoryCategory
  //    {
  //        return $this->createQueryBuilder('i')
  //            ->andWhere('i.exampleField = :val')
  //            ->setParameter('val', $value)
  //            ->getQuery()
  //            ->getOneOrNullResult()
  //        ;
  //    }
}
