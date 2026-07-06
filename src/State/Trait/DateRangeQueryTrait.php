<?php

namespace App\State\Trait;

use ApiPlatform\State\Pagination\TraversablePaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Shared helpers for state providers exposing a raw-SQL, per-day breakdown behind a
 * manual `start`/`end`/`page`/`itemsPerPage`/`pagination` query-param contract.
 */
trait DateRangeQueryTrait {
  /**
   * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
   */
  private function parseDateRangeFilter(?Request $request): array {
    $startFilter = $request?->query->get('start');
    $endFilter = $request?->query->get('end');
    if (!$startFilter && !$endFilter) {
      return [null, null];
    }

    try {
      $start = $startFilter ? new \DateTimeImmutable($startFilter . ' 00:00:00') : null;
      $end = $endFilter ? new \DateTimeImmutable($endFilter . ' 23:59:59') : null;
    } catch (\Exception $e) {
      throw new BadRequestHttpException('Invalid date filter.', $e);
    }

    return [$start, $end];
  }

  private function paginateRows(array $rows, ?Request $request): TraversablePaginator|array {
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
