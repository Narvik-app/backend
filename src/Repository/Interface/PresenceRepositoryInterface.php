<?php

namespace App\Repository\Interface;

use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
use Deprecated;

interface PresenceRepositoryInterface {
  public function findAllPresentToday(Club $club): array;
  public function findAllByActivity(Activity $activity): ?array;

  /**********************************************************
   *                        METRICS
   *********************************************************/

  /**
   * Return all the presences' stats, sorted by activity name then weekdays
   * 0 = sunday
   *
   * @param Club|null $club
   * @param \DateTimeImmutable $endDate
   * @param \DateTimeImmutable|null $startDate
   * @return array
   */
  public function getPresencesStatsPerActivitiesPerDayOfWeek(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): array;

  /**
   * Return the presences stats for a specific activity, sorted by weekdays
   * 0 = sunday
   *
   * @param Club|null $club
   * @param Activity $activity
   * @param \DateTimeImmutable $endDate
   * @param \DateTimeImmutable|null $startDate
   * @return array
   */
  public function getPresencesStatsPerDayOfWeekForActivity(?Club $club, Activity $activity, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): array;
  public function countNumberOfPresenceDaysYearlyUntilDate(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): int;
  public function countTotalPresences(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): int;
  public function countPresencesPerActivitiesYearlyUntilDate(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null);
}
