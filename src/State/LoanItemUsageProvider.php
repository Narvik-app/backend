<?php

namespace App\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ClubDependent\Plugin\Loan\LoanItemRepository;
use App\Repository\ClubRepository;
use App\State\Trait\DateRangeQueryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides per-day loan usage counts for a LoanItem.
 *
 * Endpoint: /clubs/{clubUuid}/loan-items/{itemUuid}/usage-per-day
 * Returns rows { day, count } ordered by day DESC.
 */
final readonly class LoanItemUsageProvider implements ProviderInterface {
  use DateRangeQueryTrait;

  public function __construct(
    private ClubRepository $clubRepository,
    private LoanItemRepository $loanItemRepository,
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

    $item = $this->loanItemRepository->findOneByClubAndUuid($club, $uriVariables['itemUuid']);
    if (!$item) {
      return null;
    }

    return $this->providePerDay($item->getId());
  }

  private function providePerDay(int $itemId): TraversablePaginator|array {
    $request = $this->requestStack->getCurrentRequest();
    [$start, $end] = $this->parseDateRangeFilter($request);

    $dateFilter = '';
    $params = ['itemId' => $itemId];
    if ($start) {
      $dateFilter .= ' AND l.start_date >= :start';
      $params['start'] = $start->format('Y-m-d H:i:s');
    }
    if ($end) {
      $dateFilter .= ' AND l.start_date <= :end';
      $params['end'] = $end->format('Y-m-d H:i:s');
    }

    $sql = <<<SQL
        SELECT
          DATE(l.start_date) AS day,
          COUNT(*) AS count
        FROM loan l
        WHERE l.loan_item_id = :itemId{$dateFilter}
        GROUP BY DATE(l.start_date)
        ORDER BY day DESC
    SQL;

    $rows = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

    return $this->paginateRows($rows, $request);
  }
}
