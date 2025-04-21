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

  public static function getCurrentSeasonName(?Club $club = null): string {
    $seasonEnd = '12-31'; // Regular annual year

    if ($club?->getSettings()) {
      $seasonEnd = $club->getSettings()->getSeasonEnd();
    }

    $today = new \DateTimeImmutable();
    $endYearSeason = \DateTimeImmutable::createFromFormat("m-d", $seasonEnd);
    $seasonName = "";
    if ($today < $endYearSeason) {
      $seasonName = $today->modify("-1year")->format("Y") . "/" . $today->format("Y");
    } else {
      $seasonName = $today->format("Y") . "/" . $today->modify("+1year")->format("Y");
    }
    return $seasonName;
  }

  public static function getPreviousSeasonName(?Club $club = null): string {
    $seasons = explode("/", self::getCurrentSeasonName($club));
    $seasons[0] = --$seasons[0];
    $seasons[1] = --$seasons[1];

    return implode("/", $seasons);
  }
}
