<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\LoanRecording;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\LoanItemFactory;
use App\Tests\Factory\LoanRecordingFactory;
use App\Tests\Factory\MemberFactory;
use App\Tests\Story\_InitStory;

class LoanRecordingTest extends AbstractEntityClubLinkedTestCase {
  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 10;

  protected function getClassname(): string {
    return LoanRecording::class;
  }

  protected function getRootUrl(): string {
    return "/loan-recordings";
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    // Supervisors need LOAN_RECORDINGS_ACCESS permission to access the collection
    $access[ClubRole::supervisor->value] = false;
    return $access;
  }

  public function initDefaultFixtures(): void {
    LoanRecordingFactory::createMany(10);
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
        $payload = ["loanItem" => $this->getIriFromResource($item), "description" => "Test$id"];
        $payloadCheck = ["description" => "Test$id"];
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
        $recording = LoanRecordingFactory::createOne();
        $payloadCheck = ["description" => "New description$id"];
        $this->makePatchRequest($this->getIriFromResource($recording), $payloadCheck);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $recording = LoanRecordingFactory::createOne();
        $this->makeDeleteRequest($this->getIriFromResource($recording));
      },
    );
  }

  /**
   * The description is optional (it used to be required — see setDescription's empty-to-null
   * normalization on the entity).
   */
  public function testDescriptionIsOptional(): void {
    $club = _InitStory::club_1();
    $item = LoanItemFactory::createOne();

    $this->loggedAsAdminClub1();
    $response = $this->makePostRequest($this->getRootWClubUrl($club), [
      "loanItem" => $this->getIriFromResource($item),
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
    // Null properties are omitted from the JSON response entirely
    $this->assertArrayNotHasKey('description', $response->toArray());
  }

  /**
   * Regression test: the author's name must be embedded in the response, not just its IRI —
   * LoanRecording's normalizationContext needs the "autocomplete" group for Member's
   * fullName/firstname/lastname to serialize (previously missing, author always looked "empty").
   */
  public function testAuthorNameIsEmbedded(): void {
    $author = MemberFactory::createOne(['firstname' => 'Jean', 'lastname' => 'Dupont']);
    $recording = LoanRecordingFactory::createOne(['author' => $author]);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getIriFromResource($recording));
    $this->assertResponseIsSuccessful();

    // Member normalizes lastname to uppercase and firstname via ucfirst — see Member::setLastname/setFirstname
    $this->assertEquals('DUPONT Jean', $response->toArray()['author']['fullName']);
  }
}
