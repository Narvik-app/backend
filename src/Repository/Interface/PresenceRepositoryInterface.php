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
  #[Deprecated(message: 'Use countTotalPresences instead', since: '3.12')]
  public function countTotalPresencesYearlyUntilDate(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): int;
  public function countNumberOfPresenceDaysYearlyUntilDate(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): int;
  #[Deprecated(message: 'Use countTotalPresencesYearlyUntilDate instead', since: '3.12')]
  public function countTotalPresencesYearlyForCurrentSeason(?Club $club): int;
  #[Deprecated(message: 'Use countTotalPresencesYearlyUntilDate instead', since: '3.12')]
  public function countTotalPresencesYearlyForPreviousSeason(?Club $club): int;
  public function countTotalPresences(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): int;
  #[Deprecated(message: 'Use countNumberOfPresenceDaysYearlyUntilDate instead', since: '3.12')]
  public function countNumberOfPresenceDaysForCurrentSeason(?Club $club): int;
  #[Deprecated(message: 'Use countNumberOfPresenceDaysYearlyUntilDate instead', since: '3.12')]
  public function countNumberOfPresenceDaysForPreviousSeason(?Club $club): int;
  public function countPresencesPerActivitiesYearlyUntilDate(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null);
  #[Deprecated(message: 'Use countPresencesPerActivitiesYearlyUntilDate instead', since: '3.12')]
  public function countPresencesPerActivitiesForCurrentSeason(?Club $club);
  #[Deprecated(message: 'Use countPresencesPerActivitiesYearlyUntilDate instead', since: '3.12')]
  public function countPresencesPerActivitiesForPreviousSeason(?Club $club);

}
