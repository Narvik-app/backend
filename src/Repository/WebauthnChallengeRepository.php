<?php

namespace App\Repository;

use App\Entity\WebauthnChallenge;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebauthnChallenge>
 * @method WebauthnChallenge|null findOneByUuid(string $uuid)
 */
class WebauthnChallengeRepository extends ServiceEntityRepository {
  use UuidEntityRepositoryTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, WebauthnChallenge::class);
  }

  /**
   * Remove every expired challenge. Returns the number of deleted rows.
   */
  /**
   * Atomically delete a challenge. Returns false when the row was already deleted (i.e. by a concurrent request).
   */
  public function deleteOne(WebauthnChallenge $challenge): bool {
    return $this->createQueryBuilder('c')
                ->delete()
                ->andWhere('c.id = :id')
                ->setParameter('id', $challenge->getId())
                ->getQuery()
                ->execute() === 1;
  }

  public function deleteExpired(): int {
    return $this->createQueryBuilder('c')
                ->delete()
                ->andWhere('c.expireAt <= :now')
                ->setParameter('now', new \DateTimeImmutable())
                ->getQuery()
                ->execute();
  }
}
