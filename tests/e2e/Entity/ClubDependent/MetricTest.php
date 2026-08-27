<?php

namespace App\Tests\e2e\Entity\ClubDependent;

use App\Entity\ClubDependent\Metric;
use App\Enum\Permission;
use App\Enum\SalePaymentModeKind;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\MemberPresenceFactory;
use App\Tests\Factory\SaleFactory;
use App\Tests\Factory\SalePaymentModeFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\ActivityStory;

class MetricTest extends AbstractEntityClubLinkedTestCase {

  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 8;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 8;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 8;
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 6;

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
    $this->assertIsArray($pagination['order']);
    // Default ordering is presenceCount DESC
    $this->assertEquals(['presenceCount' => 'DESC'], $pagination['order']);

    // Check that stats are ordered by presence count DESC (default)
    $items = $data['values'];
    if (count($items) >= 2) {
      $firstMemberCount = $items[0]['presenceCount'];
      $secondMemberCount = $items[1]['presenceCount'];
      $this->assertGreaterThanOrEqual($secondMemberCount, $firstMemberCount);
    }

    // Verify structure of each stat entry
    foreach ($items as $stat) {
      $this->assertArrayHasKey('memberUuid', $stat);
      $this->assertArrayHasKey('presenceCount', $stat);
      $this->assertArrayHasKey('lastPresenceDate', $stat);
      $this->assertArrayHasKey('firstname', $stat);
      $this->assertArrayHasKey('lastname', $stat);
      $this->assertArrayHasKey('medicalCertificateExpiration', $stat);
      // lastControlActivity is only present when a control activity is configured
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

    // Test DESC ordering (most present first) via order[presenceCount]=DESC
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?order[presenceCount]=DESC";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $data = $response->toArray();
    $items = $data['values'];

    // First member should have most presences
    $this->assertGreaterThanOrEqual(5, $items[0]['presenceCount']);
    $this->assertEquals(['presenceCount' => 'DESC'], $data['pagination']['order']);

    // Test ASC ordering (least present first) via order[presenceCount]=ASC
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?order[presenceCount]=ASC";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $data = $response->toArray();
    $items = $data['values'];

    // First member should have least presences
    if (count($items) >= 2) {
      $this->assertLessThanOrEqual($items[1]['presenceCount'], $items[0]['presenceCount']);
    }

    // Test ordering by medicalCertificateExpiration
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?order[medicalCertificateExpiration]=ASC";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $this->assertEquals(['medicalCertificateExpiration' => 'ASC'], $response->toArray()['pagination']['order']);

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

  public function testMemberPresenceStatsWithMemberControlType(): void {
    $member1 = _InitStory::MEMBER_member_club_1();
    $member2 = _InitStory::MEMBER_admin_club_1();
    $member3 = _InitStory::MEMBER_supervisor_club_1();
    $club1 = _InitStory::club_1();
    $controlActivity = ActivityStory::getRandom('activities_club1');

    $activityIri = $this->getIriFromResource($controlActivity);

    $this->loggedAsAdminClub1();
    $response = $this->makePostRequest($this->getIriFromResource($club1) . '/member-control-types', [
      'name' => 'Contrôle',
      'activity' => $activityIri,
      'warningDays' => 335,
      'alertDays' => 365,
    ]);
    $this->assertResponseIsSuccessful();
    $typeData = $response->toArray();
    $typeUuid = $typeData['uuid'];
    $typeIri = $typeData['@id'];
    $controlAlias = 'control_' . str_replace('-', '_', $typeUuid);

    // member1: 2 control activity presences, latest = most recent
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('-1 month'),
      'member' => $member1,
      'activities' => [$controlActivity],
    ])->create();
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('-3 months'),
      'member' => $member1,
      'activities' => [$controlActivity],
    ])->create();

    // member2: 1 control activity presence, older than member1
    MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('-6 months'),
      'member' => $member2,
      'activities' => [$controlActivity],
    ])->create();

    // member3: no control activity presence

    $this->loggedAsSupervisorClub1();

    // The control_<uuid> column is now included in the response
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $data = $response->toArray();
    foreach ($data['values'] as $stat) {
      $this->assertArrayHasKey($controlAlias, $stat);
    }

    // Sort ASC: member with oldest control date first (member2), then member1, then member3 (null last)
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?order[{$controlAlias}]=ASC";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $data = $response->toArray();
    $this->assertEquals([$controlAlias => 'ASC'], $data['pagination']['order']);

    // Sort DESC: member with most recent control date first (member1)
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?order[{$controlAlias}]=DESC";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $data = $response->toArray();
    $this->assertEquals([$controlAlias => 'DESC'], $data['pagination']['order']);
    $items = $data['values'];
    $firstWithControl = null;
    foreach ($items as $item) {
      if ($item[$controlAlias] !== null) {
        $firstWithControl = $item;
        break;
      }
    }
    $this->assertNotNull($firstWithControl, 'Expected at least one member with a control date');
    $this->assertEquals($member1->getUuid(), $firstWithControl['memberUuid']);

    // Remove the type — the control_<uuid> column should no longer appear
    $this->loggedAsAdminClub1();
    $this->makeDeleteRequest($typeIri);

    $this->loggedAsSupervisorClub1();
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats";
    $data = $this->makeGetRequest($iri)->toArray();
    foreach ($data['values'] as $stat) {
      $this->assertArrayNotHasKey($controlAlias, $stat);
    }
  }

  public function testMemberPresenceStatsWithInvalidParameters(): void {
    $club1 = _InitStory::club_1();
    $this->loggedAsSupervisorClub1();

    // Invalid order field is silently ignored, falls back to default ordering
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?order[unknownField]=ASC";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $this->assertEquals(['presenceCount' => 'DESC'], $response->toArray()['pagination']['order']);

    // Invalid direction is silently ignored, falls back to default order
    $iri = $this->getRootWClubUrl($club1) . "/member-presence-stats?order[presenceCount]=INVALID";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $this->assertEquals(['presenceCount' => 'DESC'], $response->toArray()['pagination']['order']);

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

  public function testSalesStats(): void {
    $club1 = _InitStory::club_1();
    $cash = SalePaymentModeFactory::createOne(['name' => 'Espèces', 'kind' => SalePaymentModeKind::payment]);
    $stockRemoval = SalePaymentModeFactory::createOne(['name' => 'Sortie de stock', 'kind' => SalePaymentModeKind::stock_removal]);
    $unused = SalePaymentModeFactory::createOne(['name' => 'Chèque', 'kind' => SalePaymentModeKind::payment]);

    SaleFactory::createOne(['createdAt' => new \DateTimeImmutable(), 'paymentMode' => $cash, 'price' => '10.00']);
    SaleFactory::createOne(['createdAt' => new \DateTimeImmutable(), 'paymentMode' => $cash, 'price' => '5.50']);
    SaleFactory::createOne(['createdAt' => new \DateTimeImmutable(), 'paymentMode' => $stockRemoval, 'price' => '3.00']);

    $this->loggedAsAdminClub1();

    $iri = $this->getRootWClubUrl($club1) . "/sales-stats";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $data = $response->toArray();

    // Total count includes stock removals, total amount excludes them
    $this->assertEquals(3, $data['value']);
    $childMetrics = array_column($data['childMetrics'], 'value', 'name');
    $this->assertEquals(3, $childMetrics['total-count']);
    $this->assertEquals(15.5, $childMetrics['total-amount']);

    // One row per payment mode, including ones with zero sales
    $paymentModesByUuid = array_column($data['values'], null, 'uuid');
    $cashUuid = (string) $cash->getUuid();
    $stockRemovalUuid = (string) $stockRemoval->getUuid();
    $unusedUuid = (string) $unused->getUuid();

    $this->assertArrayHasKey($cashUuid, $paymentModesByUuid);
    $this->assertArrayHasKey($stockRemovalUuid, $paymentModesByUuid);
    $this->assertArrayHasKey($unusedUuid, $paymentModesByUuid);

    $this->assertEquals(2, $paymentModesByUuid[$cashUuid]['count']);
    $this->assertEquals(15.5, $paymentModesByUuid[$cashUuid]['amount']);
    $this->assertEquals(1, $paymentModesByUuid[$stockRemovalUuid]['count']);
    $this->assertEquals(0, $paymentModesByUuid[$stockRemovalUuid]['amount']);
    $this->assertEquals(0, $paymentModesByUuid[$unusedUuid]['count']);
    $this->assertEquals(0, $paymentModesByUuid[$unusedUuid]['amount']);
  }

  public function testSalesStatsWithSameDayWindow(): void {
    $club1 = _InitStory::club_1();
    $cash = SalePaymentModeFactory::createOne(['kind' => SalePaymentModeKind::payment]);

    // A sale today, and one outside of "today"
    SaleFactory::createOne(['createdAt' => new \DateTimeImmutable(), 'paymentMode' => $cash, 'price' => '10.00']);
    SaleFactory::createOne(['createdAt' => new \DateTimeImmutable('-2 days'), 'paymentMode' => $cash, 'price' => '100.00']);

    $this->loggedAsAdminClub1();

    $today = new \DateTimeImmutable()->format('Y-m-d');
    $iri = $this->getRootWClubUrl($club1) . "/sales-stats?start={$today}&end={$today}";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);

    // Regression test: a start=end=<today> window must not silently widen to the whole season
    $this->assertEquals(1, $response->toArray()['value']);
  }

  public function testSalesStatsDoesNotLeakStartDateAcrossRequests(): void {
    $club1 = _InitStory::club_1();
    $cash = SalePaymentModeFactory::createOne(['kind' => SalePaymentModeKind::payment]);

    // One sale safely inside last season, one safely inside the current one.
    $previousSeasonDate = \App\Service\SeasonService::getPreviousSeasonEndDate($club1)->modify('-10 days');
    SaleFactory::createOne(['createdAt' => $previousSeasonDate, 'paymentMode' => $cash, 'price' => '50.00']);
    SaleFactory::createOne(['createdAt' => new \DateTimeImmutable(), 'paymentMode' => $cash, 'price' => '10.00']);

    $this->loggedAsAdminClub1();

    // Request 1: an explicit recent range (sets a real, recent ?start= on the request) -
    // only catches this season's sale.
    $today = new \DateTimeImmutable()->format('Y-m-d');
    $recentStart = new \DateTimeImmutable('-30 days')->format('Y-m-d');
    $iri = $this->getRootWClubUrl($club1) . "/sales-stats?start={$recentStart}&end={$today}";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $this->assertEquals(1, $response->toArray()['value']);

    // Request 2: previous-season=true, with NO start/end params at all. Regression test:
    // MetricProvider is a long-lived singleton (reused across requests under a persistent
    // PHP worker) - its filterDates['start'] must be reset every call, not carried over from
    // request 1's explicit ?start=. A leaked recent start combined with the previous season's
    // (much older) end would invert the window (start > end) and silently match nothing.
    $iri = $this->getRootWClubUrl($club1) . "/sales-stats?previous-season=true";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $this->assertEquals(1, $response->toArray()['value']);
  }

  public function testSalesStatsRequiresPermission(): void {
    $club1 = _InitStory::club_1();
    SaleFactory::createOne(['createdAt' => new \DateTimeImmutable()]);

    $this->loggedAsSupervisorClub1();
    $iri = $this->getRootWClubUrl($club1) . "/sales-stats";
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::not_found->value);

    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);
    $this->loggedAsAdminClub1();
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => Permission::SALE_HISTORY_ACCESS->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    $this->loggedAsSupervisorClub1();
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
  }

  public function testSalesPerItemStats(): void {
    $club1 = _InitStory::club_1();
    SaleFactory::createOne(['createdAt' => new \DateTimeImmutable()]);

    $this->loggedAsAdminClub1();

    $iri = $this->getRootWClubUrl($club1) . "/sales-per-item-stats";
    $response = $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $data = $response->toArray();

    $this->assertArrayHasKey('values', $data);
    $this->assertNotEmpty($data['values']);
    foreach ($data['values'] as $row) {
      $this->assertArrayHasKey('category', $row);
      $this->assertArrayHasKey('itemName', $row);
      $this->assertArrayHasKey('paymentModeName', $row);
      $this->assertArrayHasKey('count', $row);
      $this->assertArrayHasKey('amount', $row);
    }
  }

}
