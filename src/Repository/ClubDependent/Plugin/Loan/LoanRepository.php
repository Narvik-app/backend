<?php

namespace App\Repository\ClubDependent\Plugin\Loan;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use App\Service\SeasonService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Loan>
 */
class LoanRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Loan::class);
  }

  /**
   * Count all borrow events for a given item.
   */
  public function countByItem(LoanItem $item): int {
    return (int) $this->createQueryBuilder('l')
      ->select('COUNT(l.id)')
      ->andWhere('l.loanItem = :item')
      ->setParameter('item', $item)
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Count currently open (not returned) borrow events for a given item.
   */
  public function countOpenByItem(LoanItem $item): int {
    return (int) $this->createQueryBuilder('l')
      ->select('COUNT(l.id)')
      ->andWhere('l.loanItem = :item')
      ->andWhere('l.endDate IS NULL')
      ->setParameter('item', $item)
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Aggregate loan statistics for a club over a date window (by loan startDate), plus a
   * live (non-period-bound) count of currently open loans.
   *
   * @return array{
   *   total: int, openNow: int, distinctItems: int, distinctBorrowers: int, avgDurationDays: ?float,
   *   dailyCounts: array<int, array{day: string, count: int}>,
   *   items: array<int, array{uuid: string, name: string, count: int, openCount: int, avgDurationDays: ?float, dailyCounts: array<int, array{day: string, count: int}>}>
   * }
   */
  public function getLoanStats(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): array {
    $dateRange = SeasonService::calculateStartEndDate($club, $endDate, $startDate);
    $conn = $this->getEntityManager()->getConnection();

    $clubClause = $club ? ' club_id = :clubId AND' : '';
    $params = [
      'from' => $dateRange['start']->format('Y-m-d H:i:s'),
      'to' => $dateRange['end']->format('Y-m-d H:i:s'),
    ];
    if ($club) {
      $params['clubId'] = $club->getId();
    }

    // Global aggregates over the period
    $aggregateSql = <<<SQL
      SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT loan_item_id) AS distinct_items,
        COUNT(DISTINCT COALESCE(member_id::text, borrower_name)) AS distinct_borrowers,
        AVG(EXTRACT(EPOCH FROM (end_date - start_date)) / 86400) FILTER (WHERE end_date IS NOT NULL) AS avg_duration_days
      FROM loan
      WHERE{$clubClause} start_date BETWEEN :from AND :to
    SQL;
    $aggregate = $conn->executeQuery($aggregateSql, $params)->fetchAssociative() ?: [];

    // Live snapshot — not period-bound
    $openNowParams = $club ? ['clubId' => $club->getId()] : [];
    $openNowSql = $club
      ? 'SELECT COUNT(*) FROM loan WHERE club_id = :clubId AND end_date IS NULL'
      : 'SELECT COUNT(*) FROM loan WHERE end_date IS NULL';
    $openNow = (int) $conn->executeQuery($openNowSql, $openNowParams)->fetchOne();

    // Daily counts for the chart
    $dailySql = <<<SQL
      SELECT DATE(start_date) AS day, COUNT(*) AS count
      FROM loan
      WHERE{$clubClause} start_date BETWEEN :from AND :to
      GROUP BY DATE(start_date)
      ORDER BY day
    SQL;
    $dailyCounts = $conn->executeQuery($dailySql, $params)->fetchAllAssociative();

    // Per-item breakdown
    $itemsClubClause = $club ? ' l.club_id = :clubId AND' : '';
    $itemsSql = <<<SQL
      SELECT
        li.uuid AS uuid,
        li.name AS name,
        COUNT(l.id) AS count,
        SUM(CASE WHEN l.end_date IS NULL THEN 1 ELSE 0 END) AS open_count,
        AVG(EXTRACT(EPOCH FROM (l.end_date - l.start_date)) / 86400) FILTER (WHERE l.end_date IS NOT NULL) AS avg_duration_days
      FROM loan l
      INNER JOIN loan_item li ON li.id = l.loan_item_id
      WHERE{$itemsClubClause} l.start_date BETWEEN :from AND :to
      GROUP BY li.id, li.uuid, li.name
      ORDER BY count DESC
    SQL;
    $itemRows = $conn->executeQuery($itemsSql, $params)->fetchAllAssociative();

    // Per-item daily counts, reshaped by item uuid
    $itemDailySql = <<<SQL
      SELECT li.uuid AS uuid, DATE(l.start_date) AS day, COUNT(*) AS count
      FROM loan l
      INNER JOIN loan_item li ON li.id = l.loan_item_id
      WHERE{$itemsClubClause} l.start_date BETWEEN :from AND :to
      GROUP BY li.uuid, DATE(l.start_date)
      ORDER BY day
    SQL;
    $itemDailyRows = $conn->executeQuery($itemDailySql, $params)->fetchAllAssociative();

    $dailyCountsByItem = [];
    foreach ($itemDailyRows as $row) {
      $dailyCountsByItem[$row['uuid']][] = ['day' => $row['day'], 'count' => (int) $row['count']];
    }

    $items = array_map(
      fn(array $row) => [
        'uuid' => $row['uuid'],
        'name' => $row['name'],
        'count' => (int) $row['count'],
        'openCount' => (int) $row['open_count'],
        'avgDurationDays' => $row['avg_duration_days'] !== null ? (float) $row['avg_duration_days'] : null,
        'dailyCounts' => $dailyCountsByItem[$row['uuid']] ?? [],
      ],
      $itemRows
    );

    return [
      'total' => (int) ($aggregate['total'] ?? 0),
      'openNow' => $openNow,
      'distinctItems' => (int) ($aggregate['distinct_items'] ?? 0),
      'distinctBorrowers' => (int) ($aggregate['distinct_borrowers'] ?? 0),
      'avgDurationDays' => isset($aggregate['avg_duration_days']) && $aggregate['avg_duration_days'] !== null
        ? (float) $aggregate['avg_duration_days']
        : null,
      'dailyCounts' => array_map(
        fn(array $row) => ['day' => $row['day'], 'count' => (int) $row['count']],
        $dailyCounts
      ),
      'items' => $items,
    ];
  }
}
