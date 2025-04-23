<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\Season;
use App\Service\SeasonService;
use App\Service\UtilsService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Season>
 *
 * @method Season|null find($id, $lockMode = null, $lockVersion = null)
 * @method Season|null findOneBy(array $criteria, array $orderBy = null)
 * @method Season[]    findAll()
 * @method Season[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeasonRepository extends ServiceEntityRepository {
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Season::class);
  }

  public function findOneByName(string $seasonName): ?Season {
    $query = $this->createQueryBuilder("s")
                  ->andWhere("s.name = :seasonName")
                  ->setParameter("seasonName", $seasonName)
                  ->setMaxResults(1)
                  ->getQuery();

    try {
      return $query->getOneOrNullResult();
    }
    catch (\Exception) {
      return null;
    }
  }

  public function findOrCreateOneByName(string $seasonName, bool $autoFlush = true): ?Season {
    $seasonName = trim(str_replace(" ", "", $seasonName));
    // Season name must be in format 20xx/20xx
    if (strlen($seasonName) !== 9) {
      return null;
    }

    $seasons = explode("/", $seasonName, 2);
    if (!is_numeric($seasons[0]) || !is_numeric($seasons[1])) {
      return null;
    }

    $foundSeasons = $this->findOneByName($seasonName);
    if ($foundSeasons) {
      return $foundSeasons;
    }

    $season = new Season();
    $season->setName("$seasons[0]/$seasons[1]");
    $this->getEntityManager()->persist($season);

    if ($autoFlush) {
      $this->getEntityManager()->flush();
    }

    return $season;
  }

  public function findCurrentSeason(?Club $club = null): ?Season  {
    return $this->findOrCreateOneByName(SeasonService::getCurrentSeasonName($club));
  }

  public function findPreviousSeason(?Club $club = null): ?Season  {
    return $this->findOrCreateOneByName(SeasonService::getPreviousSeasonName($club));
  }
}
