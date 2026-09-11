<?php

namespace App\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubRepository;
use App\State\Trait\DateRangeQueryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Backs both:
 *  - GET /clubs/{clubUuid}/time-and-travel-declarations/-/summary-per-member (one row per member)
 *  - GET /clubs/{clubUuid}/members/{memberUuid}/time-and-travel-declarations/-/summary (a single row)
 * distinguished by the presence of `memberUuid` in $uriVariables.
 */
final readonly class TimeAndTravelSummaryProvider implements ProviderInterface {
  use DateRangeQueryTrait;

  public function __construct(
    private ClubRepository $clubRepository,
    private MemberRepository $memberRepository,
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

    $memberId = null;
    if (!empty($uriVariables['memberUuid'])) {
      $member = $this->memberRepository->findOneByUuid($uriVariables['memberUuid']);
      if (!$member) {
        return null;
      }
      $memberId = $member->getId();
    }

    return $this->provideSummary($club->getId(), $memberId);
  }

  private function provideSummary(int $clubId, ?int $memberId): TraversablePaginator|array {
    $request = $this->requestStack->getCurrentRequest();
    [$start, $end] = $this->parseDateRangeFilter($request);

    $whereClauses = ['d.club_id = :clubId'];
    $params = ['clubId' => $clubId];

    if ($memberId) {
      $whereClauses[] = 'm.id = :memberId';
      $params['memberId'] = $memberId;
    }
    if ($start) {
      $whereClauses[] = 'd.date >= :start';
      $params['start'] = $start->format('Y-m-d');
    }
    if ($end) {
      $whereClauses[] = 'd.date <= :end';
      $params['end'] = $end->format('Y-m-d');
    }

    $where = implode(' AND ', $whereClauses);

    $sql = <<<SQL
        SELECT
          m.uuid AS member_uuid,
          m.firstname,
          m.lastname,
          m.licence,
          COUNT(d.id) AS declaration_count,
          COALESCE(SUM(d.kilometers), 0) AS total_kilometers,
          COALESCE(SUM(d.hours), 0) AS total_hours,
          COALESCE(SUM(d.kilometers * v.fiscal_coefficient), 0) AS total_travel_amount
        FROM time_and_travel_declaration d
        JOIN member m ON m.id = d.member_id
        LEFT JOIN member_vehicle v ON v.id = d.member_vehicle_id
        WHERE {$where}
        GROUP BY m.id, m.uuid, m.firstname, m.lastname, m.licence
        ORDER BY m.lastname ASC, m.firstname ASC
    SQL;

    $rawRows = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

    $smicRate = $this->getSmicHourlyRate($clubId);
    $rows = [];
    foreach ($rawRows as $rawRow) {
      $totalHours = (float) $rawRow['total_hours'];
      $totalTravelAmount = (float) $rawRow['total_travel_amount'];
      $totalTimeAmount = $totalHours * $smicRate;

      // Raw SQL rows aren't going through the entity normalizer, so we camelCase
      // the keys ourselves to match every other API response.
      $rows[] = [
        'memberUuid' => $rawRow['member_uuid'],
        'firstname' => $rawRow['firstname'],
        'lastname' => $rawRow['lastname'],
        'licence' => $rawRow['licence'],
        'declarationCount' => (int) $rawRow['declaration_count'],
        'totalKilometers' => (int) $rawRow['total_kilometers'],
        'totalHours' => $totalHours,
        'totalTravelAmount' => $totalTravelAmount,
        'totalTimeAmount' => $totalTimeAmount,
        'totalAmount' => $totalTravelAmount + $totalTimeAmount,
      ];
    }

    return $this->paginateRows($rows, $request);
  }

  private function getSmicHourlyRate(int $clubId): float {
    $rate = $this->entityManager->getConnection()->executeQuery(
      'SELECT smic_hourly_rate FROM club_setting WHERE club_id = :clubId',
      ['clubId' => $clubId]
    )->fetchOne();

    return $rate !== false && $rate !== null ? (float) $rate : 0.0;
  }
}
