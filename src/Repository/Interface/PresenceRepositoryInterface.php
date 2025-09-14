<?php

namespace App\Repository\Interface;

use App\Entity\Club;
use App\Entity\ClubDependent\Activity;

interface PresenceRepositoryInterface {
  public function findAllPresentToday(Club $club): array;
  public function findAllByActivity(Activity $activity): ?array;

  /**********************************************************
   *                        METRICS
   *********************************************************/

  public function countTotalPresencesYearlyUntilDate(?Club $club, \DateTimeImmutable $maxDate): int;
  public function countNumberOfPresenceDaysYearlyUntilDate(?Club $club, \DateTimeImmutable $maxDate): int;
  public function countTotalPresencesYearlyForCurrentSeason(?Club $club): int;
  public function countTotalPresencesYearlyForPreviousSeason(?Club $club): int;
  public function countTotalPresences(?Club $club): int;
  public function countNumberOfPresenceDaysForCurrentSeason(?Club $club): int;
  public function countNumberOfPresenceDaysForPreviousSeason(?Club $club): int;
  public function countPresencesPerActivitiesYearlyUntilDate(?Club $club, \DateTimeImmutable $maxDate);
  public function countPresencesPerActivitiesForCurrentSeason(?Club $club);
  public function countPresencesPerActivitiesForPreviousSeason(?Club $club);

}
