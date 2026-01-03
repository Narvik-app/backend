<?php

namespace App\Repository\Trait;

use App\Entity\Club;
use App\Entity\Interface\SortableEntityInterface;
use Doctrine\ORM\NonUniqueResultException;

trait SortableEntityRepositoryTrait {
  public function getLatestAvailableWeight(Club $club): int {
    $query = $this->createQueryBuilder('i')
                  ->andWhere('i.club = :club')
                  ->orderBy('i.weight', "DESC")
                  ->setParameter('club', $club)
                  ->setMaxResults(1)
                  ->getQuery();

    $lastWeight = null;
    try {
      $result = $query->getOneOrNullResult();
      if ($result) {
        $lastWeight = $result->getWeight();
      }
    } catch (\Exception) {
    }

    if (!$lastWeight) {
      $lastWeight = 0;
    }

    return $lastWeight + 1;
  }

  public function getUpperItem(Club $club, int $currentWeight): ?SortableEntityInterface {
    $query = $this->createQueryBuilder('i')
                  ->orderBy('i.weight', "DESC")
                  ->andWhere('i.weight < :weight')
                  ->andWhere('i.club = :club')
                  ->setParameter('weight', $currentWeight)
                  ->setParameter('club', $club)
                  ->setMaxResults(1)
                  ->getQuery();

    try {
      return $query->getOneOrNullResult();
    } catch (NonUniqueResultException) {
      return null;
    }
  }

  public function getLowerItem(Club $club, int $currentWeight): ?SortableEntityInterface {
    $query = $this->createQueryBuilder('i')
                  ->orderBy('i.weight', "ASC")
                  ->andWhere('i.weight > :weight')
                  ->andWhere('i.club = :club')
                  ->setParameter('weight', $currentWeight)
                  ->setParameter('club', $club)
                  ->setMaxResults(1)
                  ->getQuery();

    try {
      return $query->getOneOrNullResult();
    } catch (NonUniqueResultException) {
      return null;
    }
  }

  public function moveUp(Club $club, SortableEntityInterface $itemToMove): void {
    // We store the old weight since we make a swap
    $itemWeight = $itemToMove->getWeight();

    $targetItem = $this->getUpperItem($club, $itemWeight);
    // No item found, we do nothing
    if (!$targetItem) {
      return;
    }

    // We swap the weights
    $itemToMove->setWeight($targetItem->getWeight());
    $targetItem->setWeight($itemWeight);

    $this->getEntityManager()->persist($itemToMove);
    $this->getEntityManager()->persist($targetItem);
    $this->getEntityManager()->flush();
  }

  public function moveDown(Club $club, SortableEntityInterface $itemToMove): void {
    // We store the old weight since we make a swap
    $itemWeight = $itemToMove->getWeight();

    $targetItem = $this->getLowerItem($club, $itemWeight);
    // No item found, we do nothing
    if (!$targetItem) {
      return;
    }

    // We swap the weights
    $itemToMove->setWeight($targetItem->getWeight());
    $targetItem->setWeight($itemWeight);

    $this->getEntityManager()->persist($itemToMove);
    $this->getEntityManager()->persist($targetItem);
    $this->getEntityManager()->flush();
  }

  public function reorder(Club $club, array $uuids): void {
    if (empty($uuids)) {
      return;
    }

    $qb = $this->createQueryBuilder('i');
    $entities = $qb
      ->where('i.club = :club')
      ->andWhere($qb->expr()->in('i.uuid', ':uuids'))
      ->setParameter('club', $club)
      ->setParameter('uuids', $uuids)
      ->getQuery()
      ->getResult();

    if (count($entities) === 0) {
      return;
    }

    $originalWeights = [];
    foreach ($entities as $entity) {
      $originalWeights[] = $entity->getWeight();
    }
    sort($originalWeights);

    $entitiesByUuid = [];
    foreach ($entities as $entity) {
      $entitiesByUuid[(string)$entity->getUuid()] = $entity;
    }

    $entityManager = $this->getEntityManager();
    foreach (array_values($uuids) as $i => $uuid) {
      if (isset($entitiesByUuid[$uuid])) {
        $entity = $entitiesByUuid[$uuid];
        if (isset($originalWeights[$i])) {
            $entity->setWeight($originalWeights[$i]);
            $entityManager->persist($entity);
        }
      }
    }

    $entityManager->flush();
  }
}
