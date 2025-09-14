<?php

namespace App\Repository\ClubDependent;

use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\MemberSeason;
use App\Entity\Season;
use App\Repository\Trait\ClubLinkedTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberSeason>
 *
 * @method MemberSeason|null find($id, $lockMode = null, $lockVersion = null)
 * @method MemberSeason|null findOneBy(array $criteria, array $orderBy = null)
 * @method MemberSeason[]    findAll()
 * @method MemberSeason[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MemberSeasonRepository extends ServiceEntityRepository {
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, MemberSeason::class);
  }

  public function countTotalMembersForSeason(?Club $club, Season $season): int {
    $qb = $this->createQueryBuilder("m");
    if ($club) {
      $this->applyClubRestriction($qb, $club);
    }
    return $qb
      ->select($qb->expr()->count("m.id"))
      ->andWhere("m.season = :season")
      ->setParameter("season", $season)
      ->getQuery()->getSingleScalarResult();
  }

  public function countTotalMembersForThisSeason(?Club $club): int {
    $currentSeason = $this->getEntityManager()->getRepository(Season::class)->findCurrentSeason($club);
    if (!$currentSeason) return 0;
    return $this->countTotalMembersForSeason($club, $currentSeason);
  }

  public function countTotalMembersForPreviousSeason(?Club $club): int {
    $currentSeason = $this->getEntityManager()->getRepository(Season::class)->findPreviousSeason($club);
    if (!$currentSeason) return 0;
    return $this->countTotalMembersForSeason($club, $currentSeason);
  }

  public function findOneByMemberAndSeason(Member $member, Season $season): ?MemberSeason {
    $qb = $this->createQueryBuilder('m')
               ->andWhere('m.member = :member')
               ->andWhere('m.season = :season')
               ->setParameter('member', $member)
               ->setParameter('season', $season)
               ->setMaxResults(1);

    try {
      return $qb->getQuery()->getOneOrNullResult();
    }
    catch (\Exception) {
      return null;
    }
  }

//    /**
//     * @return MemberSeason[] Returns an array of MemberSeason objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('m.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }
}
