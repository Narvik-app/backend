<?php

namespace App\Repository\ClubDependent\Plugin\Sale;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Sale\Sale;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use App\Service\SeasonService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sale>
 */
class SaleRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Sale::class);
  }

  /**
   * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
   */
  private function resolveStatsWindow(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate): array {
    if ($startDate !== null) {
      return [
        'start' => $startDate->setTime(0, 0, 0),
        'end' => $endDate->setTime(23, 59, 59),
      ];
    }

    return SeasonService::calculateStartEndDate($club, $endDate, null, false);
  }

  /**
   * Aggregate sale count/amount, globally and per payment mode, over a date window.
   * `total-amount` excludes `stock_removal` payment modes; `value` (total count) includes them.
   *
   * @return array{
   *   totalCount: int,
   *   totalAmount: float,
   *   paymentModes: array<int, array{uuid: string, name: string, icon: ?string, kind: string, count: int, amount: float}>
   * }
   */
  public function getSaleStats(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): array {
    $window = $this->resolveStatsWindow($club, $endDate, $startDate);
    $conn = $this->getEntityManager()->getConnection();

    $params = [
      'from' => $window['start']->format('Y-m-d H:i:s'),
      'to' => $window['end']->format('Y-m-d H:i:s'),
    ];

    $totalsWhereClauses = ['s.created_at BETWEEN :from AND :to'];
    $totalsParams = $params;
    $this->addClubRawSqlRestriction($club, $totalsWhereClauses, $totalsParams, 's.');
    $totalsWhereSql = implode(' AND ', $totalsWhereClauses);

    $totalsSql = <<<SQL
      SELECT
        COUNT(*) AS total_count,
        COALESCE(SUM(s.price) FILTER (WHERE pm.kind IS DISTINCT FROM 'stock_removal'), 0) AS total_amount
      FROM sale s
      LEFT JOIN sale_payment_mode pm ON pm.id = s.payment_mode_id
      WHERE {$totalsWhereSql}
    SQL;
    $totals = $conn->executeQuery($totalsSql, $totalsParams)->fetchAssociative() ?: [];

    $paymentModesWhereClauses = [];
    $paymentModesParams = [];
    $this->addClubRawSqlRestriction($club, $paymentModesWhereClauses, $paymentModesParams, 'pm.');
    $paymentModesWhereSql = $paymentModesWhereClauses ? implode(' AND ', $paymentModesWhereClauses) : '1 = 1';

    $paymentModesSql = <<<SQL
      SELECT
        pm.uuid AS uuid,
        pm.name AS name,
        pm.icon AS icon,
        pm.kind AS kind,
        COUNT(s.id) AS count,
        COALESCE(SUM(s.price), 0) AS amount
      FROM sale_payment_mode pm
      LEFT JOIN sale s ON s.payment_mode_id = pm.id AND s.created_at BETWEEN :from AND :to
      WHERE {$paymentModesWhereSql}
      GROUP BY pm.id, pm.uuid, pm.name, pm.icon, pm.kind, pm.weight
      ORDER BY pm.weight ASC NULLS LAST, pm.name ASC
    SQL;
    $paymentModeRows = $conn->executeQuery($paymentModesSql, array_merge($paymentModesParams, $params))->fetchAllAssociative();

    return [
      'totalCount' => (int) ($totals['total_count'] ?? 0),
      'totalAmount' => (float) ($totals['total_amount'] ?? 0),
      'paymentModes' => array_map(
        fn(array $row) => [
          'uuid' => $row['uuid'],
          'name' => $row['name'],
          'icon' => $row['icon'],
          'kind' => $row['kind'],
          'count' => (int) $row['count'],
          'amount' => (float) $row['amount'],
        ],
        $paymentModeRows
      ),
    ];
  }

  /**
   * Aggregate sold quantity/amount per item (grouped by category, item name and payment mode)
   * over a date window. `amount` excludes `stock_removal` payment modes; `count` includes them.
   *
   * @return array<int, array{
   *   category: ?string, itemName: string, paymentModeName: string, count: int, amount: float
   * }>
   */
  public function getSalePerItemStats(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): array {
    $window = $this->resolveStatsWindow($club, $endDate, $startDate);
    $conn = $this->getEntityManager()->getConnection();

    $whereClauses = ['s.created_at BETWEEN :from AND :to'];
    $params = [
      'from' => $window['start']->format('Y-m-d H:i:s'),
      'to' => $window['end']->format('Y-m-d H:i:s'),
    ];
    $this->addClubRawSqlRestriction($club, $whereClauses, $params, 's.');
    $whereSql = implode(' AND ', $whereClauses);

    $sql = <<<SQL
      SELECT
        spi.item_category AS category,
        spi.item_name AS item_name,
        pm.name AS payment_mode_name,
        SUM(spi.quantity) AS count,
        COALESCE(SUM(spi.item_price * spi.quantity) FILTER (WHERE pm.kind IS DISTINCT FROM 'stock_removal'), 0) AS amount
      FROM sale_purchased_item spi
      INNER JOIN sale s ON s.id = spi.sale_id
      INNER JOIN sale_payment_mode pm ON pm.id = s.payment_mode_id
      WHERE {$whereSql}
      GROUP BY spi.item_category, spi.item_name, pm.id, pm.name
      ORDER BY spi.item_category ASC NULLS LAST, spi.item_name ASC
    SQL;
    $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

    return array_map(
      fn(array $row) => [
        'category' => $row['category'],
        'itemName' => $row['item_name'],
        'paymentModeName' => $row['payment_mode_name'],
        'count' => (int) $row['count'],
        'amount' => (float) $row['amount'],
      ],
      $rows
    );
  }
}
