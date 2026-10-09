<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationFactory;
use App\Tests\Story\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationStory;
use App\Tests\Story\Plugin\TimeAndTravelDeclaration\MemberVehicleStory;
use App\Tests\Story\_InitStory;
use function Zenstruck\Foundry\faker;

class TimeAndTravelDeclarationTest extends AbstractEntityClubLinkedTestCase {
  protected int $TOTAL_SUPER_ADMIN = 3;
  protected int $TOTAL_ADMIN_CLUB_1 = 3;
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 3;

  protected function getClassname(): string {
    return TimeAndTravelDeclaration::class;
  }

  protected function getRootUrl(): string {
    return "/time-and-travel-declarations";
  }

  public function initDefaultFixtures(): void {
    TimeAndTravelDeclarationStory::load();
  }

  public function testCreate(): void {
    $club1 = _InitStory::club_1();
    $member = _InitStory::MEMBER_member_club_1();
    $memberVehicle = MemberVehicleStory::basic();

    $memberIri = $this->getIriFromResource($member);
    $memberVehicleIri = $this->getIriFromResource($memberVehicle);

    $payload = [
      "member" => $memberIri,
      "date" => (new \DateTimeImmutable())->format('Y-m-d'),
      "departureLocation" => "Paris",
      "arrivalLocation" => "Lyon",
      "kilometers" => 500,
      "hours" => "5.50",
      "description" => "Test declaration",
      "memberVehicle" => $memberVehicleIri,
    ];

    $payloadCheck = [
      "member" => [
        '@id' => $memberIri,
      ],
      "date" => $payload["date"],
      "departureLocation" => "Paris",
      "arrivalLocation" => "Lyon",
      "kilometers" => 500,
      "hours" => "5.50",
      "description" => "Test declaration",
      "isRoundtrip" => true,
      "memberVehicle" => [
        '@id' => $memberVehicleIri,
      ]
    ];

    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::created,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub1Code: ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use ($club1, $payload) {
        $this->makePostRequest($this->getRootWClubUrl($club1), $payload);
      },
    );
  }

  public function testPatch(): void {
    // Update a time and travel declaration created today
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::ok,
      adminClub1Code: ResponseCodeEnum::ok,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::ok,
      requestFunction: function (string $level, ?int $id) {
        $item = TimeAndTravelDeclarationFactory::createOne(['createdAt' => new \DateTimeImmutable()]);
        $this->makePatchRequest($this->getIriFromResource($item), ['description' => 'Updated description']);
      },
    );

    // Update a time and travel declaration created days ago
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::ok,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::ok,
      requestFunction: function (string $level, ?int $id) {
        $item = TimeAndTravelDeclarationFactory::createOne([
          'createdAt' => \DateTimeImmutable::createFromMutable(faker()->dateTimeBetween('-10 days', '-2 days'))
        ]);
        $this->makePatchRequest($this->getIriFromResource($item), ['description' => 'Updated old declaration']);
      },
    );
  }

  public function testDelete(): void {
    // Delete a time and travel declaration created today
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::no_content,
      adminClub1Code: ResponseCodeEnum::no_content,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $item = TimeAndTravelDeclarationFactory::createOne(['createdAt' => new \DateTimeImmutable()]);
        $this->makeDeleteRequest($this->getIriFromResource($item));
      },
    );

    // Delete a time and travel declaration created days ago
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $item = TimeAndTravelDeclarationFactory::createOne([
          'createdAt' => \DateTimeImmutable::createFromMutable(faker()->dateTimeBetween('-10 days', '-2 days'))
        ]);
        $this->makeDeleteRequest($this->getIriFromResource($item));
      },
    );
  }
}
