<?php

namespace App\Repository\ClubDependent\Plugin\Presence;

use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Entity\Season;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Interface\PresenceRepositoryInterface;
use App\Repository\Trait\PresenceRepositoryTrait;
use App\Service\SeasonService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberPresence>
 *
 * @method MemberPresence|null find($id, $lockMode = null, $lockVersion = null)
 * @method MemberPresence|null findOneBy(array $criteria, array $orderBy = null)
 * @method MemberPresence[]    findAll()
 * @method MemberPresence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MemberPresenceRepository extends ServiceEntityRepository implements PresenceRepositoryInterface, ClubLinkedInterface {
  use PresenceRepositoryTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, MemberPresence::class);
  }

  public function findOneToday(Member $member): ?MemberPresence {
    return $this->findOneByDay($member, new \DateTimeImmutable());
  }

  public function findOneByDay(Member $member, \DateTimeImmutable $date): ?MemberPresence {
    $qb = $this->createQueryBuilder('m');
    $query = $this
      ->applyDayConstraint($qb, $date)
      ->andWhere("m.member = :member")
      ->setParameter("member", $member)
      ->setMaxResults(1)
      ->getQuery();

    try {
      return $query->getOneOrNullResult();
    } catch (\Exception) {
      return null;
    }
  }

  public function findLastOneByActivity(Member $member, Activity $activity): ?MemberPresence {
    $qb = $this->createQueryBuilder('m');
    $query = $qb
      ->andWhere("m.member = :member")
      ->innerJoin("m.activities", "a", Join::WITH, $qb->expr()->eq("a.id", ":activity"))
      ->orderBy("m.date", "DESC")
      ->setParameter("activity", $activity)
      ->setParameter("member", $member)
      ->setMaxResults(1)
      ->getQuery();

    try {
      return $query->getOneOrNullResult();
    }
    catch (\Exception) {
      return null;
    }
  }

  /**
   * Get member presence statistics with count and last presence date
   * The results are restricted to members present in the current season if $currentSeason is provided.
   *
   * @param Club|null $club
   * @param \DateTimeImmutable $endDate
   * @param \DateTimeImmutable|null $startDate
   * @param string $order Sort order (ASC or DESC)
   * @param int $page Page number (1-based)
   * @param int $itemsPerPage Number of items per page
   * @param Season|null $currentSeason
   * @return array Array of statistics with member info, presence count, and last presence date
   */
  public function getMemberPresenceStats(
    ?Club $club,
    \DateTimeImmutable $endDate,
    ?\DateTimeImmutable $startDate = null,
    string $order = 'ASC',
    int $page = 1,
    int $itemsPerPage = 30,
    ?Season $currentSeason = null
  ): array {
    $dateRange = SeasonService::calculateStartEndDate($club, $endDate, $startDate);

    // Validate and normalize order
    $order = strtoupper($order);
    if (!in_array($order, ['ASC', 'DESC'], true)) {
      $order = 'ASC';
    }

    // Validate pagination parameters with defensive bounds
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));

    $qb = $this->createQueryBuilder('mp');
    $qb
      ->select('IDENTITY(mp.member) as memberId')
      ->addSelect('COUNT(mp.id) as presenceCount')
      ->addSelect('MAX(mp.date) as lastPresenceDate')
      ->addSelect('mem.firstname')
      ->addSelect('mem.lastname')
      ->addSelect('mem.licence')
      ->innerJoin('mp.member', 'mem')
      ->where($qb->expr()->between('mp.date', ':from', ':to'))
      ->setParameter('from', $dateRange['start'])
      ->setParameter('to', $dateRange['end']);

    if ($club) {
      $qb->andWhere('mp.club = :club')
         ->setParameter('club', $club);
    }

    if ($currentSeason) {
      $qb->innerJoin('mem.memberSeasons', 'ms')
         ->andWhere('ms.season = :currentSeason')
         ->setParameter('currentSeason', $currentSeason);
    }

    $qb
      ->groupBy('memberId')
      ->addGroupBy('mem.firstname')
      ->addGroupBy('mem.lastname')
      ->addGroupBy('mem.licence')
      ->orderBy('presenceCount', $order)
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage);

    return $qb->getQuery()->getResult();
  }

  /**
   * Get total count of members with presences for pagination
   *
   * @param Club|null $club
   * @param \DateTimeImmutable $endDate
   * @param \DateTimeImmutable|null $startDate
   * @param Season|null $currentSeason
   * @return int Total count of members
   */
  public function countMemberPresenceStats(
    ?Club $club,
    \DateTimeImmutable $endDate,
    ?\DateTimeImmutable $startDate = null,
    ?Season $currentSeason = null
  ): int {
    $dateRange = SeasonService::calculateStartEndDate($club, $endDate, $startDate);

    $qb = $this->createQueryBuilder('mp');
    $qb
      ->select('COUNT(DISTINCT mp.member)')
      ->where($qb->expr()->between('mp.date', ':from', ':to'))
      ->setParameter('from', $dateRange['start'])
      ->setParameter('to', $dateRange['end']);

    if ($currentSeason) {
      $qb->innerJoin('mp.member', 'mem')
         ->innerJoin('mem.memberSeasons', 'ms')
         ->andWhere('ms.season = :currentSeason')
         ->setParameter('currentSeason', $currentSeason);
    }

    if ($club) {
      $qb->andWhere('mp.club = :club')
         ->setParameter('club', $club);
    }

    return (int) $qb->getQuery()->getSingleScalarResult();
  }

}
