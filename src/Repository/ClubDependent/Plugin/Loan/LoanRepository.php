<?php

namespace App\Repository\ClubDependent\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Loan>
 */
class LoanRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Loan::class);
  }

  /**
   * Count all borrow events for a given item.
   */
  public function countByItem(LoanItem $item): int {
    return (int) $this->createQueryBuilder('l')
      ->select('COUNT(l.id)')
      ->andWhere('l.loanItem = :item')
      ->setParameter('item', $item)
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Count currently open (not returned) borrow events for a given item.
   */
  public function countOpenByItem(LoanItem $item): int {
    return (int) $this->createQueryBuilder('l')
      ->select('COUNT(l.id)')
      ->andWhere('l.loanItem = :item')
      ->andWhere('l.endDate IS NULL')
      ->setParameter('item', $item)
      ->getQuery()
      ->getSingleScalarResult();
  }
}
