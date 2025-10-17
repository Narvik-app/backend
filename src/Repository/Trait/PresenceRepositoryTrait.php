<?php

namespace App\Repository\Trait;

use App\DQL\CustomExpr;
use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
use App\Service\SeasonService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
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

  public function getStatsPerDayOfWeek(?Club $club, \DateTimeImmutable $maxDate)
  {
    $dateRange = $this->calculateStartEndDate($club, $maxDate);
    $conn = $this->getEntityManager()->getConnection();

    $presenceTable = $this->getClassMetadata()->getTableName();
    // Guess for join table name based on Doctrine's default naming strategy.
    $presenceActivityTable = $presenceTable . '_activity';
    $presenceJoinColumn = $presenceTable . '_id';
    $activityTable = 'activity';

    $params = [
      'from' => $dateRange['start']->format('Y-m-d H:i:s'),
      'to' => $dateRange['end']->format('Y-m-d H:i:s'),
    ];
    $paramTypes = [];

    $whereClauses = ['p.date BETWEEN :from AND :to'];

    if ($club) {
      $whereClauses[] = 'p.club_id = :clubId';
      $params['clubId'] = $club->getId();

      $ignoredActivities = $club->getSettings()?->getExcludedActivitiesFromOpeningDays();
      if ($ignoredActivities && !$ignoredActivities->isEmpty()) {
        $ignoredActivityIds = array_map(fn($activity) => $activity->getId(), $ignoredActivities->toArray());
        $whereClauses[] = 'a.id NOT IN (:ignoredActivities)';
        $params['ignoredActivities'] = $ignoredActivityIds;
        $paramTypes['ignoredActivities'] = ArrayParameterType::INTEGER;
      }
    }

    $whereSql = implode(' AND ', $whereClauses);

    $sql = <<<SQL
      WITH daily_counts AS (
        SELECT
          a.name AS activity_name,
          DATE(p.date) AS day,
          EXTRACT(DOW FROM p.date) AS dayofweek,
          COUNT(*) AS daily_total
        FROM {$presenceTable} p
        INNER JOIN {$presenceActivityTable} pa ON p.id = pa.{$presenceJoinColumn}
        INNER JOIN {$activityTable} a ON a.id = pa.activity_id
        WHERE {$whereSql}
        GROUP BY a.name, DATE(p.date), EXTRACT(DOW FROM p.date)
      )
      SELECT
        activity_name,
        dayofweek,
        SUM(daily_total) AS total_presences,
        CAST(AVG(daily_total) AS NUMERIC(10,2)) AS avg_per_day,
        PERCENTILE_CONT(0.25) WITHIN GROUP (ORDER BY daily_total) AS p25,
        PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY daily_total) AS median,
        PERCENTILE_CONT(0.75) WITHIN GROUP (ORDER BY daily_total) AS p75
      FROM daily_counts
      GROUP BY activity_name, dayofweek
      ORDER BY activity_name, dayofweek
    SQL;

    return $conn->executeQuery($sql, $params, $paramTypes)->fetchAllAssociative();
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
    return $this->countTotalPresencesYearlyUntilDate($club, SeasonService::getCurrentSeasonEndDate($club));
  }

  public function countTotalPresencesYearlyForPreviousSeason(?Club $club): int {
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

  /**
   * @param Club|null $club
   * @param \DateTimeImmutable $maxDate
   * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
   * @throws \DateMalformedStringException
   */
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
}
