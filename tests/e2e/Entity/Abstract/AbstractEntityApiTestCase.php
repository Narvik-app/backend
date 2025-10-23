<?php

namespace App\Tests\e2e\Entity\Abstract;

use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\Tests\e2e\AbstractApiTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * For a better comprehension on how to create the entities test
 * @see https://api-platform.com/docs/v3.0/distribution/testing/
 */
abstract class AbstractEntityApiTestCase extends AbstractApiTestCase {
  protected int $TOTAL_SUPER_ADMIN = 0;
  protected int $TOTAL_ADMIN_CLUB_1 = 0;
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 0;
  protected int $TOTAL_MEMBER_CLUB_1 = 0;
  protected int $TOTAL_BADGER_CLUB_1 = 0;
  protected int $TOTAL_BADGER_CLUB_2 = 0;

  abstract protected function getClassname() : string;
  abstract protected function getRootUrl() : string;

  abstract public function testCreate(): void;
  abstract public function testPatch(): void;
  abstract public function testDelete(): void;

  /**
   * Mapping of the collection response code
   *
   * @return array{string, bool}
   */
  protected function getCollectionGrantedAccess() : array {
    return [
      UserRole::super_admin->value => true,

      ClubRole::admin->value => false,
      ClubRole::supervisor->value => false,

      ClubRole::member->value => false,
      ClubRole::badger->value => false,
    ];
  }

  protected function testGetCollectionAs(UserRole|ClubRole $role, int $total): ResponseInterface {
    $response = $this->makeGetRequest($this->getRootUrl());
    return $this->validateGetCollectionResponse($response, $role, $total);
  }

  protected function validateGetCollectionResponse(ResponseInterface $response, UserRole|ClubRole $role, int $total): ResponseInterface {
    if (!$this->getCollectionGrantedAccess()[$role->value] || $total < 0) {
      self::assertResponseIsClientError();
      return $response;
    }

    self::assertResponseIsSuccessful();
    self::assertMatchesResourceCollectionJsonSchema($this->getClassname());
    $this->assertEquals($total, $response->toArray()['totalItems']);
    return $response;
  }

  public function testGetCollectionAsSuperAdmin(): ResponseInterface {
    $this->loggedAsSuperAdmin();
    return $this->testGetCollectionAs(UserRole::super_admin, $this->TOTAL_SUPER_ADMIN);
  }

  public function testGetCollectionAsAdminClub1(): ResponseInterface {
    $this->loggedAsAdminClub1();
    return $this->testGetCollectionAs(ClubRole::admin, $this->TOTAL_ADMIN_CLUB_1);
  }

  public function testGetCollectionAsAdminClub2(): ResponseInterface {
    $this->loggedAsAdminClub2();
    return $this->testGetCollectionAs(ClubRole::admin, $this->TOTAL_ADMIN_CLUB_2);
  }

  public function testGetCollectionAsSupervisorClub1(): ResponseInterface {
    $this->loggedAsSupervisorClub1();
    return $this->testGetCollectionAs(ClubRole::supervisor, $this->TOTAL_SUPERVISOR_CLUB_1);
  }

  public function testGetCollectionAsMemberClub1(): ResponseInterface {
    $this->loggedAsMemberClub1();
    return $this->testGetCollectionAs(ClubRole::member, $this->TOTAL_MEMBER_CLUB_1);
  }

  public function testGetCollectionAsBadgerClub1(): ResponseInterface {
    $this->loggedAsBadgerClub1();
    return $this->testGetCollectionAs(ClubRole::badger, $this->TOTAL_BADGER_CLUB_1);
  }

  public function testGetCollectionAsBadgerClub2(): ResponseInterface {
    $this->loggedAsBadgerClub2();
    return $this->testGetCollectionAs(ClubRole::badger, $this->TOTAL_BADGER_CLUB_2);
  }
}
