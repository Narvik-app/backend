<?php

namespace App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Enum\TimeAndTravelExportStatus;
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
   * Whether the given date already falls within a LOCKED export's period for this club — used to
   * refuse new declarations that could never be included in any export of that period any more.
   * A draft export doesn't block: it gets regenerated (picking up new declarations) right before
   * it's locked, so declarations remain addable for as long as the period isn't locked yet.
   */
  public function existsCoveringDate(Club $club, \DateTimeImmutable $date): bool {
    $count = $this->createQueryBuilder('e')
      ->select('COUNT(e.id)')
      ->where('e.club = :club')
      ->andWhere('e.status = :status')
      ->andWhere('e.startDate <= :date')
      ->andWhere('e.endDate >= :date')
      ->setParameter('club', $club)
      ->setParameter('status', TimeAndTravelExportStatus::locked)
      ->setParameter('date', $date)
      ->getQuery()
      ->getSingleScalarResult();

    return $count > 0;
  }

  /**
   * The still-editable (draft) export whose period covers this date, if any — used to know which
   * export needs regenerating when a declaration in its period is added, edited or removed.
   */
  public function findDraftCoveringDate(Club $club, \DateTimeImmutable $date): ?TimeAndTravelExport {
    return $this->createQueryBuilder('e')
      ->where('e.club = :club')
      ->andWhere('e.status = :status')
      ->andWhere('e.startDate <= :date')
      ->andWhere('e.endDate >= :date')
      ->setParameter('club', $club)
      ->setParameter('status', TimeAndTravelExportStatus::draft)
      ->setParameter('date', $date)
      ->getQuery()
      ->getOneOrNullResult();
  }

  /**
   * Any other export (draft or locked) whose period overlaps [$start, $end] for this club —
   * used to refuse creating/editing an export that would contend with it over declarations.
   */
  public function findOverlapping(Club $club, \DateTimeImmutable $start, \DateTimeImmutable $end, ?TimeAndTravelExport $excluding = null): ?TimeAndTravelExport {
    $qb = $this->createQueryBuilder('e')
      ->where('e.club = :club')
      ->andWhere('e.startDate <= :end')
      ->andWhere('e.endDate >= :start')
      ->setParameter('club', $club)
      ->setParameter('start', $start)
      ->setParameter('end', $end)
      ->setMaxResults(1);

    if ($excluding?->getId() !== null) {
      $qb->andWhere('e.id != :excludingId')->setParameter('excludingId', $excluding->getId());
    }

    return $qb->getQuery()->getOneOrNullResult();
  }
}
