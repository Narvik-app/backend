<?php

namespace App\Filter\ClubDependent;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\ClubDependent\Member;
use App\Entity\Season;
use App\Filter\Abstract\AbstractClubDependentFilter;
use App\Repository\ClubRepository;
use App\Repository\SeasonRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class CurrentSeasonFilter extends AbstractClubDependentFilter {
  public const PROPERTY_NAME = "current-season";

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

    $currentSeason = $this->seasonRepository->findCurrentSeason($this->getSelfClub($queryBuilder));
    if (!$currentSeason) {
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

      if ($resourceClass === Member::class) {
        $this->buildMemberFilterClause($queryBuilder, $passedFilterProps[0], $rootAlias, $queryNameGenerator, $currentSeason);
      } else { // We apply the filter on the field
        $this->buildDateFilterClause($queryBuilder, $passedFilterProps[0], $rootAlias, $queryNameGenerator);
      }
    }
  }

  private function getAcceptedFilterProps(): array {
    $acceptedFilterProps = [];
    foreach (array_keys($this->properties) as $filterProps) {
      $acceptedFilterProps = array_merge($acceptedFilterProps, array_map("trim", explode(",", (string) $filterProps)));
    }
    return array_unique($acceptedFilterProps);
  }

  private function buildMemberFilterClause(QueryBuilder $queryBuilder, string $field, string $rootAlias, QueryNameGeneratorInterface $queryNameGenerator, Season $currentSeason): void
  {
    $clauseField = $this->buildClauseField($rootAlias, $field, $queryBuilder, $queryNameGenerator);

    $queryBuilder->andWhere($queryBuilder->expr()->eq($clauseField, ':currentSeason'));
    $queryBuilder->setParameter(":currentSeason", $currentSeason);
  }

  private function buildDateFilterClause(QueryBuilder $queryBuilder, string $field, string $rootAlias, QueryNameGeneratorInterface $queryNameGenerator): void
  {
    $clauseField = $this->buildClauseField($rootAlias, $field, $queryBuilder, $queryNameGenerator);

//    $queryBuilder->andWhere($queryBuilder->expr()->eq($clauseField, ':currentSeason'));
//    $queryBuilder->setParameter(":currentSeason", $currentSeason);
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
        'description' => 'Force the query to be only for current season.',
      ];
    }
    return $description;
  }
}
