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
   *
   * @param QueryBuilder $queryBuilder
   * @param QueryNameGeneratorInterface $queryNameGenerator
   * @param QueryBuilder|null $rootQuery In the case of a subquery, we must specify the root qb so we can set the parameter correctly (if the parameter is set on the subquery doctrine throw an exception)
   * @return string|null Return the current club uuid
   */
  public function addSelfClubJoin(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, ?QueryBuilder $rootQuery = null): ?string {
    $clubUuid = $this->getClubUuid($rootQuery ?? $queryBuilder);

    if ($clubUuid) {
      $joinAlias = $queryNameGenerator->generateJoinAlias("ja_club");
      $queryBuilder->leftJoin("m.club", $joinAlias);

      $queryBuilder
        ->andWhere($queryBuilder->expr()->eq("$joinAlias.uuid", ":self_club_uuid"));

      if ($rootQuery) {
        $rootQuery->setParameter("self_club_uuid", $clubUuid);
      } else {
        $queryBuilder->setParameter("self_club_uuid", $clubUuid);
      }
    }

    return $clubUuid;
  }

}
