<?php

namespace App\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Club;
use App\Entity\ClubDependent\ClubSetting;
use App\Enum\VehicleCategory;
use App\Enum\VehicleEngineType;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubRepository;
use App\Service\MileageRateCalculationService;
use App\Service\SeasonService;
use App\State\Trait\DateRangeQueryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Backs both:
 *  - GET /clubs/{clubUuid}/time-and-travel-declarations/-/summary-per-member (one row per member)
 *  - GET /clubs/{clubUuid}/members/{memberUuid}/time-and-travel-declarations/-/summary (a single row)
 * distinguished by the presence of `memberUuid` in $uriVariables.
 */
final readonly class TimeAndTravelSummaryProvider implements ProviderInterface {
  // Only paginateRows() is used from this trait — date-range parsing here is resolveDateRange()
  // below instead, since this endpoint must follow the declarations list's own filter conventions
  // (date[after]/date[before], current-season[date]/previous-season[date]), not the plain
  // start/end query params this trait's parseDateRangeFilter() expects elsewhere (e.g. MetricProvider).
  use DateRangeQueryTrait;

  public function __construct(
    private ClubRepository $clubRepository,
    private MemberRepository $memberRepository,
    private EntityManagerInterface $entityManager,
    private RequestStack $requestStack,
    private MileageRateCalculationService $mileageRateCalculationService,
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

    return $this->provideSummary($club, $memberId);
  }

  private function provideSummary(Club $club, ?int $memberId): TraversablePaginator|array {
    $clubId = $club->getId();
    $request = $this->requestStack->getCurrentRequest();
    [$start, $end] = $this->resolveDateRange($request, $club);

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

    // Fetched per-declaration (not aggregated in SQL): the official mileage scale prices a
    // vehicle's WHOLE cumulative distance for the period under a single bracket, so the travel
    // amount can't be summed declaration by declaration — it has to be computed once per vehicle,
    // over that vehicle's total kilometers, via MileageRateCalculationService (mirrors
    // TimeAndTravelExportGenerationService::computeTotals()).
    $sql = <<<SQL
        SELECT
          m.id AS member_id,
          m.uuid AS member_uuid,
          m.firstname,
          m.lastname,
          m.licence,
          d.kilometers,
          d.hours,
          v.id AS vehicle_id,
          v.category AS vehicle_category,
          v.fiscal_power AS vehicle_fiscal_power,
          v.engine_type AS vehicle_engine_type
        FROM time_and_travel_declaration d
        JOIN member m ON m.id = d.member_id
        LEFT JOIN member_vehicle v ON v.id = d.member_vehicle_id
        WHERE {$where}
    SQL;

    $rawRows = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

    /**
     * @var array<int, array{
     *   member: array{memberUuid: string, firstname: string, lastname: string, licence: ?string},
     *   declarationCount: int,
     *   totalKilometers: int,
     *   totalHours: float,
     *   byVehicle: array<int, array{category: string, fiscalPower: int, isElectric: bool, kilometers: int}>
     * }> $byMember
     */
    $byMember = [];
    foreach ($rawRows as $rawRow) {
      $memberId = (int) $rawRow['member_id'];
      $byMember[$memberId]['member'] ??= [
        'memberUuid' => $rawRow['member_uuid'],
        'firstname' => $rawRow['firstname'],
        'lastname' => $rawRow['lastname'],
        'licence' => $rawRow['licence'],
      ];
      $byMember[$memberId]['declarationCount'] = ($byMember[$memberId]['declarationCount'] ?? 0) + 1;
      $byMember[$memberId]['totalKilometers'] = ($byMember[$memberId]['totalKilometers'] ?? 0) + (int) $rawRow['kilometers'];
      $byMember[$memberId]['totalHours'] = ($byMember[$memberId]['totalHours'] ?? 0.0) + (float) $rawRow['hours'];

      $kilometers = (int) $rawRow['kilometers'];
      if (!$rawRow['vehicle_id'] || $kilometers <= 0) {
        continue;
      }

      $vehicleId = (int) $rawRow['vehicle_id'];
      $byMember[$memberId]['byVehicle'][$vehicleId]['category'] ??= $rawRow['vehicle_category'];
      $byMember[$memberId]['byVehicle'][$vehicleId]['fiscalPower'] ??= (int) $rawRow['vehicle_fiscal_power'];
      $byMember[$memberId]['byVehicle'][$vehicleId]['isElectric'] ??= $rawRow['vehicle_engine_type'] === VehicleEngineType::electric->value;
      $byMember[$memberId]['byVehicle'][$vehicleId]['kilometers'] = ($byMember[$memberId]['byVehicle'][$vehicleId]['kilometers'] ?? 0) + $kilometers;
    }

    $smicRate = $this->getSmicHourlyRate($clubId);
    $rows = [];
    foreach ($byMember as $memberData) {
      $totalTravelAmount = 0.0;
      foreach ($memberData['byVehicle'] ?? [] as $vehicleData) {
        $result = $this->mileageRateCalculationService->calculate(
          VehicleCategory::from($vehicleData['category']),
          $vehicleData['fiscalPower'],
          $vehicleData['kilometers'],
          $vehicleData['isElectric'],
        );
        $totalTravelAmount += $result?->amount ?? 0.0;
      }

      $totalHours = (float) $memberData['totalHours'];
      $totalTimeAmount = $totalHours * $smicRate;

      // Raw SQL rows aren't going through the entity normalizer, so we camelCase
      // the keys ourselves to match every other API response.
      $rows[] = [
        'memberUuid' => $memberData['member']['memberUuid'],
        'firstname' => $memberData['member']['firstname'],
        'lastname' => $memberData['member']['lastname'],
        'licence' => $memberData['member']['licence'],
        'declarationCount' => $memberData['declarationCount'],
        'totalKilometers' => $memberData['totalKilometers'],
        'totalHours' => $totalHours,
        'totalTravelAmount' => $totalTravelAmount,
        'totalTimeAmount' => $totalTimeAmount,
        'totalAmount' => $totalTravelAmount + $totalTimeAmount,
      ];
    }

    usort($rows, fn (array $a, array $b) => [$a['lastname'], $a['firstname']] <=> [$b['lastname'], $b['firstname']]);

    return $this->paginateRows($rows, $request);
  }

  /**
   * Mirrors DateFilter/CurrentSeasonFilter/PreviousSeasonFilter as attached to
   * TimeAndTravelDeclaration's `date` property on the declarations list — this endpoint bypasses
   * API Platform's own filter resolution (raw SQL), so it has to replicate the same query param
   * conventions the frontend already sends for that list: `current-season[date]`/`previous-season[date]`
   * (booleans) take precedence, otherwise `date[after]`/`date[before]` (ISO dates, inclusive).
   *
   * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
   */
  private function resolveDateRange(?Request $request, Club $club): array {
    if (!$request) {
      return [null, null];
    }

    if ($this->toBoolean($request->query->all('current-season')['date'] ?? null)) {
      $range = SeasonService::calculateStartEndDate($club, SeasonService::getCurrentSeasonEndDate($club));
      return [$range['start'], $range['end']];
    }

    if ($this->toBoolean($request->query->all('previous-season')['date'] ?? null)) {
      $range = SeasonService::calculateStartEndDate($club, SeasonService::getPreviousSeasonEndDate($club), null, false);
      return [$range['start'], $range['end']];
    }

    $afterFilter = $request->query->all('date')['after'] ?? null;
    $beforeFilter = $request->query->all('date')['before'] ?? null;
    if (!$afterFilter && !$beforeFilter) {
      return [null, null];
    }

    try {
      $start = $afterFilter ? new \DateTimeImmutable($afterFilter . ' 00:00:00') : null;
      $end = $beforeFilter ? new \DateTimeImmutable($beforeFilter . ' 23:59:59') : null;
    } catch (\Exception $e) {
      throw new BadRequestHttpException('Invalid date filter.', $e);
    }

    return [$start, $end];
  }

  private function toBoolean(mixed $value): bool {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
  }

  private function getSmicHourlyRate(int $clubId): float {
    $rate = $this->entityManager->getConnection()->executeQuery(
      'SELECT smic_hourly_rate FROM club_setting WHERE club_id = :clubId',
      ['clubId' => $clubId]
    )->fetchOne();

    return (float) ($rate !== false && $rate !== null ? $rate : ClubSetting::DEFAULT_SMIC_HOURLY_RATE);
  }
}
