<?php

namespace App\Repository\ClubDependent;

use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\MemberControl;
use App\Entity\ClubDependent\MemberControlType;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberControl>
 */
class MemberControlRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, MemberControl::class);
  }

  public function findOneByMemberAndType(Member $member, MemberControlType $type): ?MemberControl {
    return $this->findOneBy(['member' => $member, 'type' => $type]);
  }

  /**
   * Bulk-load all controls for a set of members, to avoid N+1 queries when hydrating a member list.
   *
   * @param Member[] $members
   * @return MemberControl[]
   */
  public function findAllByMembers(array $members): array {
    if (empty($members)) {
      return [];
    }

    return $this->createQueryBuilder('c')
      ->andWhere('c.member IN (:members)')
      ->setParameter('members', $members)
      ->getQuery()
      ->getResult();
  }
}
