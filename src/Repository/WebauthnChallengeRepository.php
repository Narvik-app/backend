<?php

namespace App\Repository;

use App\Entity\WebauthnChallenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebauthnChallenge>
 */
class WebauthnChallengeRepository extends ServiceEntityRepository {
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, WebauthnChallenge::class);
  }

  public function findOneByUuid(string $uuid): ?WebauthnChallenge {
    if (!\Symfony\Component\Uid\Uuid::isValid($uuid)) {
      return null;
    }
    return $this->findOneBy(['uuid' => $uuid]);
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
