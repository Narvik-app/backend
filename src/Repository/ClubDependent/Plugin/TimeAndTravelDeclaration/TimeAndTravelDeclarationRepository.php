<?php

namespace App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeAndTravelDeclaration>
 * @implements ClubLinkedInterface<TimeAndTravelDeclaration>
 */
class TimeAndTravelDeclarationRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, TimeAndTravelDeclaration::class);
  }

  /**
   * Find declarations for a specific member
   */
  public function findByMember(string $memberUuid): array {
    return $this->createQueryBuilder('d')
      ->join('d.member', 'm')
      ->where('m.uuid = :memberUuid')
      ->setParameter('memberUuid', $memberUuid)
      ->orderBy('d.date', 'DESC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Find declarations by date range
   */
  public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, ?string $clubUuid = null): array {
    $qb = $this->createQueryBuilder('d')
      ->where('d.date >= :startDate')
      ->andWhere('d.date <= :endDate')
      ->setParameter('startDate', $startDate)
      ->setParameter('endDate', $endDate)
      ->orderBy('d.date', 'DESC');

    if ($clubUuid) {
      $qb->join('d.member', 'm')
         ->join('m.club', 'c')
         ->andWhere('c.uuid = :clubUuid')
         ->setParameter('clubUuid', $clubUuid);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Find declarations grouped by month and year
   */
  public function findMonthlyTotals(int $year, ?string $clubUuid = null): array {
    $qb = $this->createQueryBuilder('d')
      ->select('YEAR(d.date) as year, MONTH(d.date) as month, SUM(d.kilometers) as totalKilometers, SUM(d.hours) as totalHours, SUM(d.fiscalReduction) as totalFiscalReduction, SUM(d.timeValue) as totalTimeValue, SUM(d.totalAmount) as totalAmount, COUNT(d.id) as declarationCount')
      ->where('YEAR(d.date) = :year')
      ->setParameter('year', $year)
      ->groupBy('year, month')
      ->orderBy('month', 'ASC');

    if ($clubUuid) {
      $qb->join('d.member', 'm')
         ->join('m.club', 'c')
         ->andWhere('c.uuid = :clubUuid')
         ->setParameter('clubUuid', $clubUuid);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Find yearly totals
   */
  public function findYearlyTotals(int $year, ?string $clubUuid = null): array {
    $qb = $this->createQueryBuilder('d')
      ->select('YEAR(d.date) as year, SUM(d.kilometers) as totalKilometers, SUM(d.hours) as totalHours, SUM(d.fiscalReduction) as totalFiscalReduction, SUM(d.timeValue) as totalTimeValue, SUM(d.totalAmount) as totalAmount, COUNT(d.id) as declarationCount')
      ->where('YEAR(d.date) = :year')
      ->setParameter('year', $year)
      ->groupBy('year');

    if ($clubUuid) {
      $qb->join('d.member', 'm')
         ->join('m.club', 'c')
         ->andWhere('c.uuid = :clubUuid')
         ->setParameter('clubUuid', $clubUuid);
    }

    return $qb->getQuery()->getOneOrNullResult() ?: [];
  }

  /**
   * Find member totals for a specific year
   */
  public function findMemberTotalsByYear(int $year, ?string $clubUuid = null): array {
    $qb = $this->createQueryBuilder('d')
      ->select('m.uuid as memberUuid, m.firstname, m.lastname, SUM(d.kilometers) as totalKilometers, SUM(d.hours) as totalHours, SUM(d.fiscalReduction) as totalFiscalReduction, SUM(d.timeValue) as totalTimeValue, SUM(d.totalAmount) as totalAmount, COUNT(d.id) as declarationCount')
      ->join('d.member', 'm')
      ->where('YEAR(d.date) = :year')
      ->setParameter('year', $year)
      ->groupBy('m.uuid, m.firstname, m.lastname')
      ->orderBy('m.lastname', 'ASC');

    if ($clubUuid) {
      $qb->join('m.club', 'c')
         ->andWhere('c.uuid = :clubUuid')
         ->setParameter('clubUuid', $clubUuid);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Get statistics for dashboard
   */
  public function getDashboardStats(?string $clubUuid = null): array {
    $qb = $this->createQueryBuilder('d')
      ->select('COUNT(d.id) as totalDeclarations, SUM(d.kilometers) as totalKilometers, SUM(d.hours) as totalHours, AVG(d.kilometers) as averageKilometers, MAX(d.date) as lastDeclarationDate')
      ->setMaxResults(1);

    if ($clubUuid) {
      $qb->join('d.member', 'm')
         ->join('m.club', 'c')
         ->andWhere('c.uuid = :clubUuid')
         ->setParameter('clubUuid', $clubUuid);
    }

    $result = $qb->getQuery()->getOneOrNullResult();

    return [
      'totalDeclarations' => $result['totalDeclarations'] ?? 0,
      'totalKilometers' => (float) ($result['totalKilometers'] ?? 0),
      'totalHours' => (float) ($result['totalHours'] ?? 0),
      'averageKilometers' => (float) ($result['averageKilometers'] ?? 0),
      'lastDeclarationDate' => $result['lastDeclarationDate'],
    ];
  }
}
