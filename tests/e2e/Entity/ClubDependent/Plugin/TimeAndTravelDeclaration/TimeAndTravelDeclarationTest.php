<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\ClubSetting;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Enum\TimeAndTravelExportStatus;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleFactory;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationFactory;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportFactory;
use App\Tests\Story\_InitStory;

class TimeAndTravelDeclarationTest extends AbstractEntityClubLinkedTestCase {
  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 0;
  #[\Override]
  protected int $TOTAL_BADGER_CLUB_1 = 10;

  protected function getClassname(): string {
    return TimeAndTravelDeclaration::class;
  }

  protected function getRootUrl(): string {
    return "/time-and-travel-declarations";
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    $access[ClubRole::supervisor->value] = false;
    // A badger/kiosk session can browse declarations for its club, same trust level as MemberPresence.
    $access[ClubRole::badger->value] = true;
    return $access;
  }

  public function initDefaultFixtures(): void {
    TimeAndTravelDeclarationFactory::createMany(10, ['member' => _InitStory::MEMBER_member_club_1()]);
  }

  public function testCreate(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      memberClub1Code: ResponseCodeEnum::created,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::created,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub1Code: ResponseCodeEnum::created,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $club1 = _InitStory::club_1();
        $member = _InitStory::MEMBER_member_club_1();
        $vehicle = MemberVehicleFactory::createOne(['member' => $member]);
        $payload = [
          "member" => $this->getIriFromResource($member),
          "date" => new \DateTimeImmutable()->format('Y-m-d'),
          "departureLocation" => "Home",
          "arrivalLocation" => "Club",
          "kilometers" => 20,
          "hours" => "2.00",
          "description" => "Training session $id",
          "isRoundtrip" => true,
          "memberVehicle" => $this->getIriFromResource($vehicle),
        ];
        $payloadCheck = ["kilometers" => 20];
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
      badgerClub1Code: ResponseCodeEnum::ok,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $declaration = TimeAndTravelDeclarationFactory::createOne(['member' => _InitStory::MEMBER_member_club_1()]);
        $payloadCheck = ["description" => "Updated $id"];
        $this->makePatchRequest($this->getIriFromResource($declaration), $payloadCheck);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::no_content,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      badgerClub1Code: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $declaration = TimeAndTravelDeclarationFactory::createOne(['member' => _InitStory::MEMBER_member_club_1()]);
        $this->makeDeleteRequest($this->getIriFromResource($declaration));
      },
    );
  }

  public function testMemberCannotTouchOtherMemberDeclaration(): void {
    $declaration = TimeAndTravelDeclarationFactory::createOne(['member' => _InitStory::MEMBER_admin_club_1()]);

    $this->loggedAsMemberClub1();
    $this->makePatchRequest($this->getIriFromResource($declaration), ['description' => 'Hacked']);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
  }

  public function testKilometersIsAlwaysTheTotalRegardlessOfRoundtrip(): void {
    // isRoundtrip is purely informational (display only) — kilometers is always
    // the total for the trip, never doubled.
    $declaration = TimeAndTravelDeclarationFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'kilometers' => 30,
      'isRoundtrip' => true,
    ]);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getIriFromResource($declaration));
    $this->assertResponseIsSuccessful();
    $this->assertEquals(30, $response->toArray()['kilometers']);
  }

  public function testTimeAmountUsesTheDefaultSmicRateWhenClubHasNoOverride(): void {
    $declaration = TimeAndTravelDeclarationFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'hours' => '2.00',
      'kilometers' => null,
      'departureLocation' => null,
      'arrivalLocation' => null,
      'memberVehicle' => null,
    ]);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getIriFromResource($declaration));
    $this->assertResponseIsSuccessful();
    $this->assertEqualsWithDelta(2 * (float) ClubSetting::DEFAULT_SMIC_HOURLY_RATE, $response->toArray()['timeAmount'], 0.001);
  }

  public function testKilometersAndHoursAreEachOptionalButAtLeastOneIsRequired(): void {
    $club1 = _InitStory::club_1();
    $member = _InitStory::MEMBER_member_club_1();

    $this->loggedAsAdminClub1();

    // Kilometers-only is valid (with the vehicle it requires)
    $vehicle = MemberVehicleFactory::createOne(['member' => $member]);
    $response = $this->makePostRequest($this->getRootWClubUrl($club1), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'departureLocation' => 'Home',
      'arrivalLocation' => 'Club',
      'kilometers' => 15,
      'memberVehicle' => $this->getIriFromResource($vehicle),
      'description' => 'Km only',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
    $this->assertNull($response->toArray()['hours'] ?? null);

    // Hours-only is valid, and needs no departure/arrival location at all
    $response = $this->makePostRequest($this->getRootWClubUrl($club1), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'hours' => '2.00',
      'description' => 'Hours only',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
    $this->assertNull($response->toArray()['kilometers'] ?? null);
    $this->assertNull($response->toArray()['departureLocation'] ?? null);

    // Neither is invalid
    $this->makePostRequest($this->getRootWClubUrl($club1), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'description' => 'Neither',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);
  }

  public function testDistanceFieldsAreRequiredOnlyWhenKilometersIsDeclared(): void {
    $club1 = _InitStory::club_1();
    $member = _InitStory::MEMBER_member_club_1();
    $vehicle = MemberVehicleFactory::createOne(['member' => $member]);

    $this->loggedAsAdminClub1();

    // Kilometers without a departure/arrival location is invalid
    $this->makePostRequest($this->getRootWClubUrl($club1), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'kilometers' => 15,
      'memberVehicle' => $this->getIriFromResource($vehicle),
      'description' => 'Missing locations',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);

    // Kilometers without a vehicle is invalid — the vehicle drives the travel amount calculation
    $this->makePostRequest($this->getRootWClubUrl($club1), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'departureLocation' => 'Home',
      'arrivalLocation' => 'Club',
      'kilometers' => 15,
      'description' => 'Missing vehicle',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);
  }

  public function testHoursMustBeAMultipleOfAHalfHour(): void {
    $club1 = _InitStory::club_1();
    $member = _InitStory::MEMBER_member_club_1();

    $this->loggedAsAdminClub1();

    // 1.3 is not a valid half-hour step (easy to mistake for "1h30", which is actually 1.5)
    $this->makePostRequest($this->getRootWClubUrl($club1), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'hours' => '1.30',
      'description' => 'Bad granularity',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);

    $this->makePostRequest($this->getRootWClubUrl($club1), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'hours' => '1.50',
      'description' => 'Good granularity',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
  }

  public function testLocationsAndDescriptionAreBoundedToKeepExportsReadable(): void {
    $club1 = _InitStory::club_1();
    $member = _InitStory::MEMBER_member_club_1();
    $vehicle = MemberVehicleFactory::createOne(['member' => $member]);

    $this->loggedAsAdminClub1();

    $this->makePostRequest($this->getRootWClubUrl($club1), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'departureLocation' => str_repeat('a', TimeAndTravelDeclaration::LOCATION_MAX_LENGTH + 1),
      'arrivalLocation' => str_repeat('a', TimeAndTravelDeclaration::LOCATION_MAX_LENGTH + 1),
      'kilometers' => 15,
      'memberVehicle' => $this->getIriFromResource($vehicle),
      'description' => str_repeat('a', TimeAndTravelDeclaration::DESCRIPTION_MAX_LENGTH + 1),
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);

    $response = $this->makePostRequest($this->getRootWClubUrl($club1), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'departureLocation' => str_repeat('a', TimeAndTravelDeclaration::LOCATION_MAX_LENGTH),
      'arrivalLocation' => str_repeat('a', TimeAndTravelDeclaration::LOCATION_MAX_LENGTH),
      'kilometers' => 15,
      'memberVehicle' => $this->getIriFromResource($vehicle),
      'description' => str_repeat('a', TimeAndTravelDeclaration::DESCRIPTION_MAX_LENGTH),
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
  }

  public function testLockedDeclarationIsReadOnlyEvenForAdmin(): void {
    $export = TimeAndTravelExportFactory::createOne([
      'club' => _InitStory::club_1(),
      'status' => TimeAndTravelExportStatus::locked,
      'lockedAt' => new \DateTimeImmutable(),
    ]);
    $declaration = TimeAndTravelDeclarationFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'export' => $export,
    ]);

    // Not even an admin can edit a locked declaration
    $this->loggedAsAdminClub1();
    $this->makePatchRequest($this->getIriFromResource($declaration), ['description' => 'Should fail']);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);

    $this->makeDeleteRequest($this->getIriFromResource($declaration));
    $this->assertResponseStatusCodeSame(409);

    // Not even the owning member
    $this->loggedAsMemberClub1();
    $response = $this->makePatchRequest($this->getIriFromResource($declaration), ['description' => 'Should fail too']);
    $this->assertContains($response->getStatusCode(), [403, 422]);
  }

  public function testSupervisorWithPermissionCanAccessDeclarations(): void {
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

  public function testSupervisorWithEditPermissionCanCreateDeclarationForAnyMember(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $supervisorIri = $this->getIriFromResource($supervisor);
    $member = _InitStory::MEMBER_member_club_1();
    $vehicle = MemberVehicleFactory::createOne(['member' => $member]);

    // Without TIME_TRAVEL_EDIT, a supervisor cannot create a declaration on behalf of another member
    $this->loggedAsSupervisorClub1();
    $this->makePostRequest($this->getRootWClubUrl($club), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'hours' => '1.00',
      'description' => 'Should be refused',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    $this->loggedAsAdminClub1();
    $this->makePostRequest($supervisorIri . '/permissions', [
      'member' => $supervisorIri,
      'permission' => Permission::TIME_TRAVEL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    $this->loggedAsSupervisorClub1();
    $this->makePostRequest($this->getRootWClubUrl($club), [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable()->format('Y-m-d'),
      'departureLocation' => 'Home',
      'arrivalLocation' => 'Club',
      'kilometers' => 10,
      'memberVehicle' => $this->getIriFromResource($vehicle),
      'description' => 'Declared by supervisor',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
  }

  public function testCsvExport(): void {
    $this->loggedAsAdminClub1();
    $this->makeGetCsvRequest($this->getRootWClubUrl(_InitStory::club_1()) . '.csv');
    $this->assertResponseIsSuccessful();
  }
}
