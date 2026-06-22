<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Sale;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Sale\InventoryItem;
use App\Entity\ClubDependent\Plugin\Sale\InventoryItemHistory;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\InventoryItemFactory;
use App\Tests\Factory\InventoryItemHistoryFactory;
use App\Tests\Story\_InitStory;

class InventoryItemHistoryPerDayTest extends AbstractEntityClubLinkedTestCase {
  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 3;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 3;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = -1; // hardcoded url → 404 for club2 admin
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 3;

  protected InventoryItem $inventoryItem;

  protected function getClassname(): string {
    return InventoryItemHistory::class;
  }

  protected function getRootUrl(): string {
    throw new \Exception("Subresource — getRootUrl() must not be called.");
  }

  #[\Override]
  protected function getRootWClubUrl(Club $club): string {
    return $this->getIriFromResource($club) . "/inventory-items/{$this->inventoryItem->getUuid()}/histories-per-day";
  }

  public function initDefaultFixtures(): void {
    $this->inventoryItem = InventoryItemFactory::createOne();
    // 3 rows on 3 distinct days → 3 per-day aggregated rows
    InventoryItemHistoryFactory::createOne(['item' => $this->inventoryItem, 'createdAt' => new \DateTimeImmutable('-10 days')]);
    InventoryItemHistoryFactory::createOne(['item' => $this->inventoryItem, 'createdAt' => new \DateTimeImmutable('-5 days')]);
    InventoryItemHistoryFactory::createOne(['item' => $this->inventoryItem, 'createdAt' => new \DateTimeImmutable('-1 day')]);
  }

  public function testCreate(): void {
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) {
        $this->makePostRequest($this->getRootWClubUrl(_InitStory::club_1()));
      },
    );
  }

  public function testPatch(): void {
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) {
        $this->makePostRequest($this->getRootWClubUrl(_InitStory::club_1()));
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) {
        $this->makeDeleteRequest($this->getRootWClubUrl(_InitStory::club_1()));
      },
    );
  }

  /**
   * Two rows on the same day must collapse into one, keeping the latest (end-of-day) values.
   */
  public function testAggregatesMultipleRowsPerDay(): void {
    $item = InventoryItemFactory::createOne();
    $day = new \DateTimeImmutable('-2 days');

    InventoryItemHistoryFactory::createOne([
      'item'          => $item,
      'createdAt'     => $day->setTime(9, 0, 0),
      'quantity'      => 100,
      'purchasePrice' => '5.00',
      'sellingPrice'  => '12.00',
    ]);
    InventoryItemHistoryFactory::createOne([
      'item'          => $item,
      'createdAt'     => $day->setTime(17, 0, 0),
      'quantity'      => 90,
      'purchasePrice' => '6.00',
      'sellingPrice'  => '13.00',
    ]);

    $this->loggedAsSupervisorClub1();
    $url = $this->getIriFromResource(_InitStory::club_1()) . "/inventory-items/{$item->getUuid()}/histories-per-day";
    $response = $this->makeGetRequest($url);

    $this->assertResponseIsSuccessful();
    $data = $response->toArray();

    $this->assertEquals(1, $data['totalItems']);
    $this->assertCount(1, $data['member']);

    $row = $data['member'][0];
    $this->assertEquals(90, $row['quantity']);
    $this->assertEquals('6.00', $row['purchasePrice']);
    $this->assertEquals('13.00', $row['sellingPrice']);
  }

  /**
   * ?start and ?end parameters filter rows to the specified date range.
   */
  public function testDateRangeFilter(): void {
    $item = InventoryItemFactory::createOne();

    InventoryItemHistoryFactory::createOne(['item' => $item, 'createdAt' => new \DateTimeImmutable('-20 days')]);
    InventoryItemHistoryFactory::createOne(['item' => $item, 'createdAt' => new \DateTimeImmutable('-10 days')]);
    InventoryItemHistoryFactory::createOne(['item' => $item, 'createdAt' => new \DateTimeImmutable('-2 days')]);

    $this->loggedAsSupervisorClub1();
    $url = $this->getIriFromResource(_InitStory::club_1()) . "/inventory-items/{$item->getUuid()}/histories-per-day";

    // Only the -10d and -2d rows should appear in this range
    $start = (new \DateTimeImmutable('-15 days'))->format('Y-m-d');
    $end   = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $response = $this->makeGetRequest($url . "?start={$start}&end={$end}");

    $this->assertResponseIsSuccessful();
    $this->assertEquals(2, $response->toArray()['totalItems']);
  }

  /**
   * ?pagination=false returns all rows regardless of page size.
   */
  public function testPaginationFalseReturnsAll(): void {
    $item = InventoryItemFactory::createOne();

    // Seed one row per day for 5 distinct days
    for ($i = 1; $i <= 5; $i++) {
      InventoryItemHistoryFactory::createOne([
        'item'      => $item,
        'createdAt' => new \DateTimeImmutable("-{$i} days"),
      ]);
    }

    $this->loggedAsSupervisorClub1();
    $url = $this->getIriFromResource(_InitStory::club_1()) . "/inventory-items/{$item->getUuid()}/histories-per-day";

    // With itemsPerPage=2, first page returns 2
    $paginatedResponse = $this->makeGetRequest($url . '?itemsPerPage=2&page=1');
    $this->assertResponseIsSuccessful();
    $this->assertCount(2, $paginatedResponse->toArray()['member']);
    $this->assertEquals(5, $paginatedResponse->toArray()['totalItems']);

    // With pagination=false, all 5 are returned
    $fullResponse = $this->makeGetRequest($url . '?pagination=false');
    $this->assertResponseIsSuccessful();
    $this->assertCount(5, $fullResponse->toArray()['member']);
  }
}
