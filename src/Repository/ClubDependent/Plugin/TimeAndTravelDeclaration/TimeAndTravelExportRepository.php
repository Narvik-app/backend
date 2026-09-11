<?php

namespace App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeAndTravelExport>
 * @implements ClubLinkedInterface<TimeAndTravelExport>
 */
class TimeAndTravelExportRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use ClubLinkedTrait;
  use UuidEntityRepositoryTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, TimeAndTravelExport::class);
  }

  /**
   * Whether the given date already falls within an existing export's period
   * (draft or locked) for this club — used to refuse new declarations that
   * would silently fall outside every future export of that period.
   */
  public function existsCoveringDate(Club $club, \DateTimeImmutable $date): bool {
    $count = $this->createQueryBuilder('e')
      ->select('COUNT(e.id)')
      ->where('e.club = :club')
      ->andWhere('e.startDate <= :date')
      ->andWhere('e.endDate >= :date')
      ->setParameter('club', $club)
      ->setParameter('date', $date)
      ->getQuery()
      ->getSingleScalarResult();

    return $count > 0;
  }
}
