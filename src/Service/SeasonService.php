<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\Season;
use App\Repository\SeasonRepository;
use Doctrine\ORM\EntityManagerInterface;

class SeasonService {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly SeasonRepository $seasonRepository,
  ) {
  }

  public static function getSeasonEndDate(?Club $club): string {
    $seasonEnd = '12-31'; // Regular annual year

    if ($club?->getSettings()) {
      $seasonEnd = $club->getSettings()->getSeasonEnd();
    }

    return $seasonEnd;
  }

  public static function getCurrentSeasonEndDate(?Club $club): \DateTimeImmutable {
    $seasonEnd = self::getSeasonEndDate($club);

    $today = new \DateTimeImmutable();
    $endYearSeason = \DateTimeImmutable::createFromFormat("m-d", $seasonEnd);
    if ($today < $endYearSeason) {
      return $endYearSeason;
    } else {
      return $endYearSeason->modify('+1 year');
    }
  }

  public static function getPreviousSeasonEndDate(?Club $club): \DateTimeImmutable {
    return self::getCurrentSeasonEndDate($club)->modify('-1 year');
  }

  public static function getCurrentSeasonName(?Club $club = null): string {
    $currentSeasonEndDate = self::getCurrentSeasonEndDate($club);
    return $currentSeasonEndDate->modify("-1 year")->format("Y") . "/" . $currentSeasonEndDate->format("Y");
  }

  public static function getPreviousSeasonName(?Club $club = null): string {
    $seasons = explode("/", self::getCurrentSeasonName($club));
    $seasons[0] = --$seasons[0];
    $seasons[1] = --$seasons[1];

    return implode("/", $seasons);
  }

  /**
   * Calculate a start and end date range.
   *
   * @param Club|null $club
   * @param \DateTimeImmutable $endDate
   * @param \DateTimeImmutable|null $startDate If not defined, it will be based on the $endDate starting season date
   * @param bool $usedForComparison true: will update the endDate to match the current month and day (only year will be keep).
   *                                      Doing that gives the possibility to compare datas on 2 periods
   * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
   */
  public static function calculateStartEndDate(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null, bool $usedForComparison = true): array
  {
    $dates = [
      "start" => $startDate?->setTime(0, 0, 0),
      "end"   => $endDate->setTime(23, 59, 59),
    ];

    if ($startDate && $startDate < $endDate) {
      return $dates;
    }

    $currentDate = new \DateTimeImmutable();
    $seasonEndDate = SeasonService::getCurrentSeasonEndDate($club);

    // Calculate start date: day after the previous season's end date
    $previousYear = $endDate->modify('-1 year')->format('Y');
    $previousSeasonEnd = $endDate->setDate(
      (int) $previousYear,
      (int) $seasonEndDate->format('m'),
      (int) $seasonEndDate->format('d')
    );
    $dates['start'] = $previousSeasonEnd->modify('+1 day');

    if ($usedForComparison) {
      // For comparison mode, use current date if we're still in this season
      // (i.e., if the season end date hasn't passed yet)
      if ($currentDate <= $endDate->setTime(23, 59, 59)) {
        $dates['end'] = $currentDate;
      }
      // Otherwise, keep the season end date as is (season is already over)
    }

    $dates['start'] = $dates['start']->setTime(0, 0, 0);
    $dates['end'] = $dates['end']->setTime(23, 59, 59);

    return $dates;
  }
}
