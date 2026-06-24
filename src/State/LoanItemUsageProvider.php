<?php

namespace App\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ClubDependent\Plugin\Loan\LoanItemRepository;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides per-day loan usage counts for a LoanItem.
 *
 * Endpoint: /clubs/{clubUuid}/loan-items/{itemUuid}/usage-per-day
 * Returns rows { day, count } ordered by day DESC.
 */
final readonly class LoanItemUsageProvider implements ProviderInterface {
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

    $start = null;
    $end = null;

    $startFilter = $request?->query->get('start');
    $endFilter = $request?->query->get('end');
    if ($startFilter || $endFilter) {
      try {
        if ($endFilter) {
          $end = new \DateTimeImmutable($endFilter . ' 23:59:59');
        }
        if ($startFilter) {
          $start = new \DateTimeImmutable($startFilter . ' 00:00:00');
        }
      } catch (\Exception $e) {
        throw new BadRequestHttpException('Invalid date filter.', $e);
      }
    }

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

    if ($request?->query->get('pagination') === 'false') {
      return $rows;
    }

    $page = max(1, $request?->query->getInt('page', 1) ?? 1);
    $itemsPerPage = max(1, $request?->query->getInt('itemsPerPage', 30) ?? 30);
    $total = count($rows);
    $sliced = array_slice($rows, ($page - 1) * $itemsPerPage, $itemsPerPage);

    return new TraversablePaginator(new \ArrayIterator($sliced), $page, $itemsPerPage, $total);
  }
}
