<?php

namespace App\Tests\e2e\Entity\ClubDependent;

use App\Entity\ClubDependent\Metric;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\MemberPresenceFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\ActivityStory;

class MetricTest extends AbstractEntityClubLinkedTestCase {

  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 5;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 5;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 5;
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 5;

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

  public function testMemberPresenceStats(): void {
    $member1 = _InitStory::MEMBER_member_club_1();
    $member2 = _InitStory::MEMBER_admin_club_1();
    $club1 = _InitStory::club_1();

    // Create presences for member1 (3 presences)
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('-5 days'),
      'member' => $member1,
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->create();
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('-3 days'),
      'member' => $member1,
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->create();
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('-1 day'),
      'member' => $member1,
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->create();

    // Create presences for member2 (2 presences)
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('-7 days'),
      'member' => $member2,
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->create();
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('-2 days'),
      'member' => $member2,
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->create();

    $this->loggedAsSupervisorClub1();

    // Test access to the new member-presence-stats endpoint
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);

    $data = $response->toArray();

    // Should have values array with member statistics
    $this->assertArrayHasKey('values', $data);
    $this->assertIsArray($data['values']);

    // Should have pagination field with metadata
    $this->assertArrayHasKey('pagination', $data);
    $this->assertIsArray($data['pagination']);

    $pagination = $data['pagination'];
    $this->assertArrayHasKey('currentPage', $pagination);
    $this->assertArrayHasKey('itemsPerPage', $pagination);
    $this->assertArrayHasKey('totalItems', $pagination);
    $this->assertArrayHasKey('totalPages', $pagination);
    $this->assertArrayHasKey('order', $pagination);
    $this->assertEquals(1, $pagination['currentPage']);
    $this->assertEquals('ASC', $pagination['order']);

    // Check that stats are ordered by presence count (ASC)
    $items = $data['values'];
    if (count($items) >= 2) {
      $firstMemberCount = $items[0]['presenceCount'];
      $secondMemberCount = $items[1]['presenceCount'];
      $this->assertLessThanOrEqual($secondMemberCount, $firstMemberCount);
    }

    // Verify structure of each stat entry
    foreach ($items as $stat) {
      $this->assertArrayHasKey('memberUuid', $stat);
      $this->assertArrayHasKey('presenceCount', $stat);
      $this->assertArrayHasKey('lastPresenceDate', $stat);
      $this->assertArrayHasKey('firstname', $stat);
      $this->assertArrayHasKey('lastname', $stat);
    }
  }

  public function testMemberPresenceStatsWithDateFilter(): void {
    $member1 = _InitStory::MEMBER_member_club_1();
    $club1 = _InitStory::club_1();

    // Create presences within date range
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('2024-06-15'),
      'member' => $member1,
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->create();

    // Create presence outside date range
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('2024-12-31'),
      'member' => $member1,
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->create();

    $this->loggedAsSupervisorClub1();

    // Test with date range filter
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?start=2024-06-01&end=2024-06-30";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);

    $data = $response->toArray();
    $this->assertArrayHasKey('values', $data);

    // Find the member in the results
    $foundMember = false;
    foreach ($data['values'] as $stat) {
      if ($stat['memberUuid'] == $member1->getUuid()) {
        $foundMember = true;
        // Should have exactly 1 presence in the filtered range
        $this->assertEquals(1, $stat['presenceCount']);
        break;
      }
    }
    $this->assertTrue($foundMember, 'Member should be found in stats');
  }

  public function testMemberPresenceStatsWithOrderAndPagination(): void {
    $member1 = _InitStory::MEMBER_member_club_1();
    $member2 = _InitStory::MEMBER_admin_club_1();
    $member3 = _InitStory::MEMBER_supervisor_club_1();
    $club1 = _InitStory::club_1();

    // Create presences for member1 (5 presences - most)
    MemberPresenceFactory::new([
      'member' => $member1,
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->many(5)->create();

    // Create presences for member2 (3 presences - middle)
    MemberPresenceFactory::new([
      'member' => $member2,
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->many(3)->create();

    // Create presences for member3 (1 presence - least)
    MemberPresenceFactory::new([
      'member' => $member3,
      'activities' => [ActivityStory::getRandom('activities_club1')],
    ])->create();

    $this->loggedAsSupervisorClub1();

    // Test DESC ordering (default - most present first)
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?order=DESC";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $data = $response->toArray();
    $items = $data['values'];

    // First member should have most presences
    $this->assertGreaterThanOrEqual(5, $items[0]['presenceCount']);

    // Test DESC ordering (least present first)
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?order=DESC";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $data = $response->toArray();
    $items = $data['values'];

    // First member should have least presences
    if (count($items) >= 2) {
      $this->assertGreaterThan($items[1]['presenceCount'], $items[0]['presenceCount']);
    }

    // Test pagination
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?page=1&itemsPerPage=2";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $data = $response->toArray();

    $pagination = $data['pagination'];
    $this->assertEquals(1, $pagination['currentPage']);
    $this->assertEquals(2, $pagination['itemsPerPage']);
    $this->assertLessThanOrEqual(2, count($data['values']));
  }

  public function testMemberPresenceStatsWithInvalidParameters(): void {
    $club1 = _InitStory::club_1();
    $this->loggedAsSupervisorClub1();

    // Test invalid order parameter
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?order=INVALID";
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);

    // Test invalid page parameter (negative)
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?page=-1";
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);

    // Test invalid itemsPerPage parameter (too large)
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?itemsPerPage=200";
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);

    // Test invalid page parameter (non-numeric)
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?page=abc";
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
  }

}
