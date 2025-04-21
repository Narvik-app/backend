<?php

namespace App\Filter\Abstract;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Club;
use App\Repository\ClubRepository;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

abstract class AbstractClubDependentFilter extends AbstractFilter {
  public function __construct(
    protected ClubRepository $clubRepository,
    ?ManagerRegistry $managerRegistry = null,
    ?LoggerInterface $logger = null,
    ?array $properties = null,
    ?NameConverterInterface $nameConverter = null,
  ) {
    parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
  }


  public function getClubUuid(QueryBuilder $queryBuilder): ?string {
    /** @var Parameter|null $parameter */
    $parameter = $queryBuilder->getParameters()[0] ?? null;
    if ($parameter && $parameter->getValue() instanceof UuidInterface) {
      return $parameter->getValue();
    }
    return null;
  }

  public function getSelfClub(QueryBuilder $queryBuilder): ?Club {
    $clubUUID = $this->getClubUuid($queryBuilder);
    if (!$clubUUID) {
      return null;
    }

    return $this->clubRepository->findOneByUuid($clubUUID);
  }

  /**
   * Only work for query (setParameter don't work if set on a subQuery like in currentSeasonFilter)
   *
   * @param QueryBuilder $queryBuilder
   * @param QueryNameGeneratorInterface $queryNameGenerator
   * @return void
   */
  public function addSelfClubJoin(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator): void {
    $clubUuid = $this->getClubUuid($queryBuilder);

    if ($clubUuid) {
      $joinAlias = $queryNameGenerator->generateJoinAlias("ja_club");
      $queryBuilder->leftJoin("m.club", $joinAlias);

      $queryBuilder
        ->andWhere($queryBuilder->expr()->eq("$joinAlias.uuid", ":c"));
      $queryBuilder->setParameter("c", $clubUuid);
    }
  }

}
