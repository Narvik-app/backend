<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Sale;

use App\Entity\ClubDependent\Plugin\Sale\InventoryCategory;
use App\Enum\ClubRole;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\InventoryCategoryFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\InventoryCategoryStory;

class InventoryCategoryTest extends AbstractEntityClubLinkedTestCase {
  protected int $TOTAL_SUPER_ADMIN = 6;
  protected int $TOTAL_ADMIN_CLUB_1 = 6;
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 6;

  protected function getClassname(): string {
    return InventoryCategory::class;
  }

  protected function getRootUrl(): string {
    return "/inventory-categories";
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    // Supervisors need SALE_CATEGORIES_ACCESS permission to access inventory categories collection
    $access[ClubRole::supervisor->value] = false;
    return $access;
  }

  public function initDefaultFixtures(): void {
    InventoryCategoryStory::load();
  }

  public function testCreate(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::created,
      superAdminCode: ResponseCodeEnum::created,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $club1 = _InitStory::club_1();
        $payload = [
          "name" => "Test$id",
        ];

        $payloadCheck = $payload;
        $this->makePostRequest($this->getRootWClubUrl($club1), $payload);
      },
    );
  }

  public function testPatch(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $item = InventoryCategoryFactory::createOne();

        $payloadCheck = [
          "name" => "My new name$id"
        ];

        $this->makePatchRequest($this->getIriFromResource($item), $payloadCheck);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $item = InventoryCategoryFactory::createOne();
        $this->makeDeleteRequest($this->getIriFromResource($item));
      },
    );
  }

  public function testMove(): void {
    $club1 = _InitStory::club_1();

    $this->loggedAsAdminClub1();

    $response = $this->makeGetRequest($this->getRootWClubUrl($club1));
    $this->assertResponseIsSuccessful();

    $categories = $response->toArray()['member'];
    $first = $categories[0]['@id'];
    $second = $categories[1]['@id'];

    $this->makePutRequest($second . "/move", [
      'direction' => 'toto'
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Direction must be 'up' or 'down'",
    ]);

    $this->makePutRequest($second . "/move", [
      'direction' => 'up'
    ]);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club1));
    $movedCategories = $response->toArray()['member'];
    $this->assertEquals($second, $movedCategories[0]['@id']);
    $this->assertEquals($first, $movedCategories[1]['@id']);

    $this->makePutRequest($second . "/move", [
      'direction' => 'down'
    ]);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club1));
    $movedCategories = $response->toArray()['member'];
    $this->assertEquals($first, $movedCategories[0]['@id']);
    $this->assertEquals($second, $movedCategories[1]['@id']);
  }
}
