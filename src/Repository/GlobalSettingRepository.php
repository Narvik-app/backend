<?php

namespace App\Repository;

use App\Entity\GlobalSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GlobalSetting>
 *
 * @method GlobalSetting|null find($id, $lockMode = null, $lockVersion = null)
 * @method GlobalSetting|null findOneBy(array $criteria, array $orderBy = null)
 * @method GlobalSetting[]    findAll()
 * @method GlobalSetting[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GlobalSettingRepository extends ServiceEntityRepository {
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, GlobalSetting::class);
  }

  public function findOneByName(string $name): ?GlobalSetting {
    $query = $this->createQueryBuilder("g")
                  ->andWhere("g.name = :name")
                  ->setParameter("name", $name)
                  ->setMaxResults(1)
                  ->getQuery();

    try {
      return $query->getOneOrNullResult();
    }
    catch (\Exception) {
      return null;
    }
  }
}
