<?php

namespace App\Tests\Entity\ClubDependent\Plugin\Emailing;

use App\Entity\ClubDependent\Plugin\Emailing\EmailTemplate;
use App\Enum\ClubRole;
use App\Tests\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\ClubDependent\Plugin\Emailing\EmailTemplateFactory;
use App\Tests\Story\_InitStory;

class EmailTemplateTest extends AbstractEntityClubLinkedTestCase {

  protected int $TOTAL_SUPER_ADMIN = 10;
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  protected int $TOTAL_ADMIN_CLUB_2 = 0;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 0;

  protected function getClassname(): string {
    return EmailTemplate::class;
  }

  protected function getRootUrl(): string {
    return "/email-templates";
  }

  public function initDefaultFixtures(): void {
    EmailTemplateFactory::createMany(10);
  }

  protected function getCollectionGrantedAccess() : array {
    $access = parent::getCollectionGrantedAccess();
    $access[ClubRole::supervisor->value] = false;
    return $access;
  }

  public function testCreate(): void {
    $club1 = _InitStory::club_1();

    $payload = [
      "name" => 'Test',
      "title" => 'Email title',
      "content" => 'Contenu du mail'
    ];

    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::forbidden,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::created,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub1Code: ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use ($club1, &$payload) {
        $this->makePostRequest($this->getRootWClubUrl($club1), $payload);
      },
    );
  }

  public function testPatch(): void {
    $emailTemplate = EmailTemplateFactory::createOne();

    $this->loggedAsAdminClub1();

    // We can update a draft one
    $this->makePatchRequest($this->getIriFromResource($emailTemplate), [
      'title' => 'Title updated',
    ]);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains([
      "title" => "Title updated",
    ]);

    $this->loggedAsSupervisorClub1();
    $this->makePatchRequest($this->getIriFromResource($emailTemplate), [
      'title' => 'Title updated 2',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

  }

  public function testDelete(): void {
    // Deleting a template created today
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $item = EmailTemplateFactory::createOne(['createdAt' => new \DateTimeImmutable()]);
        $this->makeDeleteRequest($this->getIriFromResource($item));
      },
    );
  }
}
