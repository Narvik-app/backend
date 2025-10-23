<?php

namespace App\Tests\e2e\Entity\Abstract;

use App\Entity\Club;
use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\Tests\Story\_InitStory;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class AbstractEntityClubLinkedTestCase extends AbstractEntityApiTestCase {

  /**
   * Mapping of the collection response code
   *
   * @return array{string, bool}
   */
  protected function getCollectionGrantedAccess() : array {
    $access = parent::getCollectionGrantedAccess();
    $access[ClubRole::admin->value] = true;
    $access[ClubRole::supervisor->value] = true;
    return $access;
  }

  protected function getRootWClubUrl(Club $club) : string {
    return $this->getIriFromResource($club) . $this->getRootUrl();
  }

  protected function testGetCollectionWClubAs(UserRole|ClubRole $role, Club $club, int $total): ResponseInterface {
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    return $this->validateGetCollectionResponse($response, $role, $total);
  }

  public function testGetCollectionAsSuperAdmin(): ResponseInterface {
    $this->loggedAsSuperAdmin();
    // We test with super admin only on club 1
    return $this->testGetCollectionWClubAs(UserRole::super_admin, _InitStory::club_1(), $this->TOTAL_SUPER_ADMIN);
  }

  public function testGetCollectionAsAdminClub1(): ResponseInterface {
    $this->loggedAsAdminClub1();

    // We don't get any from club 2
    $this->makeGetRequest($this->getRootWClubUrl(_InitStory::club_2()));
    $this->assertResponseIsClientError();

    return $this->testGetCollectionWClubAs(ClubRole::admin, _InitStory::club_1(), $this->TOTAL_ADMIN_CLUB_1);
  }

  public function testGetCollectionAsAdminClub2(): ResponseInterface {
    $this->loggedAsAdminClub2();

    // We don't get any from club 1
    $this->makeGetRequest($this->getRootWClubUrl(_InitStory::club_1()));
    $this->assertResponseIsClientError();

    return $this->testGetCollectionWClubAs(ClubRole::admin, _InitStory::club_2(), $this->TOTAL_ADMIN_CLUB_2);
  }

  public function testGetCollectionAsSupervisorClub1(): ResponseInterface {
    $this->loggedAsSupervisorClub1();

    // We don't get any from club 2
    $this->makeGetRequest($this->getRootWClubUrl(_InitStory::club_2()));
    $this->assertResponseIsClientError();

    return $this->testGetCollectionWClubAs(ClubRole::supervisor, _InitStory::club_1(),$this->TOTAL_SUPERVISOR_CLUB_1);
  }

  public function testGetCollectionAsMemberClub1(): ResponseInterface {
    $this->loggedAsMemberClub1();

    // We don't get any from club 2
    $this->makeGetRequest($this->getRootWClubUrl(_InitStory::club_2()));
    $this->assertResponseIsClientError();

    return $this->testGetCollectionWClubAs(ClubRole::member, _InitStory::club_1(), $this->TOTAL_MEMBER_CLUB_1);
  }

  public function testGetCollectionAsBadgerClub1(): ResponseInterface {
    $this->loggedAsBadgerClub1();

    // We don't get any from club 2
    $this->makeGetRequest($this->getRootWClubUrl(_InitStory::club_2()));
    $this->assertResponseIsClientError();

    return $this->testGetCollectionWClubAs(ClubRole::badger, _InitStory::club_1(), $this->TOTAL_BADGER_CLUB_1);
  }

  public function testGetCollectionAsBadgerClub2(): ResponseInterface {
    $this->loggedAsBadgerClub2();

    // We don't get any from club 1
    $this->makeGetRequest($this->getRootWClubUrl(_InitStory::club_1()));
    $this->assertResponseIsClientError();

    return $this->testGetCollectionWClubAs(ClubRole::badger, _InitStory::club_2(), $this->TOTAL_BADGER_CLUB_2);
  }
}
