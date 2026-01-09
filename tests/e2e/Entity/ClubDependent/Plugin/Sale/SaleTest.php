<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Sale;

use App\Entity\ClubDependent\Plugin\Sale\Sale;
use App\Enum\ClubRole;
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

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    // Supervisors need SALE_HISTORY_ACCESS permission to access sales collection
    $access[ClubRole::supervisor->value] = false;
    return $access;
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
      supervisorClub1Code: ResponseCodeEnum::forbidden, // Supervisors need SALE_NEW permission
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

  /**
   * Test that a supervisor WITH the SALE_HISTORY_ACCESS permission can access the sales collection
   */
  public function testSupervisorWithPermissionCanAccessSales(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    // First, verify supervisor cannot access without permission
    $this->loggedAsSupervisorClub1();
    $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    // Admin grants the permission
    $this->loggedAsAdminClub1();
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => \App\Enum\Permission::SALE_HISTORY_ACCESS->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Now supervisor should be able to access
    $this->loggedAsSupervisorClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseIsSuccessful();
    $this->assertGreaterThan(0, $response->toArray()['totalItems']);
  }

  /**
   * Test SALE_NEW permission auto-grants SALE_HISTORY_ACCESS and SALE_INVENTORY_ACCESS
   */
  public function testSaleNewAutoGrantsImpliedPermissions(): void {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    // Admin grants SALE_NEW permission
    $this->loggedAsAdminClub1();
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => \App\Enum\Permission::SALE_NEW->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Verify implied permissions were auto-granted
    $response = $this->makeGetRequest($memberIri . '/permissions');
    $this->assertResponseIsSuccessful();

    $permissions = array_column($response->toArray()['member'], 'permission');
    $this->assertContains(\App\Enum\Permission::SALE_NEW->value, $permissions);
    $this->assertContains(\App\Enum\Permission::SALE_HISTORY_ACCESS->value, $permissions);
    $this->assertContains(\App\Enum\Permission::SALE_INVENTORY_ACCESS->value, $permissions);
  }

  public function testSaleNewCascadeInSelfResponse(): void {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    // Admin grants SALE_NEW permission
    $this->loggedAsAdminClub1();
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => \App\Enum\Permission::SALE_NEW->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Login as supervisor and check /self
    $this->loggedAsSupervisorClub1();
    $response = $this->makeGetRequest("/self");
    $this->assertResponseIsSuccessful();

    $data = $response->toArray();
    $profile = $data['linkedProfiles'][0];
    $permissions = $profile['permissions'];

    $this->assertContains(\App\Enum\Permission::SALE_NEW->value, $permissions);
    $this->assertContains(\App\Enum\Permission::SALE_HISTORY_ACCESS->value, $permissions);
    $this->assertContains(\App\Enum\Permission::SALE_INVENTORY_ACCESS->value, $permissions);
  }

  /**
   * Test supervisor with SALE_NEW can create sales
   */
  public function testSupervisorWithSaleNewCanCreateSales(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $inventoryItem = InventoryItemFactory::createOne(['canBeSold' => true]);
    $paymentMode = SalePaymentModeFactory::createOne(['available' => true]);
    $inventoryItemIri = $this->getIriFromResource($inventoryItem);
    $paymentModeIri = $this->getIriFromResource($paymentMode);

    // Verify supervisor cannot create without permission
    $this->loggedAsSupervisorClub1();
    $this->makePostRequest($this->getRootWClubUrl($club), [
      "salePurchasedItems" => [["quantity" => 1, "item" => $inventoryItemIri]],
      "paymentMode" => $paymentModeIri,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    // Admin grants SALE_NEW permission
    $this->loggedAsAdminClub1();
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => \App\Enum\Permission::SALE_NEW->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Now supervisor can create sales
    $this->loggedAsSupervisorClub1();
    $this->makePostRequest($this->getRootWClubUrl($club), [
      "salePurchasedItems" => [["quantity" => 1, "item" => $inventoryItemIri]],
      "paymentMode" => $paymentModeIri,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
  }

  /**
   * Test cannot remove implied permissions while SALE_NEW is active
   */
  public function testCannotRemoveImpliedPermissionsWhileSaleNewActive(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);
    $clubIri = $this->getIriFromResource($club);

    // Admin grants SALE_NEW permission (which auto-grants implied permissions)
    $this->loggedAsAdminClub1();
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => \App\Enum\Permission::SALE_NEW->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Get the SALE_HISTORY_ACCESS permission UUID
    $response = $this->makeGetRequest($memberIri . '/permissions');
    $permissions = $response->toArray()['member'];
    $historyPermission = array_filter($permissions, fn($p) => $p['permission'] === \App\Enum\Permission::SALE_HISTORY_ACCESS->value);
    $historyPermission = array_values($historyPermission)[0];

    // Try to delete SALE_HISTORY_ACCESS using generic item URL - should fail
    $this->makeDeleteRequest($clubIri . '/permissions/' . $historyPermission['uuid']);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      'detail' => 'Unable to remove this permission because \'SALE_NEW\' is enabled and requires it.',
    ]);
  }
}
