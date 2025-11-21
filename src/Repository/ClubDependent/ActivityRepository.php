<?php

namespace App\Repository\ClubDependent;

use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
use App\Entity\ClubDependent\Plugin\Presence\ExternalPresence;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 *
 * @method Activity|null find($id, $lockMode = null, $lockVersion = null)
 * @method Activity|null findOneBy(array $criteria, array $orderBy = null)
 * @method Activity[]    findAll()
 * @method Activity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActivityRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Activity::class);
  }

  public function findOneByName(Club $club, string $name): ?Activity {
    $qb = $this->createQueryBuilder('a');
    $query = $qb
      ->andWhere($qb->expr()->like((string) $qb->expr()->lower('a.name'), (string) $qb->expr()->lower(':name')))
      ->andWhere("a.club = :club")
      ->setParameter('name', $name)
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

//  public function findByIds(array $activities): array {
//    $qb = $this->createQueryBuilder('a');
//    return $qb
//      ->andWhere($qb->expr()->in('a.uuid', ':activities'))
//      ->setParameter('activities', $activities)
//      ->getQuery()
//      ->getResult();
//  }

  public function mergeAndDelete(Activity $activityToMerge, Activity $targetActivity): void {
    // We update all the member presences
    $mps = $this->getEntityManager()->getRepository(MemberPresence::class)->findAllByActivity($activityToMerge);
    /** @var MemberPresence $mp */
    foreach ($mps as $mp) {
      $mp->removeActivity($activityToMerge);
      $mp->addActivity($targetActivity);
      $this->getEntityManager()->persist($mp);
    }

    // We update all the external presences
    $eps = $this->getEntityManager()->getRepository(ExternalPresence::class)->findAllByActivity($activityToMerge);
    /** @var ExternalPresence $ep */
    foreach ($eps as $ep) {
      $ep->removeActivity($activityToMerge);
      $ep->addActivity($targetActivity);
      $this->getEntityManager()->persist($ep);
    }

    $this->getEntityManager()->remove($activityToMerge);

    $this->getEntityManager()->flush();
  }
}
