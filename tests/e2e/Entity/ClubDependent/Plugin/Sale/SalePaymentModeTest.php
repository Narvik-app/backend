<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Sale;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentMode;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\SalePaymentModeFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\SalePaymentModeStory;

class SalePaymentModeTest extends AbstractEntityClubLinkedTestCase {
  protected int $TOTAL_SUPER_ADMIN = 3;
  protected int $TOTAL_ADMIN_CLUB_1 = 3;
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 3;

  protected function getClassname(): string {
    return SalePaymentMode::class;
  }

  protected function getRootUrl(): string {
    return "/sale-payment-modes";
  }

  public function initDefaultFixtures(): void {
    SalePaymentModeStory::load();
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

        $payload = [
          "name" => "Test$id",
          "icon" => "credit-card"
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
        $item = SalePaymentModeFactory::createOne();

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
        $item = SalePaymentModeFactory::createOne();
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
