<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Enum\ClubRole;
use App\Enum\LoanItemStatus;
use App\Enum\Permission;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\FileFactory;
use App\Tests\Factory\LoanFactory;
use App\Tests\Factory\LoanItemFactory;
use App\Tests\Story\_InitStory;

class LoanItemTest extends AbstractEntityClubLinkedTestCase {
  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 10;

  protected function getClassname(): string {
    return LoanItem::class;
  }

  protected function getRootUrl(): string {
    return "/loan-items";
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    // Supervisors need LOAN_ITEMS_ACCESS permission to access the collection
    $access[ClubRole::supervisor->value] = false;
    return $access;
  }

  public function initDefaultFixtures(): void {
    LoanItemFactory::createMany(10);
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
        $payload = ["name" => "Item$id", "loanPrice" => "5.00"];
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
        $item = LoanItemFactory::createOne();
        $payloadCheck = ["name" => "New name$id"];
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
        $item = LoanItemFactory::createOne();
        $this->makeDeleteRequest($this->getIriFromResource($item));
      },
    );
  }

  public function testSupervisorWithPermissionCanAccessItems(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->loggedAsSupervisorClub1();
    $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    $this->loggedAsAdminClub1();
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => Permission::LOAN_ITEMS_ACCESS->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    $this->loggedAsSupervisorClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseIsSuccessful();
    $this->assertEquals($this->TOTAL_SUPERVISOR_CLUB_1, $response->toArray()['totalItems']);
  }

  /**
   * isCurrentlyLoaned/timesLoaned are computed on postLoad from the item's Loan history
   * (see LoanItemSubscriber) — verify the API actually exposes the live, correct values.
   */
  public function testComputedLoanStateIsExposed(): void {
    $item = LoanItemFactory::createOne();

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getIriFromResource($item));
    $this->assertResponseIsSuccessful();
    $this->assertEquals(false, $response->toArray()['isCurrentlyLoaned']);
    $this->assertEquals(0, $response->toArray()['timesLoaned']);

    // A closed loan: item was loaned once, but not currently
    LoanFactory::createOne(['loanItem' => $item, 'startDate' => new \DateTimeImmutable('-5 days'), 'endDate' => new \DateTimeImmutable('-3 days')]);

    $response = $this->makeGetRequest($this->getIriFromResource($item));
    $this->assertResponseIsSuccessful();
    $this->assertEquals(false, $response->toArray()['isCurrentlyLoaned']);
    $this->assertEquals(1, $response->toArray()['timesLoaned']);

    // An open loan: item is currently out
    LoanFactory::createOne(['loanItem' => $item, 'startDate' => new \DateTimeImmutable(), 'endDate' => null]);

    $response = $this->makeGetRequest($this->getIriFromResource($item));
    $this->assertResponseIsSuccessful();
    $this->assertEquals(true, $response->toArray()['isCurrentlyLoaned']);
    $this->assertEquals(2, $response->toArray()['timesLoaned']);
  }

  /**
   * Regression test: LoanItem.image must be embedded as a full object (with privateUrl), and
   * that shape must match the documented OpenAPI schema for the collection — the item form
   * relies on reading .image.privateUrl directly (see LoanItemForm.vue), so this embedding is
   * intentional; the fix was to make the static normalizationContext (which now includes
   * "common-read") match that reality, not to suppress it. The default fixtures have no image,
   * so only a request against an item that HAS one actually exercises this schema path.
   */
  public function testItemWithImageMatchesCollectionSchema(): void {
    $club = _InitStory::club_1();
    $file = FileFactory::createOne();
    LoanItemFactory::createOne(['image' => $file]);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseIsSuccessful();
    self::assertMatchesResourceCollectionJsonSchema($this->getClassname());

    $members = $response->toArray()['member'];
    $withImage = array_values(array_filter($members, fn(array $m) => isset($m['image'])));
    $this->assertCount(1, $withImage);
    $this->assertIsString($withImage[0]['image']['privateUrl']);
  }

  public function testFilterByStatus(): void {
    $club = _InitStory::club_1();
    LoanItemFactory::createOne(['status' => LoanItemStatus::retired]);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['status' => 'available']);
    $this->assertResponseIsSuccessful();
    $this->assertEquals($this->TOTAL_ADMIN_CLUB_1, $response->toArray()['totalItems']);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['status' => 'retired']);
    $this->assertResponseIsSuccessful();
    $this->assertEquals(1, $response->toArray()['totalItems']);
  }
}
