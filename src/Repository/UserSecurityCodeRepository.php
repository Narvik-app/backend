<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSecurityCode;
use App\Enum\UserSecurityCodeTrigger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSecurityCode>
 */
class UserSecurityCodeRepository extends ServiceEntityRepository {
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, UserSecurityCode::class);
  }

  public function findOneBySecurityCode(string $securityCode, UserSecurityCodeTrigger $trigger): ?UserSecurityCode {
    return $this->createQueryBuilder('m')
                ->andWhere('m.code = :securityCode')
                ->andWhere('m.trigger = :trigger')
                ->andWhere(':expire_at <= m.expireAt')
                ->setParameter('securityCode', $securityCode)
                ->setParameter('trigger', $trigger)
                ->setParameter('expire_at', new \DateTimeImmutable())
                ->orderBy('m.createdAt', 'DESC')
                ->orderBy('m.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
  }

  public function findLastOneForUser(User $user, UserSecurityCodeTrigger $trigger): ?UserSecurityCode {
    return $this->createQueryBuilder('m')
                ->andWhere('m.user = :user')
                ->andWhere('m.trigger = :trigger')
                ->andWhere(':expire_at <= m.expireAt')
                ->setParameter('user', $user)
                ->setParameter('trigger', $trigger)
                ->setParameter('expire_at', new \DateTimeImmutable())
                ->orderBy('m.createdAt', 'DESC')
                ->orderBy('m.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
  }

  public function findAllByTrigger(User $user, UserSecurityCodeTrigger $trigger): array {
    return $this->createQueryBuilder('m')
                ->andWhere('m.user = :user')
                ->andWhere('m.trigger = :trigger')
                ->setParameter('user', $user)
                ->setParameter('trigger', $trigger)
                ->getQuery()
                ->getResult();
  }

  /**
   * Return codes that have expired and can be removed.
   *
   * @return UserSecurityCode[]
   */
  public function findExpired(): array {
    return $this->createQueryBuilder('m')
                ->andWhere('m.expireAt <= :expire_at')
                ->setParameter('expire_at', new \DateTimeImmutable('-1 days'))
                ->getQuery()
                ->getResult();
  }
}
