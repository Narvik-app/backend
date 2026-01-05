<?php

namespace App\Repository\ClubDependent;

use App\Entity\ClubDependent\PermissionTemplate;
use App\Entity\Club;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PermissionTemplate>
 */
class PermissionTemplateRepository extends ServiceEntityRepository {
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, PermissionTemplate::class);
  }

  /**
   * Find all templates for a given club
   * @return PermissionTemplate[]
   */
  public function findByClub(Club $club): array {
    return $this->findBy(['club' => $club], ['name' => 'ASC']);
  }
}
