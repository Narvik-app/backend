<?php

namespace App\Repository\ClubDependent;

use App\Entity\Club;
use App\Entity\ClubDependent\ClubJob;
use App\Enum\ClubJobKey;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClubJob>
 */
class ClubJobRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, ClubJob::class);
  }

  public function findOneByClubAndKey(Club $club, ClubJobKey $key): ?ClubJob {
    return $this->findOneBy(['club' => $club, 'key' => $key]);
  }
}
