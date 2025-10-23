<?php

namespace App\Tests\e2e\Entity\ClubDependent;

use App\Entity\ClubDependent\Metric;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\MemberPresenceFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\ActivityStory;

class MetricTest extends AbstractEntityClubLinkedTestCase {

  protected int $TOTAL_SUPER_ADMIN = 4;
  protected int $TOTAL_ADMIN_CLUB_1 = 4;
  protected int $TOTAL_ADMIN_CLUB_2 = 4;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 4;

  protected function getClassname(): string {
    return Metric::class;
  }

  protected function getRootUrl(): string {
    return "/metrics";
  }

  public function initDefaultFixtures(): void {
    ActivityStory::load();
  }

  public function testCreate(): void {
    $club1 = _InitStory::club_1();

    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) use ($club1) {
        $this->makePostRequest($this->getRootWClubUrl($club1));
      },
    );
  }

  public function testPatch(): void {
    $club1 = _InitStory::club_1();

    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) use ($club1) {
        $this->makePatchRequest($this->getRootWClubUrl($club1));
      },
    );
  }

  public function testDelete(): void {
    $club1 = _InitStory::club_1();

    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) use ($club1) {
        $this->makeDeleteRequest($this->getRootWClubUrl($club1));
      },
    );
  }

  public function testSuperAdminGetAllStatsMerged(): void {
    MemberPresenceFactory::new([
      'member' => _InitStory::MEMBER_member_club_1(),
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->many(5)->create();

    $iri = $this->getRootUrl();
    $iriItem = $iri . "/members";

    $this->loggedAsAdminClub1();
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
    $this->makeGetRequest($iriItem);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    $this->loggedAsSuperAdmin();
    $this->makeGetRequest($iri);
    $this->assertResponseIsSuccessful();
    $this->makeGetRequest($iriItem);
    $this->assertResponseIsSuccessful();
  }

  public function testFilteringWithAMalformedDate(): void {
    $iri = $this->getRootWClubUrl(_InitStory::club_1()) . "/opened-days?end=2025-14-14";

    $this->loggedAsAdminClub1();
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Invalid date filter.",
    ]);
  }

  public function testFilteringWithDateReversed(): void {
    $this->loggedAsAdminClub1();

    $iri = $this->getRootWClubUrl(_InitStory::club_1()) . "/opened-days?end=2025-12-31&start=2026-11-11";
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
  }

  public function testFilteringWithDate(): void {
    $this->loggedAsAdminClub1();

    $iri = $this->getRootWClubUrl(_InitStory::club_1()) . "/opened-days?end=2025-12-31&start=2024-11-11";
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
  }

  public function testFilteringOnSeason(): void {
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable,
      'member' => _InitStory::MEMBER_member_club_1(),
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->many(5)->create();

    $this->loggedAsAdminClub1();

    $iri = $this->getRootWClubUrl(_InitStory::club_1()) . "/presences?current-season=true";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $this->assertEquals(5, $response->toArray()['value']);

    $iri = $this->getRootWClubUrl(_InitStory::club_1()) . "/presences?previous-season=true";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $this->assertEquals(0, $response->toArray()['value']);
  }

}
