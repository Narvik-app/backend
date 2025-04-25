<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UserClubExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface {
  private QueryNameGeneratorInterface $queryNameGenerator;
  private QueryBuilder $queryBuilder;
  private string $rootAlias;

  public function __construct(
    private readonly Security $security,
  ) {
  }

  public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void {
    $this->queryBuilder = $queryBuilder;
    $this->queryNameGenerator = $queryNameGenerator;
    $this->addWhere($resourceClass);
  }

  public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void {
    $this->queryBuilder = $queryBuilder;
    $this->queryNameGenerator = $queryNameGenerator;
    $this->addWhere($resourceClass);
  }

  private function throwAccessDenied(): void {
    throw new HttpException(Response::HTTP_FORBIDDEN, "Access to this resource isn't allowed");
  }

  private function getUser(): User {
    $user = $this->security->getUser();
    if (!$user instanceof User) {
      $this->throwAccessDenied();
    }
    return $user;
  }

  private function getUserClubs(): array {
    $user = $this->getUser();
    $userClubs = [];
    foreach ($user->getLinkedProfiles() as $club) {
      $userClubs[] = $club->getClub();
    }
    return $userClubs;
  }

  /**
   * Generate all the joins from the query string (exploding dot into join)
   *
   * @param string $joinQuery
   *
   * @return string The last join alias generated, that can be used to refer to the targeted entity
   */
  private function addJoins(string $joinQuery): string {
    $joins = explode(".", $joinQuery);
    $parentJoin = $this->rootAlias; // The start of the join is the SQL root
    foreach ($joins as $join) {
      $joinAlias = $this->queryNameGenerator->generateJoinAlias($join);
      $this->queryBuilder->join("$parentJoin.$join", $joinAlias);
      $parentJoin = $joinAlias;
    }

    return $parentJoin;
  }

  private function addWhere(string $resourceClass): void {
    // User is anonymous, not logged, we don't add any more restriction
    if (!$this->security->getUser()) {
      return;
    }

    $user = $this->getUser();

    // Super admin no restriction
    if ($user->getRole() === UserRole::super_admin) {
      return;
    }

    $this->rootAlias = $this->queryBuilder->getRootAliases()[0];

    if ($resourceClass === Club::class) {
      $userClubs = $this->getUserClubs();
      $this->queryBuilder
        ->andWhere(
          $this->queryBuilder->expr()->in("{$this->rootAlias}", ":clubs"),
        )
        ->setParameter("clubs", $userClubs);
      return;
    }

    // Entity must be restrained based on the user's clubs
    if (is_subclass_of($resourceClass, ClubLinkedEntityInterface::class)) {
      $userClubs = $this->getUserClubs();

      $hasClubLinked = false;
      foreach ($this->queryBuilder->getDQLPart("join") as $dqlJoin) {
        /** @var Join $join */
        foreach ($dqlJoin as $join) {
          if (str_ends_with($join->getJoin(), "club")) {
            $hasClubLinked = true;
            break 2;
          }
        }
      }

      if (!$hasClubLinked) {
        $clubJoin = $this->addJoins($resourceClass::getClubSqlPath());
        $this->queryBuilder
          ->andWhere(
            $this->queryBuilder->expr()->in($clubJoin, ":clubs"),
          )
          ->setParameter("clubs", $userClubs);
      }
    }
  }
}
