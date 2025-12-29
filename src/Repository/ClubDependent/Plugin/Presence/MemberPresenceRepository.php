<?php

namespace App\Repository\ClubDependent\Plugin\Presence;

use App\Entity\ClubDependent\Activity;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Interface\PresenceRepositoryInterface;
use App\Repository\Trait\PresenceRepositoryTrait;
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
   * 
   * @param \App\Entity\Club|null $club
   * @param \DateTimeImmutable $endDate
   * @param \DateTimeImmutable|null $startDate
   * @param string $order Sort order (ASC or DESC)
   * @param int $page Page number (1-based)
   * @param int $itemsPerPage Number of items per page
   * @return array Array of statistics with member info, presence count, and last presence date
   */
  public function getMemberPresenceStats(
    ?\App\Entity\Club $club, 
    \DateTimeImmutable $endDate, 
    ?\DateTimeImmutable $startDate = null,
    string $order = 'DESC',
    int $page = 1,
    int $itemsPerPage = 30
  ): array {
    $dateRange = \App\Service\SeasonService::calculateStartEndDate($club, $endDate, $startDate);
    
    // Validate order
    $order = strtoupper($order);
    if (!in_array($order, ['ASC', 'DESC'])) {
      $order = 'DESC';
    }
    
    // Validate pagination parameters
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
   * @param \App\Entity\Club|null $club
   * @param \DateTimeImmutable $endDate
   * @param \DateTimeImmutable|null $startDate
   * @return int Total count of members
   */
  public function countMemberPresenceStats(
    ?\App\Entity\Club $club, 
    \DateTimeImmutable $endDate, 
    ?\DateTimeImmutable $startDate = null
  ): int {
    $dateRange = \App\Service\SeasonService::calculateStartEndDate($club, $endDate, $startDate);
    
    $qb = $this->createQueryBuilder('mp');
    $qb
      ->select('COUNT(DISTINCT mp.member)')
      ->where($qb->expr()->between('mp.date', ':from', ':to'))
      ->setParameter('from', $dateRange['start'])
      ->setParameter('to', $dateRange['end']);
    
    if ($club) {
      $qb->andWhere('mp.club = :club')
         ->setParameter('club', $club);
    }
    
    return (int) $qb->getQuery()->getSingleScalarResult();
  }

}
