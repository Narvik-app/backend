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

  public function countTotalPresencesYearlyUntilDate(?Club $club, \DateTime $maxDate): int;
  public function countNumberOfPresenceDaysYearlyUntilDate(?Club $club, \DateTime $maxDate): int;
  public function countTotalPresencesYearlyUntilToday(?Club $club): int;
  public function countTotalPresencesYearlyForPreviousYear(?Club $club): int;
  public function countTotalPresences(?Club $club): int;
  public function countNumberOfPresenceDaysYearlyUntilToday(?Club $club): int;
  public function countNumberOfPresenceDaysYearlyForPreviousYear(?Club $club): int;
  public function countPresencesPerActivitiesYearlyUntilDate(?Club $club, \DateTime $maxDate);
  public function countPresencesPerActivitiesYearlyUntilToday(?Club $club);
  public function countPresencesPerActivitiesYearlyForPreviousYear(?Club $club);

}
