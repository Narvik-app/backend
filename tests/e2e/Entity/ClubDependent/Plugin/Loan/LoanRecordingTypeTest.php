<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\LoanRecordingType;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\LoanRecordingTypeFactory;
use App\Tests\Story\_InitStory;

class LoanRecordingTypeTest extends AbstractEntityClubLinkedTestCase {
  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 10;

  protected function getClassname(): string {
    return LoanRecordingType::class;
  }

  protected function getRootUrl(): string {
    return "/loan-recording-types";
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    // Supervisors need LOAN_RECORDINGS_ACCESS permission to access the collection
    $access[ClubRole::supervisor->value] = false;
    return $access;
  }

  public function initDefaultFixtures(): void {
    LoanRecordingTypeFactory::createMany(10);
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
        $payload = ["name" => "Type$id", "color" => "#3b82f6"];
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
        $type = LoanRecordingTypeFactory::createOne();
        $payloadCheck = ["name" => "New name$id"];
        $this->makePatchRequest($this->getIriFromResource($type), $payloadCheck);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $type = LoanRecordingTypeFactory::createOne();
        $this->makeDeleteRequest($this->getIriFromResource($type));
      },
    );
  }

  public function testSupervisorWithPermissionCanAccessRecordingTypes(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->loggedAsSupervisorClub1();
    $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    $this->loggedAsAdminClub1();
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => Permission::LOAN_RECORDINGS_ACCESS->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    $this->loggedAsSupervisorClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertResponseIsSuccessful();
    $this->assertEquals($this->TOTAL_SUPERVISOR_CLUB_1, $response->toArray()['totalItems']);
  }
}
