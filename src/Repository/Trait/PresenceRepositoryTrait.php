<?php

namespace App\Repository\Trait;

use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
use App\Service\SeasonService;
use Doctrine\DBAL\ArrayParameterType;
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

  private function applyActivityExclusionConstraint(?Club $club, QueryBuilder $qb, string $presenceAlias = 'm'): void {
    if ($club) {
      $this->applyClubRestriction($qb, $club);

      $ignoredActivities = $club->getSettings()?->getExcludedActivitiesFromOpeningDays();

      if ($ignoredActivities && $ignoredActivities->count() > 0) {
          $qb->leftJoin($presenceAlias . '.activities', 'mpa')
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

  public function getPresencesStatsPerActivitiesPerDayOfWeek(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): array
  {
    return $this->executePresenceStatsPerDayOfWeekQuery($club, null, $endDate, $startDate);
  }

  public function getPresencesStatsPerDayOfWeekForActivity(?Club $club, Activity $activity, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): array
  {
    return $this->executePresenceStatsPerDayOfWeekQuery($club, $activity, $endDate, $startDate);
  }

  public function countNumberOfPresenceDaysYearlyUntilDate(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): int {
    $dateRange = SeasonService::calculateStartEndDate($club, $endDate, $startDate);

    $qb = $this->createQueryBuilder("m");
    $this->applyActivityExclusionConstraint($club, $qb);

    return $qb
      ->select($qb->expr()->countDistinct("m.date"))
      ->andWhere($qb->expr()->between("m.date", ":from", ":to"))
      ->setParameter("from", $dateRange['start'])
      ->setParameter("to", $dateRange['end'])
      ->getQuery()->getSingleScalarResult();
  }

  public function countTotalPresences(?Club $club, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): int {
    $dateRange = SeasonService::calculateStartEndDate($club, $endDate, $startDate);

    $qb = $this->createQueryBuilder("m");
    $this->applyActivityExclusionConstraint($club, $qb);

    return $qb
      ->select($qb->expr()->countDistinct("m.id"))
      ->andWhere($qb->expr()->between("m.date", ":from", ":to"))
      ->setParameter("from", $dateRange['start'])
      ->setParameter("to", $dateRange['end'])
      ->getQuery()->getSingleScalarResult();
  }

  private function executePresenceStatsPerDayOfWeekQuery(?Club $club, ?Activity $activity, \DateTimeImmutable $endDate, ?\DateTimeImmutable $startDate = null): array {
    $dateRange = SeasonService::calculateStartEndDate($club, $endDate, $startDate);
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

      if (!is_null($activity?->getId())) {
        $whereClauses[] = 'a.id = :activityId';
        $params['activityId'] = $activity->getId();
      } else {
        // We get all activities (excluding ignored ones)
        $ignoredActivities = $club->getSettings()?->getExcludedActivitiesFromOpeningDays();
        if ($ignoredActivities && !$ignoredActivities->isEmpty()) {
          $ignoredActivityIds = array_map(fn($actvt) => $actvt->getId(), $ignoredActivities->toArray());
          $whereClauses[] = 'a.id NOT IN (:ignoredActivities)';
          $params['ignoredActivities'] = $ignoredActivityIds;
          $paramTypes['ignoredActivities'] = ArrayParameterType::INTEGER;
        }
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
        SUM(daily_total) AS total,
        CAST(AVG(daily_total) AS NUMERIC(10,2)) AS average,
        PERCENTILE_CONT(0.25) WITHIN GROUP (ORDER BY daily_total) AS p25,
        PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY daily_total) AS median,
        PERCENTILE_CONT(0.75) WITHIN GROUP (ORDER BY daily_total) AS p75
      FROM daily_counts
      GROUP BY activity_name, dayofweek
      ORDER BY activity_name, dayofweek
    SQL;

    $result = $conn->executeQuery($sql, $params, $paramTypes)->fetchAllAssociative();
    return $this->formatPresenceStatsPerActivityThenDays($result);
  }

  private function formatPresenceStatsPerActivityThenDays(array $queryResults): array {
    $output = [];
    foreach ($queryResults as $queryResult) {
      $activityName = $queryResult['activity_name'] ?? 'undefined';
      $dayOfWeek = $queryResult['dayofweek'] ?? '-1';
      unset($queryResult['activity_name'], $queryResult['dayofweek']);

      $output[$activityName][$dayOfWeek] = $queryResult;
    }
    return $output;
  }
}
