<?php

namespace App\Tests\Entity\ClubDependent\Plugin\Emailing;

use App\Entity\ClubDependent\Plugin\Emailing\Email;
use App\Enum\ClubRole;
use App\Tests\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\ClubDependent\Plugin\Emailing\EmailFactory;
use App\Tests\Story\_InitStory;

class EmailTest extends AbstractEntityClubLinkedTestCase {
  protected int $TOTAL_SUPER_ADMIN = 10;
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 0;

  protected function getClassname(): string {
    return Email::class;
  }

  protected function getRootUrl(): string {
    return "/emails";
  }

  public function initDefaultFixtures(): void {
    EmailFactory::createMany(10);
  }

  protected function getCollectionGrantedAccess() : array {
    $access = parent::getCollectionGrantedAccess();
    $access[ClubRole::supervisor->value] = false;
    return $access;
  }

  public function testCreate(): void {
    $club1 = _InitStory::club_1();

    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) use ($club1) {
        $this->makePostRequest($this->getRootWClubUrl($club1));
      },
    );
  }

  public function testPatch(): void {
    $this->markTestSkipped();
  }

  public function testDelete(): void {
    // Deleting a sale created today
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) {
        $item = EmailFactory::createOne(['createdAt' => new \DateTimeImmutable()]);
        $this->makeDeleteRequest($this->getIriFromResource($item));
      },
    );
  }

  public function testSendEmailAsNewsletter(): void {
    $this->markTestSkipped();

    // TODO: Add test with attachment and not

    // Check the club monthly email increase well

    // Test with no members

    // Test with a member that has disallowed newsletter

    // Test with a member that has disallowed newsletter and one that allow, count should be one
  }

  public function testSendEmailAsInfo(): void {
    // Notification like renew notice (so must ignore user preference for newsletter)
    $this->markTestSkipped();
  }
}
