<?php

namespace App\Repository\ClubDependent;

use App\Entity\ClubDependent\PermissionTemplate;
use App\Repository\Trait\ClubLinkedTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PermissionTemplate>
 */
class PermissionTemplateRepository extends ServiceEntityRepository {
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, PermissionTemplate::class);
  }
}
