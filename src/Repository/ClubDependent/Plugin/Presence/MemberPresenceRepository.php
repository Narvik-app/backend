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

}
