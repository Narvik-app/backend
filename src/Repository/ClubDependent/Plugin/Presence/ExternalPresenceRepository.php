<?php

namespace App\Repository\ClubDependent\Plugin\Presence;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Presence\ExternalPresence;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Interface\PresenceRepositoryInterface;
use App\Repository\Trait\PresenceRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExternalPresence>
 *
 * @method ExternalPresence|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExternalPresence|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExternalPresence[]    findAll()
 * @method ExternalPresence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExternalPresenceRepository extends ServiceEntityRepository implements PresenceRepositoryInterface, ClubLinkedInterface {
  use PresenceRepositoryTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, ExternalPresence::class);
  }

  public function findAllWithLicence(Club $club): ?array {
    $qb = $this->createQueryBuilder('e');
    return $qb
      ->where($qb->expr()->isNotNull("e.licence"))
      ->andWhere("e.club = :club")
      ->setParameter("club", $club)
      ->orderBy("e.licence", "ASC")
      ->getQuery()->getResult();
  }

  public function findOneByDay(Club $club, string $firstname, string $lastname, \DateTimeImmutable $date): ?ExternalPresence {
    $qb = $this->createQueryBuilder('m');
    $this->applyClubRestriction($qb, $club);
    $query = $this
      ->applyDayConstraint($qb, $date)
      ->andWhere($qb->expr()->eq((string) $qb->expr()->lower("m.firstname"), (string) $qb->expr()->lower(":firstname")))
      ->andWhere($qb->expr()->eq((string) $qb->expr()->lower("m.lastname"), (string) $qb->expr()->lower(":lastname")))
      ->setParameter("firstname", $firstname)
      ->setParameter("lastname", $lastname)
      ->setMaxResults(1)
      ->getQuery();

    try {
      return $query->getOneOrNullResult();
    } catch (\Exception) {
      return null;
    }
  }

}
