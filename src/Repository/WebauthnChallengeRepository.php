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
  public function deleteExpired(): int {
    return $this->createQueryBuilder('c')
                ->delete()
                ->andWhere('c.expireAt <= :now')
                ->setParameter('now', new \DateTimeImmutable())
                ->getQuery()
                ->execute();
  }
}
