<?php

namespace App\Repository\Trait;

use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
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

  public function countTotalPresencesYearlyUntilDate(?Club $club,\DateTime $maxDate): int {
    $startYear = (new \DateTime())
      ->setDate((int) $maxDate->format("Y"), 1, 1)
      ->setTime(0, 0, 0);

    $qb = $this->createQueryBuilder("m");
    if ($club) {
      $this->applyClubRestriction($qb, $club);
    }
    return $qb
      ->select($qb->expr()->count("m.id"))
      ->andWhere($qb->expr()->between("m.date", ":from", ":to"))
      ->setParameter("from", $startYear)
      ->setParameter("to", $maxDate)
      ->getQuery()->getSingleScalarResult();
  }

  public function countNumberOfPresenceDaysYearlyUntilDate(?Club $club, \DateTime $maxDate): int {
    $startYear = (new \DateTime())
      ->setDate((int) $maxDate->format("Y"), 1, 1)
      ->setTime(0, 0, 0);

    $qb = $this->createQueryBuilder("m");
    $this->applyActivityExclusionConstraint($club, $qb);

    return $qb
      ->select($qb->expr()->countDistinct("m.date"))
      ->andWhere($qb->expr()->between("m.date", ":from", ":to"))
      ->setParameter("from", $startYear)
      ->setParameter("to", $maxDate)
      ->getQuery()->getSingleScalarResult();
  }

  public function countTotalPresencesYearlyUntilToday(?Club $club): int {
    return $this->countTotalPresencesYearlyUntilDate($club, new \DateTime());
  }

  public function countTotalPresencesYearlyForPreviousYear(?Club $club): int {
    $lastYear = new \DateTime();
    $lastYear->setDate((int) $lastYear->format("Y") - 1, $lastYear->format("m"), $lastYear->format("d"));

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

  public function countNumberOfPresenceDaysYearlyUntilToday(?Club $club): int {
    return $this->countNumberOfPresenceDaysYearlyUntilDate($club, new \DateTime());
  }

  public function countNumberOfPresenceDaysYearlyForPreviousYear(?Club $club): int {
    $lastYear = new \DateTime();
    $lastYear->setDate((int) $lastYear->format("Y") - 1, $lastYear->format("m"), $lastYear->format("d"));

    return $this->countNumberOfPresenceDaysYearlyUntilDate($club, $lastYear);
  }

  public function countPresencesPerActivitiesYearlyUntilDate(?Club $club, \DateTime $maxDate) {
    $startYear = (new \DateTime())
      ->setDate((int) $maxDate->format("Y"), 1, 1)
      ->setTime(0, 0, 0);

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
      ->setParameter("from", $startYear)
      ->setParameter("to", $maxDate)
      ->getQuery()->getResult();
  }

  public function countPresencesPerActivitiesYearlyUntilToday(?Club $club) {
    return $this->countPresencesPerActivitiesYearlyUntilDate($club, new \DateTime());
  }

  public function countPresencesPerActivitiesYearlyForPreviousYear(?Club $club) {
    $lastYear = new \DateTime();
    $lastYear->setDate((int) $lastYear->format("Y") - 1, $lastYear->format("m"), $lastYear->format("d"));
    return $this->countPresencesPerActivitiesYearlyUntilDate($club, $lastYear);
  }
}
