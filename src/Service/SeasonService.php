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
   * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
   */
  public static function calculateStartEndDate(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): array
  {
    $dates = [
      "start" => $startDate,
      "end"   => $endDate,
    ];

    if ($startDate && $startDate < $endDate) {
      return $dates;
    }

    $currentDate = new \DateTimeImmutable();
    $seasonEndDate = SeasonService::getCurrentSeasonEndDate($club);

    $dates['start'] = $endDate->setDate($endDate->modify('-1 year')->format('Y'), $seasonEndDate->format('m'), $seasonEndDate->format('d'));
    $dates['end'] = $endDate->setDate($endDate->format('Y'), $currentDate->format('m'), $currentDate->format('d'));

    return $dates;
  }
}
