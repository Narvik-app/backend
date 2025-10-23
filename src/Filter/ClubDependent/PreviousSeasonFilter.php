<?php

namespace App\Filter\ClubDependent;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Filter\Abstract\AbstractClubDependentFilter;
use App\Repository\ClubRepository;
use App\Repository\SeasonRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class PreviousSeasonFilter extends AbstractClubDependentFilter {
  public const PROPERTY_NAME = "previous-season";

  public function __construct(private readonly SeasonRepository $seasonRepository, ClubRepository $clubRepository, ManagerRegistry $managerRegistry, ?LoggerInterface $logger = null, ?array $properties = null, ?NameConverterInterface $nameConverter = null) {
    parent::__construct($clubRepository, $managerRegistry, $logger, $properties, $nameConverter);
  }


  protected function filterProperty(string $property, $values, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void {

    if ($property !== static::PROPERTY_NAME) return;
    if (!is_array($values)) return;
    if ($this->properties === null) {
      return;
    }

    $acceptedFilterProps = $this->getAcceptedFilterProps();

    $previousSeason = $this->seasonRepository->findPreviousSeason($this->getSelfClub($queryBuilder));
    if (!$previousSeason) {
      return;
    }

    foreach ($values as $fields => $value) {
      if (!$this->toBoolean($value)) return;

      $passedFilterProps = array_map("trim", explode(",", $fields));
      if ($this->properties !== null) {
        // restrict http-passed properties to accepted filter properties only
        $passedFilterProps = array_intersect($passedFilterProps, $acceptedFilterProps);
      }
      // if no filter property matches supported resource properties
      // do not take into account the current multiple filter
      if (empty($passedFilterProps) || count($passedFilterProps) !== 1) return;

      $rootAlias = $queryBuilder->getRootAliases()[0];
      $queryBuilder->andWhere($this->buildFilterClause($queryBuilder, $passedFilterProps[0], $rootAlias, $queryNameGenerator));
      $queryBuilder->setParameter(":previousSeason", $previousSeason);
    }
  }

  private function getAcceptedFilterProps(): array {
    $acceptedFilterProps = [];
    foreach (array_keys($this->properties) as $filterProps) {
      $acceptedFilterProps = array_merge($acceptedFilterProps, array_map("trim", explode(",", (string) $filterProps)));
    }
    return array_unique($acceptedFilterProps);
  }

  private function buildFilterClause(QueryBuilder $queryBuilder, string $field, string $rootAlias, QueryNameGeneratorInterface $queryNameGenerator) {
    $clauseField = $this->buildClauseField($rootAlias, $field, $queryBuilder, $queryNameGenerator);
    return $queryBuilder->expr()->eq($clauseField, ':previousSeason');
  }

  public function getDescription(string $resourceClass): array {
    if (!$this->properties) {
      return [];
    }

    $description = [];
    foreach ($this->properties as $property => $value) {
      $description[self::PROPERTY_NAME . '[' . $property . ']'] = [
        'property' => $property,
        'type' => Type::BUILTIN_TYPE_BOOL,
        'required' => false,
        'description' => 'Force the query to be only for previous season.',
      ];
    }
    return $description;
  }
}
