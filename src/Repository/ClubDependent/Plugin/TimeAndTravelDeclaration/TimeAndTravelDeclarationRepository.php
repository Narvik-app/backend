<?php

namespace App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeAndTravelDeclaration>
 * @implements ClubLinkedInterface<TimeAndTravelDeclaration>
 */
class TimeAndTravelDeclarationRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use ClubLinkedTrait;
  use UuidEntityRepositoryTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, TimeAndTravelDeclaration::class);
  }

  /**
   * Declarations in [start, end] not yet attached to any export, for the given club.
   *
   * @param Member[] $members Restricts the result to these members when not empty
   * @return TimeAndTravelDeclaration[]
   */
  public function findExportableForPeriod(Club $club, \DateTimeImmutable $start, \DateTimeImmutable $end, array $members = []): array {
    $qb = $this->createQueryBuilder('d')
      ->andWhere('d.club = :club')
      ->andWhere('d.export IS NULL')
      ->andWhere('d.date >= :start')
      ->andWhere('d.date <= :end')
      ->setParameter('club', $club)
      ->setParameter('start', $start)
      ->setParameter('end', $end)
      ->orderBy('d.member', 'ASC')
      ->addOrderBy('d.date', 'ASC');

    if ($members) {
      $qb->andWhere('d.member IN (:members)')->setParameter('members', $members);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * @param Member[] $members Restricts the result to these members when not empty
   * @return TimeAndTravelDeclaration[]
   */
  public function findByExport(TimeAndTravelExport $export, array $members = []): array {
    $qb = $this->createQueryBuilder('d')
      ->andWhere('d.export = :export')
      ->setParameter('export', $export)
      ->orderBy('d.member', 'ASC')
      ->addOrderBy('d.date', 'ASC');

    if ($members) {
      $qb->andWhere('d.member IN (:members)')->setParameter('members', $members);
    }

    return $qb->getQuery()->getResult();
  }
}
