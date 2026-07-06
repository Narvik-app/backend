<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Enum\ClubRole;
use App\Enum\LoanItemStatus;
use App\Enum\Permission;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\LoanFactory;
use App\Tests\Factory\LoanItemFactory;
use App\Tests\Factory\MemberFactory;
use App\Tests\Story\_InitStory;

class LoanTest extends AbstractEntityClubLinkedTestCase {
  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 10;

  protected function getClassname(): string {
    return Loan::class;
  }

  protected function getRootUrl(): string {
    return "/loans";
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    // Supervisors need LOAN_ACCESS permission to access the collection
    $access[ClubRole::supervisor->value] = false;
    return $access;
  }

  public function initDefaultFixtures(): void {
    LoanFactory::createMany(10, ['endDate' => new \DateTimeImmutable()]);
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
        $item = LoanItemFactory::createOne();
        $itemIri = $this->getIriFromResource($item);

        // LoanItem doesn't carry any of Loan's normalization groups, so it serializes as a plain IRI
        $payloadCheck = ["loanItem" => $itemIri];
        $this->makePostRequest($this->getRootWClubUrl($club1), ["loanItem" => $itemIri]);
      },
    );
  }

  public function testPatch(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $loan = LoanFactory::createOne(['endDate' => null]);
        $payloadCheck = ["comment" => "test$id"];
        $this->makePatchRequest($this->getIriFromResource($loan), $payloadCheck);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $loan = LoanFactory::createOne();
        $this->makeDeleteRequest($this->getIriFromResource($loan));
      },
    );
  }

  /**
   * LoanItemNotAlreadyLoaned: creating a loan for an item that already has an open loan
   * must be rejected — enforced at the API layer, not just the frontend.
   */
  public function testCannotCreateLoanForAlreadyLoanedItem(): void {
    $club = _InitStory::club_1();
    $item = LoanItemFactory::createOne();
    LoanFactory::createOne(['loanItem' => $item, 'endDate' => null]);

    $this->loggedAsAdminClub1();
    $this->makePostRequest($this->getRootWClubUrl($club), ['loanItem' => $this->getIriFromResource($item)]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);
    $this->assertJsonContains(['detail' => 'This item is already on loan.']);
  }

  /**
   * LoanItemMustBeAvailable: creating a loan for an item that isn't "available"
   * (maintenance/sold/retired) must be rejected.
   */
  public function testCannotCreateLoanForUnavailableItem(): void {
    $club = _InitStory::club_1();
    $item = LoanItemFactory::createOne(['status' => LoanItemStatus::retired]);

    $this->loggedAsAdminClub1();
    $this->makePostRequest($this->getRootWClubUrl($club), ['loanItem' => $this->getIriFromResource($item)]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);
    $this->assertJsonContains(['detail' => 'This item is not available for loan.']);
  }

  /**
   * Returning a loan (PATCH endDate) must still work even though the two creation-only
   * validators would otherwise reject an "already loaned" / "unavailable" item — both
   * validators skip existing entities (getId() !== null).
   */
  public function testCanReturnLoanOnAlreadyLoanedItem(): void {
    $item = LoanItemFactory::createOne();
    $loan = LoanFactory::createOne(['loanItem' => $item, 'endDate' => null]);

    $this->loggedAsAdminClub1();
    $this->makePatchRequest($this->getIriFromResource($loan), ['endDate' => new \DateTimeImmutable()->format('c')]);
    $this->assertResponseIsSuccessful();
  }

  public function testFilterByLoanItemAndOpenStatus(): void {
    $club = _InitStory::club_1();
    $item = LoanItemFactory::createOne();
    LoanFactory::createOne(['loanItem' => $item, 'endDate' => null]);
    LoanFactory::createOne(['loanItem' => $item, 'endDate' => new \DateTimeImmutable()]);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club), [
      'loanItem.uuid' => $item->getUuid(),
      'exists[endDate]' => 'false',
    ]);
    $this->assertResponseIsSuccessful();
    $this->assertEquals(1, $response->toArray()['totalItems']);
  }

  public function testBorrowerCanBeFreeTextOrMember(): void {
    $club = _InitStory::club_1();
    $item1 = LoanItemFactory::createOne();
    $item2 = LoanItemFactory::createOne();
    $member = MemberFactory::createOne();

    $this->loggedAsAdminClub1();

    $response = $this->makePostRequest($this->getRootWClubUrl($club), [
      'loanItem' => $this->getIriFromResource($item1),
      'borrowerName' => 'John Doe',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
    // Null properties are omitted from the JSON response entirely, so we only assert borrowerName here
    $this->assertJsonContains(['borrowerName' => 'John Doe']);
    $this->assertArrayNotHasKey('member', $response->toArray());

    $response = $this->makePostRequest($this->getRootWClubUrl($club), [
      'loanItem' => $this->getIriFromResource($item2),
      'member' => $this->getIriFromResource($member),
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
    $this->assertJsonContains(['member' => ['@id' => $this->getIriFromResource($member)]]);
    $this->assertArrayNotHasKey('borrowerName', $response->toArray());
  }
}
