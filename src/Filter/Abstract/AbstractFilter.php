<?php

namespace App\Filter\Abstract;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter as ApiPlatformAbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Service\UtilsService;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractFilter extends ApiPlatformAbstractFilter {
  public function buildClauseField(string $rootAlias, string $field, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $joinType = Join::LEFT_JOIN): string {
    $clauseField = "$rootAlias.$field"; // by default clauseField = provided field
    $joins = explode(".", $field);
    if (count($joins) > 1) {
      $linkedTo = $rootAlias;
      foreach ($joins as $k => $join) {
        if ($k === count($joins)-1) {
          $clauseField = "$linkedTo.$join";
          break;
        }
        $joinAlias = $queryNameGenerator->generateJoinAlias("ja_{$join}");
        if (!in_array($joinAlias, $queryBuilder->getAllAliases())) {
          if ($joinType === Join::LEFT_JOIN) {
            $queryBuilder->leftJoin(sprintf('%s.%s', $linkedTo, $join), $joinAlias);
          } else {
            $queryBuilder->innerJoin(sprintf('%s.%s', $linkedTo, $join), $joinAlias);
          }
        }
        $linkedTo = $joinAlias;
      }
    }

    return $clauseField;
  }

  protected function toBoolean($value): bool {
    return UtilsService::toBoolean($value);
  }

  #[\Override]
  public function getProperties(): ?array {
    return $this->properties;
  }
}
