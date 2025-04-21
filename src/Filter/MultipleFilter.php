<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\DQL\CustomExpr;
use App\Filter\Abstract\AbstractFilter;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyInfo\Type;

final class MultipleFilter extends AbstractFilter {
  public const PROPERTY_NAME = "multiple";

  protected function filterProperty(string $property, $values, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void {
    if ($property !== static::PROPERTY_NAME) return;
    if (!is_array($values)) return;
    if ($this->properties === null) {
      return;
    }

    $acceptedFilterProps = $this->getAcceptedFilterProps();
    $iParam=0;
    foreach ($values as $fields => $value) {
      $passedFilterProps = array_map("trim", explode(",", $fields));
      if ($this->properties !== null) {
        // restrict http-passed properties to accepted filter properties only
        $passedFilterProps = array_intersect($passedFilterProps, $acceptedFilterProps);
      }
      // if no filter property matches supported resource properties
      // do not take into account the current multiple filter
      if (empty($passedFilterProps)) continue;

      $orClauses = [];
      $rootAlias = $queryBuilder->getRootAliases()[0];
      foreach ($passedFilterProps as $field) {
        $newClause = $this->buildFilterClause($queryBuilder, $field, $iParam, $rootAlias, $queryNameGenerator);
        if ($newClause) $orClauses[] = $newClause;
      }
      if (!empty($orClauses)) {
        $this->applyFilter($queryBuilder, $orClauses, $value, $iParam);
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

  public function applyFilter(QueryBuilder $queryBuilder, array $orClauses, $value, int $iParam): void {
    $queryBuilder->andWhere($queryBuilder->expr()->orX()->addMultiple($orClauses))->setParameter("value".$iParam++, "%$value%");
  }

  private function buildFilterClause(QueryBuilder $queryBuilder, string $field, int $iParam, string $rootAlias, QueryNameGeneratorInterface $queryNameGenerator) {
    $clauseField = $this->buildClauseField($rootAlias, $field, $queryBuilder, $queryNameGenerator);
    return $queryBuilder->expr()->like(CustomExpr::unaccentInsensitive($clauseField), CustomExpr::unaccentInsensitive(":value$iParam"));
  }

  public function getDescription(string $resourceClass): array {
    if (!$this->properties) {
      return [];
    }

    $description = [];
    foreach ($this->properties as $property => $value) {
      $description[self::PROPERTY_NAME . '[' . $property . ']'] = [
        'property' => $property,
        'type' => Type::BUILTIN_TYPE_STRING,
        'required' => false,
        'description' => 'Filtering with a ' . self::PROPERTY_NAME . ' condition for property ' . $property . '. Multiple field can be passed, it will be an OR condition between each one',
      ];
    }
    return $description;
  }
}
