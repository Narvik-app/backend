<?php

namespace App\Tests\e2e\Entity\ClubDependent;

use App\Entity\ClubDependent\MemberControlType;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\MemberControlTypeFactory;
use App\Tests\Story\_InitStory;

class MemberControlTypeTest extends AbstractEntityClubLinkedTestCase {
  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 3;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 3;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 3;

  protected function getClassname(): string {
    return MemberControlType::class;
  }

  protected function getRootUrl(): string {
    return "/member-control-types";
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    // Supervisors need MEMBER_CONTROL_TYPES_ACCESS permission to access the collection
    $access[ClubRole::supervisor->value] = false;
    return $access;
  }

  public function initDefaultFixtures(): void {
    MemberControlTypeFactory::createMany(3);
  }

  public function testCreate(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::created,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub1Code: ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $club1 = _InitStory::club_1();
        $payload = ["name" => "Type$id", "warningDays" => 335, "alertDays" => 365];
        $payloadCheck = $payload;
        $this->makePostRequest($this->getRootWClubUrl($club1), $payload);
      },
    );
  }

  public function testCreateWithInvalidDelays(): void {
    $this->loggedAsAdminClub1();
    $club1 = _InitStory::club_1();
    $this->makePostRequest($this->getRootWClubUrl($club1), [
      "name" => "Invalid",
      "warningDays" => 365,
      "alertDays" => 335,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);
  }

  public function testPatch(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $type = MemberControlTypeFactory::createOne();
        $payloadCheck = ["name" => "New name$id"];
        $this->makePatchRequest($this->getIriFromResource($type), $payloadCheck);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $type = MemberControlTypeFactory::createOne();
        $this->makeDeleteRequest($this->getIriFromResource($type));
      },
    );
  }

  public function testMove(): void {
    $this->loggedAsAdminClub1();
    $first = MemberControlTypeFactory::createOne();
    $second = MemberControlTypeFactory::createOne();

    $this->makePutRequest($this->getIriFromResource($second) . '/move', ['direction' => 'up']);
    $this->assertResponseIsSuccessful();
  }

  public function testSupervisorWithPermissionCanAccessTypes(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->loggedAsSupervisorClub1();
    $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    $this->loggedAsAdminClub1();
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => Permission::MEMBER_CONTROL_TYPES_ACCESS->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    $this->loggedAsSupervisorClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseIsSuccessful();
    $this->assertEquals($this->TOTAL_SUPERVISOR_CLUB_1, $response->toArray()['totalItems']);
  }
}
