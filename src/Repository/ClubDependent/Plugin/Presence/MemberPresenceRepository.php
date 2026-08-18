<?php

namespace App\Repository\ClubDependent\Plugin\Presence;

use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\MemberControl;
use App\Entity\ClubDependent\MemberControlType;
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
   * Get member presence statistics with count and last presence date.
   * The results are restricted to members present in the current season if $currentSeason is provided.
   *
   * @param Club|null $club
   * @param \DateTimeImmutable $endDate
   * @param \DateTimeImmutable|null $startDate
   * @param array $orderBy Associative array of field => direction, e.g. ['presenceCount' => 'DESC'].
   *                       Allowed fields: presenceCount, lastPresenceDate, medicalCertificateExpiration,
   *                       plus one `control_<uuid>` per club MemberControlType.
   * @param int $page Page number (1-based)
   * @param int $itemsPerPage Number of items per page
   * @param Season|null $currentSeason
   * @param MemberControlType[] $controlTypes Club's control types; one `control_<uuid>` column/alias is added per type
   * @return array Array of statistics with member info, presence count, and last presence date
   */
  public function getMemberPresenceStats(
    ?Club $club,
    \DateTimeImmutable $endDate,
    ?\DateTimeImmutable $startDate = null,
    array $orderBy = ['presenceCount' => 'DESC'],
    int $page = 1,
    int $itemsPerPage = 30,
    ?Season $currentSeason = null,
    array $controlTypes = []
  ): array {
    $dateRange = SeasonService::calculateStartEndDate($club, $endDate, $startDate);

    $page = max(1, $page);

    // We select from Member to include those with 0 presences
    $qb = $this->getEntityManager()->createQueryBuilder();
    $qb->from(Member::class, 'mem');

    $qb
      ->select('mem.uuid as memberUuid')
      ->addSelect('COUNT(DISTINCT mp.id) as presenceCount')
      ->addSelect('MAX(mp.date) as lastPresenceDate')
      ->addSelect('mem.firstname')
      ->addSelect('mem.lastname')
      ->addSelect('mem.licence')
      ->addSelect('mem.medicalCertificateExpiration')
      // Left join presences filtered by date range
      ->leftJoin('mem.memberPresences', 'mp', Join::WITH, $qb->expr()->between('mp.date', ':from', ':to'))
      ->setParameter('from', $dateRange['start'])
      ->setParameter('to', $dateRange['end']);

    $controlAliases = [];
    foreach ($controlTypes as $controlType) {
      $alias = 'control_' . str_replace('-', '_', (string) $controlType->getUuid());
      $controlAliases[$controlType->getUuid()->toString()] = $alias;

      $subQb = $this->getEntityManager()->createQueryBuilder();
      $subQb->select("MAX({$alias}_c.date)")
        ->from(MemberControl::class, "{$alias}_c")
        ->where("{$alias}_c.member = mem")
        ->andWhere("{$alias}_c.type = :{$alias}Type");
      $qb->addSelect('(' . $subQb->getDQL() . ") as {$alias}")
         ->setParameter("{$alias}Type", $controlType);
    }

    if ($club) {
      $this->applyClubRestriction($qb, $club);
      $this->applyActivityExclusionConstraint($club, $qb, 'mp');
    }

    if ($currentSeason) {
      $qb->innerJoin('mem.memberSeasons', 'ms')
         ->andWhere('ms.season = :currentSeason')
         ->setParameter('currentSeason', $currentSeason);
    }

    // Allowed sortable fields mapped to their DQL expression
    $allowedFields = [
      'presenceCount'              => 'presenceCount',
      'lastPresenceDate'           => 'lastPresenceDate',
      'medicalCertificateExpiration' => 'mem.medicalCertificateExpiration',
    ];
    foreach ($controlAliases as $alias) {
      $allowedFields[$alias] = $alias;
    }

    foreach ($orderBy as $field => $direction) {
      if (!isset($allowedFields[$field])) {
        continue;
      }
      $qb->addOrderBy($allowedFields[$field], $direction);
    }

    // Stability sort by name
    $qb->addOrderBy('mem.lastname', 'ASC')
       ->addOrderBy('mem.firstname', 'ASC');

    $qb
      ->groupBy('mem.id')
      ->addGroupBy('memberUuid')
      ->addGroupBy('mem.firstname')
      ->addGroupBy('mem.lastname')
      ->addGroupBy('mem.licence')
      ->addGroupBy('mem.medicalCertificateExpiration');

    if ($itemsPerPage > 0) {
        $qb->setFirstResult(($page - 1) * $itemsPerPage)
           ->setMaxResults($itemsPerPage);
    }

    return $qb->getQuery()->getResult();
  }

}
