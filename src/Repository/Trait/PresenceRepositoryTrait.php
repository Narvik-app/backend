<?php

namespace App\Repository\Trait;

use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
use App\Service\SeasonService;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

trait PresenceRepositoryTrait {
  use ClubLinkedTrait;

  private function applyTodayConstraint(QueryBuilder $qb): QueryBuilder {
    return $this->applyDayConstraint($qb, new \DateTimeImmutable());
  }

  private function applyDayConstraint(QueryBuilder $qb, \DateTimeImmutable $date): QueryBuilder {
    $qb->andWhere($qb->expr()->between('m.date', ':from', ':to'))
       ->setParameter('from', $date->setTime(0, 0, 0))
       ->setParameter('to', $date->setTime(23, 59, 59));
    return $qb;
  }

  private function applyActivityExclusionConstraint(?Club $club, QueryBuilder $qb): void {
    if ($club) {
      $this->applyClubRestriction($qb, $club);

      $ignoredActivities = $club->getSettings()?->getExcludedActivitiesFromOpeningDays();

      if ($ignoredActivities && $ignoredActivities->count() > 0) {
        $qb->leftJoin('m.activities', 'mpa')
           ->andWhere($qb->expr()->notIn("mpa", ":ids"))
           ->setParameter("ids", $ignoredActivities)
        ;
      }
    }
  }

  /**
   * @return array Returns an array of presences
   */
  public function findAllPresentToday(Club $club): array {
    $qb = $this->createQueryBuilder('m');

    return
      $this->applyTodayConstraint($qb)
            ->andWhere($qb->expr()->eq('m.' . $this->getClassName()::getClubSqlPath(), ':club'))
            ->setParameter(':club', $club)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult()
      ;
  }

  public function findAllByActivity(Activity $activity): ?array {
    $qb = $this->createQueryBuilder('m');
    return
      $qb->innerJoin("m.activities", "a", Join::WITH, $qb->expr()->eq("a.id", ":activity"))
         ->orderBy("m.date", "DESC")
         ->setParameter("activity", $activity)
         ->getQuery()->getResult()
      ;
  }

  /**********************************************************
   *                        METRICS
   *********************************************************/

  /**
   * @param Club|null $club
   * @param \DateTimeImmutable $maxDate
   * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
\   */
  private function calculateStartEndDate(?Club $club, \DateTimeImmutable $maxDate): array {
    $currentDate = new \DateTimeImmutable();

    $seasonEndDate = SeasonService::getCurrentSeasonEndDate($club);

    $startDate = $maxDate->setDate($maxDate->modify('-1 year')->format('Y'), $seasonEndDate->format('m'), $seasonEndDate->format('d'));
    $maxDate = $maxDate->setDate($maxDate->format('Y'), $currentDate->format('m'), $currentDate->format('d'));

    return [
      "start" => $startDate,
      "end" => $maxDate,
    ];
  }

  public function countTotalPresencesYearlyUntilDate(?Club $club, \DateTimeImmutable $maxDate): int {
    $dateRange = $this->calculateStartEndDate($club, $maxDate);

    $qb = $this->createQueryBuilder("m");
    if ($club) {
      $this->applyClubRestriction($qb, $club);
    }
    return $qb
      ->select($qb->expr()->count("m.id"))
      ->andWhere($qb->expr()->between("m.date", ":from", ":to"))
      ->setParameter("from", $dateRange['start'])
      ->setParameter("to", $dateRange['end'])
      ->getQuery()->getSingleScalarResult();
  }

  public function countNumberOfPresenceDaysYearlyUntilDate(?Club $club, \DateTimeImmutable $maxDate): int {
    $dateRange = $this->calculateStartEndDate($club, $maxDate);

    $qb = $this->createQueryBuilder("m");
    $this->applyActivityExclusionConstraint($club, $qb);

    return $qb
      ->select($qb->expr()->countDistinct("m.date"))
      ->andWhere($qb->expr()->between("m.date", ":from", ":to"))
      ->setParameter("from", $dateRange['start'])
      ->setParameter("to", $dateRange['end'])
      ->getQuery()->getSingleScalarResult();
  }

  public function countTotalPresencesYearlyForCurrentSeason(?Club $club): int {
    dump('current season');
    return $this->countTotalPresencesYearlyUntilDate($club, SeasonService::getCurrentSeasonEndDate($club));
  }

  public function countTotalPresencesYearlyForPreviousSeason(?Club $club): int {
    dump('previous season');
    $lastYear = SeasonService::getCurrentSeasonEndDate($club)->modify('-1 year');
    return $this->countTotalPresencesYearlyUntilDate($club, $lastYear);
  }

  public function countTotalPresences(?Club $club): int {
    $qb = $this->createQueryBuilder("m");
    if ($club) {
      $this->applyClubRestriction($qb, $club);
    }
    return $qb
      ->select($qb->expr()->count("m.id"))
      ->getQuery()->getSingleScalarResult();
  }

  public function countNumberOfPresenceDaysForCurrentSeason(?Club $club): int {
    return $this->countNumberOfPresenceDaysYearlyUntilDate($club, SeasonService::getCurrentSeasonEndDate($club));
  }

  public function countNumberOfPresenceDaysForPreviousSeason(?Club $club): int {
    $lastYear = SeasonService::getCurrentSeasonEndDate($club)->modify('-1 year');
    return $this->countNumberOfPresenceDaysYearlyUntilDate($club, $lastYear);
  }

  public function countPresencesPerActivitiesYearlyUntilDate(?Club $club, \DateTimeImmutable $maxDate) {
    $dateRange = $this->calculateStartEndDate($club, $maxDate);

    $qb = $this->createQueryBuilder("m");
    if ($club) {
      $this->applyClubRestriction($qb, $club);
    }
    return $qb
      ->select("a.name")
      ->addSelect($qb->expr()->count("a.name") . ' AS total')
      ->innerJoin("m.activities", "a")
      ->groupBy("a.name")
      ->orderBy("a.name")

      ->andWhere($qb->expr()->between("m.date", ":from", ":to"))
      ->setParameter("from", $dateRange['start'])
      ->setParameter("to", $dateRange['end'])
      ->getQuery()->getResult();
  }

  public function countPresencesPerActivitiesForCurrentSeason(?Club $club) {
    return $this->countPresencesPerActivitiesYearlyUntilDate($club, SeasonService::getCurrentSeasonEndDate($club));
  }

  public function countPresencesPerActivitiesForPreviousSeason(?Club $club) {
    $lastYear = SeasonService::getCurrentSeasonEndDate($club)->modify('-1 year');
    return $this->countPresencesPerActivitiesYearlyUntilDate($club, $lastYear);
  }
}
