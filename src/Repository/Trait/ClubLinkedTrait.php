<?php

namespace App\Repository\Trait;

use App\Entity\Club;
use App\Entity\Interface\UuidEntityInterface;
use Doctrine\ORM\QueryBuilder;

trait ClubLinkedTrait {
  /**
   * Generate all the joins from the query string (exploding dot into join)
   *
   * @param string $joinQuery
   *
   * @return string The last join alias generated, that can be used to refer to the targeted entity
   */
  private function addJoins(QueryBuilder $qb, string $joinQuery): string {
    $rootAlias = $qb->getRootAliases()[0];
    $joins = explode(".", $joinQuery);
    $parentJoin = $rootAlias; // The start of the join is the SQL root
    foreach ($joins as $join) {
      $joinAlias = "ja_" . $join;
      $qb->join("$parentJoin.$join", $joinAlias);
      $parentJoin = $joinAlias;
    }

    return $parentJoin;
  }

  public function applyClubRestriction(QueryBuilder $qb, Club $club): QueryBuilder {
    $alias = $qb->getRootAliases()[0];

    $clubSqlPath = $this->getClassName()::getClubSqlPath();
    if (str_contains((string) $clubSqlPath, ".")) {
      $clubSqlPath = $this->addJoins($qb, $clubSqlPath);
    } else {
      $clubSqlPath = "$alias.$clubSqlPath";
    }

    return $qb
      ->andWhere($qb->expr()->eq($clubSqlPath, ':club'))
      ->setParameter('club', $club);
  }

  public function findAllByClub(Club $club): array {
    $qb = $this->createQueryBuilder('e');
    $this->applyClubRestriction($qb, $club);
    $query = $qb->getQuery();
    return $query->getResult();
  }

  public function findOneByClubAndUuid(Club $club, string $uuid): ?UuidEntityInterface {
    $qb = $this->createQueryBuilder('e');
    $this->applyClubRestriction($qb, $club);
    $query = $qb
      ->andWhere('e.uuid = :uuid')
      ->setParameter('uuid', $uuid)
      ->setMaxResults(1)
      ->getQuery();

    try {
      return $query->getOneOrNullResult();
    }
    catch (\Exception) {
      return null;
    }
  }

  /**
   * Append a club_id raw-SQL condition (and its bound parameter) to a WHERE-clause
   * accumulator, for repositories building raw SQL instead of a DQL QueryBuilder
   * (e.g. stats queries using features DQL can't express, like FILTER or EXTRACT).
   * No-op when no club is given, so callers can use it unconditionally.
   *
   * @param array<int, string> $whereClauses
   * @param array<string, mixed> $params
   */
  public function addClubRawSqlRestriction(?Club $club, array &$whereClauses, array &$params, string $columnPrefix = ''): void {
    if (!$club) {
      return;
    }

    $whereClauses[] = "{$columnPrefix}club_id = :clubId";
    $params['clubId'] = $club->getId();
  }
}
