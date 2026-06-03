<?php

namespace App\Repository\ClubDependent\Plugin\Sale;

use App\Entity\ClubDependent\Plugin\Sale\InventoryItem;
use App\Entity\ClubDependent\Plugin\Sale\InventoryItemHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryItemHistory>
 */
class InventoryItemHistoryRepository extends ServiceEntityRepository {
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, InventoryItemHistory::class);
  }

  /** @return InventoryItemHistory[] */
  public function findBetweenDates(InventoryItem $item, \DateTimeInterface $start, \DateTimeInterface $end): array {
    return $this->createQueryBuilder('h')
      ->where('h.item = :item')
      ->andWhere('h.createdAt > :start')
      ->andWhere('h.createdAt < :end')
      ->setParameter('item', $item)
      ->setParameter('start', $start)
      ->setParameter('end', $end)
      ->getQuery()
      ->getResult();
  }
}
