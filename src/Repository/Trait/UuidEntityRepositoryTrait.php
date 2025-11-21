<?php

namespace App\Repository\Trait;

use App\Entity\Interface\UuidEntityInterface;

trait UuidEntityRepositoryTrait {
  public function findOneByUuid(string $uuid): ?UuidEntityInterface {
    $query = $this->createQueryBuilder('e')
                  ->andWhere('e.uuid = :uuid')
                  ->setParameter('uuid', $uuid)
                  ->setMaxResults(1)
                  ->getQuery();

    try {
      return $query->getOneOrNullResult();
    }
    catch (\Exception) {
      return null;
    }
  }
}
