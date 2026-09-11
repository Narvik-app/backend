<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleFactory;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationFactory;
use App\Tests\Story\_InitStory;

class MemberVehicleTest extends AbstractEntityClubLinkedTestCase {
  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 0;

  protected function getClassname(): string {
    return MemberVehicle::class;
  }

  protected function getRootUrl(): string {
    return "/member-vehicles";
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    // Supervisors need TIME_TRAVEL_ACCESS to browse every member's vehicle
    $access[ClubRole::supervisor->value] = false;
    return $access;
  }

  public function initDefaultFixtures(): void {
    MemberVehicleFactory::createMany(10, ['member' => _InitStory::MEMBER_member_club_1()]);
  }

  public function testCreate(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      // A member can create their own vehicle
      memberClub1Code: ResponseCodeEnum::created,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::created,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub1Code: ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $club1 = _InitStory::club_1();
        $member = _InitStory::MEMBER_member_club_1();
        $payload = [
          "member" => $this->getIriFromResource($member),
          "brand" => "Renault",
          "model" => "Clio",
          "licensePlate" => "AB-" . substr(bin2hex(random_bytes(3)), 0, 6),
          "engineType" => "petrol",
          "fiscalPower" => 5,
          "fiscalCoefficient" => "0.5680",
        ];
        $payloadCheck = ["brand" => "Renault"];
        $this->makePostRequest($this->getRootWClubUrl($club1), $payload);
      },
    );
  }

  public function testPatch(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      memberClub1Code: ResponseCodeEnum::ok,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $vehicle = MemberVehicleFactory::createOne(['member' => _InitStory::MEMBER_member_club_1()]);
        $payloadCheck = ["model" => "New model$id"];
        $this->makePatchRequest($this->getIriFromResource($vehicle), $payloadCheck);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::no_content,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $vehicle = MemberVehicleFactory::createOne(['member' => _InitStory::MEMBER_member_club_1()]);
        $this->makeDeleteRequest($this->getIriFromResource($vehicle));
      },
    );
  }

  public function testMemberCannotManageOtherMemberVehicle(): void {
    $vehicle = MemberVehicleFactory::createOne(['member' => _InitStory::MEMBER_admin_club_1()]);

    $this->loggedAsMemberClub1();
    $this->makePatchRequest($this->getIriFromResource($vehicle), ['model' => 'Hacked']);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
  }

  public function testSupervisorWithPermissionCanAccessVehicles(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->loggedAsSupervisorClub1();
    $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    $this->loggedAsAdminClub1();
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => Permission::TIME_TRAVEL_ACCESS->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    $this->loggedAsSupervisorClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseIsSuccessful();
    $this->assertEquals($this->TOTAL_ADMIN_CLUB_1, $response->toArray()['totalItems']);
  }

  public function testCannotDeleteVehicleWithDeclarations(): void {
    $vehicle = MemberVehicleFactory::createOne(['member' => _InitStory::MEMBER_member_club_1()]);
    TimeAndTravelDeclarationFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'memberVehicle' => $vehicle,
    ]);

    $this->loggedAsAdminClub1();
    $this->makeDeleteRequest($this->getIriFromResource($vehicle));
    $this->assertResponseStatusCodeSame(409); // HTTP_CONFLICT, not part of ResponseCodeEnum yet
  }
}
