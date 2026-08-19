<?php

namespace App\Repository\ClubDependent;

use App\DQL\CustomExpr;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Entity\Season;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Member>
 *
 * @implements PasswordUpgraderInterface<Member>
 *
 * @method Member|null find($id, $lockMode = null, $lockVersion = null)
 * @method Member|null findOneBy(array $criteria, array $orderBy = null)
 * @method Member[]    findAll()
 * @method Member[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MemberRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Member::class);
  }


  /**
   * Will return matching member based on the input string
   * Will be excluded user that don't have a licence number set (i.e: badger, default admin)
   *
   * @param Club   $club
   * @param string $string
   *
   * @return array
   */
  public function findByLicenceOrName(Club $club, string $string): array {
    $string = mb_trim($string);

    $qb = $this->createQueryBuilder("m");
    $qb
      ->andWhere(
        $qb->expr()->orX(
          $qb->expr()->like('m.licence', ':licence'),
          $qb->expr()->like(CustomExpr::unaccentInsensitive('m.firstname'), CustomExpr::unaccentInsensitive(':name')),
          $qb->expr()->like(CustomExpr::unaccentInsensitive('m.lastname'), CustomExpr::unaccentInsensitive(':name')),
          $qb->expr()->like(CustomExpr::unaccentInsensitive($qb->expr()->concat('m.lastname ', 'm.firstname')), CustomExpr::unaccentInsensitive(':name')),
          $qb->expr()->like(CustomExpr::unaccentInsensitive($qb->expr()->concat('m.firstname ', 'm.lastname')), CustomExpr::unaccentInsensitive(':name')),
        ),
      );
    $qb->andWhere(
      $qb->expr()->isNotNull('m.licence'),
    );
    $qb->andWhere('m.club = :club');

    $matches = [];
    preg_match("/^(\d{8,})/m", $string, $matches);

    if (!empty($matches)) {
      $qb->setParameter('licence', $matches[1]);
    } else {
      $qb->setParameter('licence', $string . '%');
    }

    $qb
      ->setParameter('name', '%' . str_replace(' ', '%', $string) . '%')
      ->setParameter('club', $club)
      ->setMaxResults(10);

    return $qb->getQuery()->getResult();
  }

  public function findOneByLicence(Club $club, string $licence): ?Member {
    $query = $this->createQueryBuilder('m')
      ->andWhere('m.licence = :licence')
      ->andWhere('m.club = :club')
      ->setParameter('licence', $licence)
      ->setParameter('club', $club)
      ->setMaxResults(1)
      ->getQuery();

    try {
      return $query->getOneOrNullResult();
    }
    catch (\Exception) {
      return null;
    }
  }

  public function findOneByEmail(Club $club, string $email): ?Member {
    $query = $this->createQueryBuilder('m')
      ->andWhere('m.email = :email')
      ->andWhere('m.club = :club')
      ->setParameter('email', $email)
      ->setParameter('club', $club)
      ->setMaxResults(1)
      ->getQuery();

    try {
      return $query->getOneOrNullResult();
    }
    catch (\Exception) {
      return null;
    }
  }

  public function findAllNotPresentToday(Club $club): array {
    $qb = $this->createQueryBuilder('u');

    $memberAlreadyPresents = [];
    $presents = $this->getEntityManager()->getRepository(MemberPresence::class)->findAllPresentToday($club);
    foreach ($presents as $p) {
      $memberAlreadyPresents[] = $p->getMember()->getId();
    }

    if (!empty($memberAlreadyPresents)) {
      $qb->andWhere(
        $qb->expr()->notIn('u.id', ':presentUsers')
      );
      $qb->setParameter('presentUsers', $memberAlreadyPresents);
    }

    $qb
      ->andWhere('u.club = :club')
      ->setParameter('club', $club);

    $qb
      ->addOrderBy('u.lastname', 'ASC')
      ->addOrderBy('u.firstname', 'ASC');

    return $qb->getQuery()->getResult();
  }

  public function findAllByEmail(string $email): array {
    $query = $this->createQueryBuilder('m')
      ->andWhere('m.email = :email')
      ->setParameter('email', $email)
      ->getQuery();

    return $query->getResult();
  }

  public function countTotalClubMembers(?Club $club, ?Season $currentSeason = null): int {
    $qb = $this->createQueryBuilder("m");
    if ($club) {
      $this->applyClubRestriction($qb, $club);
    }
    if ($currentSeason) {
      $qb->innerJoin('m.memberSeasons', 'ms')
         ->andWhere('ms.season = :currentSeason')
         ->setParameter('currentSeason', $currentSeason);
    }

    return $qb
      ->select($qb->expr()->count("m.id"))
      ->andWhere($qb->expr()->isNotNull("m.licence"))
      ->getQuery()->getSingleScalarResult();
  }

  /**
   * @param Club $club
   * @param array $uuids
   * @param bool $isNewsletter
   * @return Member[]
   */
  public function getAllByUuidsAndNewsletter(Club $club, array $uuids, bool $isNewsletter): array {
    $qb = $this->qbByUuids($club, $uuids);
    if ($isNewsletter) {
      $qb
        ->andWhere("m.clubNewsletter = :clubNewsletter")
        ->setparameter('clubNewsletter', true);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Lightweight projection of every member's uuid for a club — used to fan out background jobs
   * without hydrating full Member entities just to read their uuid.
   *
   * @return string[]
   */
  public function findAllUuidsByClub(Club $club): array {
    $qb = $this->createQueryBuilder('m');
    $this->applyClubRestriction($qb, $club);

    return array_column(
      $qb->select('m.uuid')->getQuery()->getScalarResult(),
      'uuid'
    );
  }

  /**
   * @param Club $club
   * @param string[] $uuids
   * @return Member[]
   */
  public function findAllByUuids(Club $club, array $uuids): array {
    return $this->qbByUuids($club, $uuids)->getQuery()->getResult();
  }

  private function qbByUuids(Club $club, array $uuids): QueryBuilder {
    $qb = $this->createQueryBuilder("m");
    $this->applyClubRestriction($qb, $club);

    return $qb
      ->andWhere($qb->expr()->in('m.uuid', ':uuids'))
      ->setParameter('uuids', $uuids);
  }
}
