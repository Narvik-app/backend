<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Sale;

use App\Entity\ClubDependent\Plugin\Sale\Sale;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\InventoryItemFactory;
use App\Tests\Factory\SaleFactory;
use App\Tests\Factory\SalePaymentModeFactory;
use App\Tests\FixtureFileManager;
use App\Tests\Story\_InitStory;
use App\Tests\Story\SalePaymentModeStory;
use function Zenstruck\Foundry\faker;

class SaleTest extends AbstractEntityClubLinkedTestCase {
  protected int $TOTAL_SUPER_ADMIN = 10;
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 10;

  protected function getClassname(): string {
    return Sale::class;
  }

  protected function getRootUrl(): string {
    return "/sales";
  }

  public function initDefaultFixtures(): void {
    SaleFactory::createMany(10);
  }

  public function testCreate(): void {
    $club1 = _InitStory::club_1();
    $inventoryItem = InventoryItemFactory::randomOrCreate(['canBeSold' => true]);
    $paymentMode = SalePaymentModeFactory::createOne(['available' => true]);

    $inventoryItemIri = $this->getIriFromResource($inventoryItem);
    $paymentModeIri = $this->getIriFromResource($paymentMode);

    $payload = [
      "salePurchasedItems" => [
        [
          "quantity" => 2,
          "item" => $inventoryItemIri
        ],
      ],
      "paymentMode" => $paymentModeIri,
    ];

    $payloadCheck = [
      "salePurchasedItems" => [
        [
          'quantity' => 2,
          'item' => [
            '@id' => $inventoryItemIri,
          ]
        ]
      ],
      "paymentMode" => [
        '@id' => $paymentModeIri,
      ]
    ];

    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::created,
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
    // Update a sale created today
    $this->makeAllLoggedRequests(
      requestFunction: function (string $level, ?int $id) {
        $item = SaleFactory::createOne(['createdAt' => new \DateTimeImmutable()]);
        $this->makePatchRequest($this->getIriFromResource($item), ['comment' => 'test']);
      },
    );

    // Update a sale creating days ago (only admin can)
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) {
        $item = SaleFactory::createOne([
          'createdAt' => \DateTimeImmutable::createFromMutable(faker()->dateTimeBetween('-10 days', '-2 days'))
        ]);
        $this->makePatchRequest($this->getIriFromResource($item), ['comment' => 'test']);
      },
    );
  }

  public function testDelete(): void {
    // Deleting a sale created today
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::no_content,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $item = SaleFactory::createOne(['createdAt' => new \DateTimeImmutable()]);
        $this->makeDeleteRequest($this->getIriFromResource($item));
      },
    );

    // Deleting a sale creating days ago (only admin can)
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $item = SaleFactory::createOne([
          'createdAt' => \DateTimeImmutable::createFromMutable(faker()->dateTimeBetween('-10 days', '-2 days'))
        ]);
        $this->makeDeleteRequest($this->getIriFromResource($item));
      },
    );
  }

  public function testExportPresencesInCSV(): void {
    $club = _InitStory::club_1();

    $this->loggedAsAdminClub1();
    $response = $this->makeGetCsvRequest($this->getRootWClubUrl($club) . ".csv");
    $this->assertResponseIsSuccessful();
    $csv = $response->getContent();
    $this->assertTrue(str_contains($csv, "seller.licence"));
  }

  public function testImportPresencesFromCSV(): void {
    SalePaymentModeStory::load();

    $club = _InitStory::club_1();

    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::NARVIK_SALES);
    $fileFail = FixtureFileManager::getUploadedFile(FixtureFileManager::LOGO);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1, $response->toArray()['member']);

    // Not a CSV
    $this->makePostRequest($this->getRootWClubUrl($club) . "/-/from-csv", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $fileFail,
        ],
      ],
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "The \"file\" must be a csv",
    ]);

    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/from-csv", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);
    $this->assertResponseIsSuccessful();

    $this->assertCount(2, $response->toArray()['created']);
    $this->assertCount(0, $response->toArray()['warnings']);
    $this->assertCount(1, $response->toArray()['errors']);

    // 2 new sales
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 2, $response->toArray()['member']);
  }

  public function testCustomFilters(): void {
    $club = _InitStory::club_1();

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['current-season[createdAt]' => true]);
    $this->assertResponseIsSuccessful();
    $this->assertEquals(10, $response->toArray()['totalItems']);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['previous-season[createdAt]' => true]);
    $this->assertResponseIsSuccessful();
    $this->assertEquals(0, $response->toArray()['totalItems']);
  }
}
