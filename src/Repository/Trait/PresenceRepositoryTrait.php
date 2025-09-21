<?php

namespace App\Repository\Trait;

use App\DQL\CustomExpr;
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

  public function getCountPerDayOfWeek(?Club $club, \DateTimeImmutable $maxDate) {
    $dateRange = $this->calculateStartEndDate($club, $maxDate);

    //TODO: For the average or median to work
    // Transform it instead to have a subselect query that get all based on day of week\
    // Find a way to get the average and percentile per day of week


    /** GET Total per day
      SELECT
      a0_.name AS name_0,
      COUNT(a0_.name) AS total,
      m1_.date
      FROM member_presence m1_
      INNER JOIN member_presence_activity m2_ ON m1_.id = m2_.member_presence_id
      INNER JOIN activity a0_ ON a0_.id = m2_.activity_id
      WHERE
      m1_.date BETWEEN '2025-01-01 00:00:00' AND '2025-09-20 14:39:41'
      GROUP BY m1_.date, name_0
      ORDER BY name_0 asc, m1_.date ASC;
     */

    /** GET Total groupe by day of week
      SELECT
      a0_.name AS name_0,
      COUNT(a0_.name) AS total,
      extract(dow from m1_.date) AS dayofweek
      FROM member_presence m1_
      INNER JOIN member_presence_activity m2_ ON m1_.id = m2_.member_presence_id
      INNER JOIN activity a0_ ON a0_.id = m2_.activity_id
      WHERE
      m1_.date BETWEEN '2025-01-01 00:00:00' AND '2025-09-20 14:39:41'
      GROUP BY dayofweek, name_0
      ORDER BY name_0 asc, dayofweek ASC;
     */

    /** GET All per weekday and with the stats, now have to convert it into a more DQL friendly query
      WITH daily_counts AS (
      SELECT
      a0_.name AS activity_name,
      DATE(m1_.date) AS day,
      EXTRACT(DOW FROM m1_.date) AS dayofweek,
      COUNT(*) AS daily_total
      FROM member_presence m1_
      INNER JOIN member_presence_activity m2_
      ON m1_.id = m2_.member_presence_id
      INNER JOIN activity a0_
      ON a0_.id = m2_.activity_id
      WHERE m1_.date BETWEEN '2025-01-01 00:00:00' AND '2025-09-20 14:39:41'
      GROUP BY a0_.name, DATE(m1_.date), EXTRACT(DOW FROM m1_.date)
      )
      SELECT
      activity_name,
      dayofweek,
      SUM(daily_total) AS total_presences,            -- total for this weekday across period
      AVG(daily_total)::numeric(10,2) AS avg_per_day, -- average presences per weekday
      PERCENTILE_CONT(0.25) WITHIN GROUP (ORDER BY daily_total) AS p25,
      PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY daily_total) AS median,
      PERCENTILE_CONT(0.75) WITHIN GROUP (ORDER BY daily_total) AS p75
      FROM daily_counts
      GROUP BY activity_name, dayofweek
      ORDER BY activity_name, dayofweek;
     */

    // Take inspiration on: https://gist.github.com/froozeify/41bf63b49a98d53be6205b702b1dbf0b

//    $qb = $this->createQueryBuilder("m");
//    $a = $qb
//      ->select("a.name")
//      ->addSelect($qb->expr()->count("a.name") . ' AS total')
////      ->addSelect($qb->expr()->avg("a.name") . ' AS avg')
//      ->addSelect(CustomExpr::dayOfWeek("m.date") . ' AS dayofweek')
//      ->innerJoin("m.activities", "a")
//      ->addgroupBy("dayofweek", "a.name")
//      ->orderBy("a.name")
//      ->addOrderBy("dayofweek")
//
//      ->andWhere($qb->expr()->between("m.date", ":from", ":to"))
//      ->setParameter("from", $dateRange['start'])
//      ->setParameter("to", $dateRange['end'])
//      ->getQuery()->getResult();
//
//    dump($a);
//
//    return $a;
//
//    $dailyCount = $this->createQueryBuilder("pa")
//      ->select("a.name as activity_name")
//      ->addSelect("DATE(p.date) as day)")

    $conn = $this->getEntityManager()->getConnection();

    dump($this->getClassMetadata()->getTableName());

    // --- Inner query: daily counts ---
    // --- Inner query: daily counts ---
    $dailyCounts = $conn->createQueryBuilder();
    $dailyCounts
      ->select("a0_.name AS activity_name")
      ->addSelect("DATE(m1_.date) AS day")
      ->addSelect("EXTRACT(DOW FROM m1_.date) AS dayofweek")
      ->addSelect("COUNT(*) AS daily_total")
      ->from("member_presence", "m1_")
      ->innerJoin("m1_", "member_presence_activity", "m2_", "m1_.id = m2_.member_presence_id")
      ->innerJoin("m2_", "activity", "a0_", "a0_.id = m2_.activity_id")
      ->where("m1_.date BETWEEN :from AND :to")
      ->groupBy("a0_.name, DATE(m1_.date), EXTRACT(DOW FROM m1_.date)");

    // --- Outer query: aggregate per activity + weekday ---
    $qb = $conn->createQueryBuilder();
    $qb
      ->select("activity_name")
      ->addSelect("dayofweek")
      ->addSelect("SUM(daily_total) AS total_presences")
      ->addSelect("AVG(daily_total)::numeric(10,2) AS avg_per_day")
      ->addSelect("PERCENTILE_CONT(0.25) WITHIN GROUP (ORDER BY daily_total) AS p25")
      ->addSelect("PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY daily_total) AS median")
      ->addSelect("PERCENTILE_CONT(0.75) WITHIN GROUP (ORDER BY daily_total) AS p75")
      ->from("(" . $dailyCounts->getSQL() . ")", "dc")
      ->setParameter("from", $dateRange['start']->format('Y-m-d H:i:s'))
      ->setParameter("to", $dateRange['end']->format('Y-m-d H:i:s'))
      ->groupBy("activity_name, dayofweek")
      ->orderBy("activity_name")
      ->addOrderBy("dayofweek");

    return $conn->executeQuery($qb->getSQL(), $qb->getParameters())->fetchAllAssociative();  }

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
    dump($this->getCountPerDayOfWeek($club, $maxDate));

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
}
