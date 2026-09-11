<?php

namespace App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
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
   * @return TimeAndTravelDeclaration[]
   */
  public function findExportableForPeriod(Club $club, \DateTimeImmutable $start, \DateTimeImmutable $end): array {
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

    return $qb->getQuery()->getResult();
  }

  /**
   * A vehicle's cumulative distance for the calendar year — the official mileage scale is
   * bracketed on the volunteer's yearly tax-return total, not the club's sports season.
   */
  public function sumKilometersForVehicleInYear(MemberVehicle $vehicle, int $year): int {
    $sum = $this->createQueryBuilder('d')
      ->select('COALESCE(SUM(d.kilometers), 0)')
      ->andWhere('d.memberVehicle = :vehicle')
      ->andWhere('d.date >= :start')
      ->andWhere('d.date <= :end')
      ->setParameter('vehicle', $vehicle)
      ->setParameter('start', new \DateTimeImmutable("{$year}-01-01"))
      ->setParameter('end', new \DateTimeImmutable("{$year}-12-31"))
      ->getQuery()
      ->getSingleScalarResult();

    return (int) $sum;
  }

  /**
   * @return TimeAndTravelDeclaration[]
   */
  public function findByExport(TimeAndTravelExport $export): array {
    return $this->createQueryBuilder('d')
      ->andWhere('d.export = :export')
      ->setParameter('export', $export)
      ->orderBy('d.member', 'ASC')
      ->addOrderBy('d.date', 'ASC')
      ->getQuery()
      ->getResult();
  }
}
