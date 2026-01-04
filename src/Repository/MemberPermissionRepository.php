<?php

namespace App\Repository;

use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\MemberPermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberPermission>
 */
class MemberPermissionRepository extends ServiceEntityRepository {
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, MemberPermission::class);
  }

  /**
   * Find all permissions for a given Member
   * @return MemberPermission[]
   */
  public function findByMember(Member $member): array {
    return $this->findBy(['member' => $member]);
  }
}
