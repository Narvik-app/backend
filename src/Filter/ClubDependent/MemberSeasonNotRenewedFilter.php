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
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class MemberSeasonNotRenewedFilter extends AbstractClubDependentFilter {
  public const string PROPERTY_NAME = "season-not-renewed";

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

      $passedFilterProps = array_map(mb_trim(...), explode(",", (string) $fields));
      if ($this->properties !== null) {
        // restrict http-passed properties to accepted filter properties only
        $passedFilterProps = array_intersect($passedFilterProps, $acceptedFilterProps);
      }
      // if no filter property matches supported resource properties
      // do not take into account the current multiple filter
      if (empty($passedFilterProps) || count($passedFilterProps) !== 1) return;

      $rootAlias = $queryBuilder->getRootAliases()[0];
      $this->buildFilterClause($queryBuilder, $passedFilterProps[0], $rootAlias, $queryNameGenerator, $currentSeason);}
  }

  private function getAcceptedFilterProps(): array {
    $acceptedFilterProps = [];
    foreach (array_keys($this->properties) as $filterProps) {
      $acceptedFilterProps = array_merge($acceptedFilterProps, array_map(mb_trim(...), explode(",", (string) $filterProps)));
    }
    return array_unique($acceptedFilterProps);
  }

  private function buildFilterClause(QueryBuilder $queryBuilder, string $field, string $rootAlias, QueryNameGeneratorInterface $queryNameGenerator, Season $currentSeason): void {
    $subQuery = new QueryBuilder($queryBuilder->getEntityManager());
    $subQuery
      ->select("m.id")
      ->from(Member::class, 'm');

    $clauseField = $this->buildClauseField($rootAlias, $field, $subQuery, $queryNameGenerator);

    $subQuery->andWhere($subQuery->expr()->eq($clauseField, ':currentSeason'));
    $queryBuilder->setParameter("currentSeason", $currentSeason);

    $this->addSelfClubJoin($subQuery, $queryNameGenerator, $queryBuilder);

    $queryBuilder->andWhere($queryBuilder->expr()->notIn("$rootAlias.id", $subQuery->getDQL()));
  }

  public function getDescription(string $resourceClass): array {
    if (!$this->properties) {
      return [];
    }

    $description = [];
    foreach (array_keys($this->properties) as $property) {
      $description[self::PROPERTY_NAME . '[' . $property . ']'] = [
        'property' => $property,
        'type' => 'bool',
        'required' => false,
        'description' => 'Display the members that don\'t have the current season linked.',
      ];
    }
    return $description;
  }
}
