<?php

namespace App\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ClubDependent\Plugin\Sale\InventoryItemHistory;
use App\Repository\ClubDependent\Plugin\Sale\InventoryItemHistoryRepository;
use App\Repository\ClubDependent\Plugin\Sale\InventoryItemRepository;
use App\Repository\ClubRepository;
use App\State\Trait\DateRangeQueryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class InventoryItemHistoryProvider implements ProviderInterface {
  use DateRangeQueryTrait;

  public function __construct(
    private ClubRepository $clubRepository,
    private InventoryItemRepository $inventoryItemRepository,
    private InventoryItemHistoryRepository $inventoryItemHistoryRepository,
    private EntityManagerInterface $entityManager,
    private RequestStack $requestStack,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null {
    if (!$operation instanceof GetCollection) {
      return null;
    }

    $club = $this->clubRepository->findOneByUuid($uriVariables['clubUuid']);
    if (!$club) {
      return null;
    }

    $item = $this->inventoryItemRepository->findOneByClubAndUuid($club, $uriVariables['itemUuid']);
    if (!$item) {
      return null;
    }

    if (str_contains((string) $operation->getUriTemplate(), 'per-day')) {
      return $this->providePerDay($item->getId());
    }

    return $this->inventoryItemHistoryRepository->findBy(['item' => $item]);
  }

  private function providePerDay(int $itemId): TraversablePaginator|array {
    $request = $this->requestStack->getCurrentRequest();
    [$start, $end] = $this->parseDateRangeFilter($request);

    $dateFilter = '';
    $params = ['itemId' => $itemId];
    if ($start) {
      $dateFilter .= ' AND h.created_at >= :start';
      $params['start'] = $start->format('Y-m-d H:i:s');
    }
    if ($end) {
      $dateFilter .= ' AND h.created_at <= :end';
      $params['end'] = $end->format('Y-m-d H:i:s');
    }

    $sql = <<<SQL
        WITH ranked AS (
          SELECT
            DATE(h.created_at) AS day,
            h.purchase_price,
            h.selling_price,
            h.quantity,
            ROW_NUMBER() OVER (PARTITION BY DATE(h.created_at) ORDER BY h.created_at DESC) AS rn
          FROM inventory_item_history h
          WHERE h.item_id = :itemId{$dateFilter}
        )
        SELECT day, purchase_price, selling_price, quantity
        FROM ranked
        WHERE rn = 1
        ORDER BY day DESC
    SQL;

    $rows = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

    $items = array_map(function (array $row): InventoryItemHistory {
      $history = new InventoryItemHistory();
      $history->setCreatedAt(new \DateTimeImmutable($row['day']));
      $history->setPurchasePrice($row['purchase_price']);
      $history->setSellingPrice($row['selling_price']);
      $history->setQuantity($row['quantity'] !== null ? (int) $row['quantity'] : null);
      return $history;
    }, $rows);

    return $this->paginateRows($items, $request);
  }
}
