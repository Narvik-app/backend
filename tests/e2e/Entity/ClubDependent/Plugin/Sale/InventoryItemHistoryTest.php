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

class InventoryItemHistoryTest extends AbstractEntityClubLinkedTestCase {
  protected int $TOTAL_SUPER_ADMIN = 2;
  protected int $TOTAL_ADMIN_CLUB_1 = 2;
  protected int $TOTAL_ADMIN_CLUB_2 = -1; // -1 due to the hardcoded url (and custom provider), user will get a 404 in the get collection
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 2;

  protected InventoryItem $inventoryItem;

  protected function getClassname(): string {
    return InventoryItemHistory::class;
  }

  protected function getRootUrl(): string {
    // The "root url" is specific since it's a sub-resource of member
    // This should never be call
    throw new \Exception("Subresource! getRootUrl() must not be call.");
  }

  #[\Override]
  protected function getRootWClubUrl(Club $club): string {
    // We fully create the url so testGetCollections can try getting with club2 as root url
    return $this->getIriFromResource($club) . "/inventory-items/{$this->inventoryItem->getUuid()}/histories";
  }

  public function initDefaultFixtures(): void {
    $this->inventoryItem = InventoryItemFactory::createOne();
    InventoryItemHistoryFactory::createMany(2, [
      'item' => $this->inventoryItem,
    ]);
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
        $iri = $this->getRootWClubUrl(_InitStory::club_1());
        $this->makePostRequest($iri);
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
        $iri = $this->getRootWClubUrl(_InitStory::club_1());
        $this->makePatchRequest($iri);
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
        $iri = $this->getRootWClubUrl(_InitStory::club_1());
        $this->makeDeleteRequest($iri);
      },
    );
  }
}
