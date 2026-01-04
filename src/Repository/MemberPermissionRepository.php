<?php

namespace App\Repository;

use App\Entity\ClubDependent\MemberPermission;
use App\Entity\UserMember;
use App\Enum\Permission;
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
   * Find all permissions for a given UserMember
   * @return MemberPermission[]
   */
  public function findByUserMember(UserMember $userMember): array {
    return $this->findBy(['userMember' => $userMember]);
  }

  /**
   * Check if a UserMember has a specific permission
   */
  public function hasPermission(UserMember $userMember, Permission $permission): bool {
    return $this->findOneBy([
      'userMember' => $userMember,
      'permission' => $permission,
    ]) !== null;
  }

  /**
   * Get all permission values for a UserMember
   * @return Permission[]
   */
  public function getPermissionValues(UserMember $userMember): array {
    $permissions = $this->findByUserMember($userMember);
    return array_map(fn(MemberPermission $mp) => $mp->getPermission(), $permissions);
  }
}
