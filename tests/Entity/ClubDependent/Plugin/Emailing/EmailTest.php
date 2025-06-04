<?php

namespace App\Tests\Entity\ClubDependent\Plugin\Emailing;

use App\Entity\ClubDependent\Plugin\Emailing\Email;
use App\Enum\ClubRole;
use App\Enum\EmailStatus;
use App\Tests\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\ClubDependent\Plugin\Emailing\EmailFactory;
use App\Tests\FixtureFileManager;
use App\Tests\Story\_InitStory;
use App\Tests\Story\GlobalSettingStory;

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
    // Can't path an item with a status different from draft
    $email = EmailFactory::createOne(['status' => EmailStatus::FAILED]);
    $emailDraft = EmailFactory::createOne(['status' => EmailStatus::DRAFT]);

    $this->loggedAsAdminClub1();
    $this->makePatchRequest($this->getIriFromResource($email), [
      'status' => EmailStatus::SENT,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    // We can update a draft one
    $this->makePatchRequest($this->getIriFromResource($emailDraft), [
      'title' => 'Title updated',
    ]);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains([
      "title" => "Title updated",
    ]);

    $this->loggedAsSupervisorClub1();
    $this->makePatchRequest($this->getIriFromResource($emailDraft), [
      'title' => 'Title updated 2',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

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

  private function getQuotas(): array {
    $club1 = _InitStory::club_1();

    $response = $this->makeGetRequest($this->getIriFromResource($club1));
    $this->assertResponseIsSuccessful();

    $content = $response->toArray(false);

    return [
      "max" => $content['maxMonthlyEmails'],
      "sent" => $content['currentMonthEmailsSent'],
    ];
  }

  public function testSendEmail(): void {
    $club1 = _InitStory::club_1();
    $member = _InitStory::MEMBER_member_club_1();
    $member2 = _InitStory::MEMBER_supervisor_club_1();

    $this->loggedAsAdminClub1();

    $endpoint = $this->getRootWClubUrl($club1) . "/-/send";
    $payload = [
      "title" => "Email Test",
      "content" => "<p>This is a test</p>",
      "members" => $member->getUuid()->toString(),
    ];

    $quotas = $this->getQuotas();
    $this->assertEquals(200, $quotas["max"]);
    $this->assertEquals(0, $quotas["sent"]);

    // Test with no members
    $payloadC = $payload;
    $payloadC["members"] = '';

    $this->makePostRequest($endpoint, [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'parameters' => $payloadC,
      ],
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "No members defined.",
    ]);

    // Emailing not enabled on the server
    $this->makePostRequest($endpoint, [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'parameters' => $payload,
      ],
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Email not enabled.",
    ]);

    GlobalSettingStory::load(); // We load the default settings so we can send email

    // Test with a member that has disallowed newsletter
    $this->makePatchRequest($this->getIriFromResource($member), ['clubNewsletter' => false]);
    $this->assertResponseIsSuccessful();
    $this->makePostRequest($endpoint, [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'parameters' => $payload,
      ],
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "No matching members to send email to.",
    ]);

    // Test with a member that has disallowed newsletter and one that allow, count should be one
    $payloadC = $payload;
    $payloadC['members'] = $payloadC['members'] . ', ' . $member2->getUuid()->toString() . ', ' . $member2->getUuid()->toString();

    $this->makePostRequest($endpoint, [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'parameters' => $payloadC,
      ],
    ]);
    $this->assertResponseIsSuccessful();

    $quotas = $this->getQuotas();
    $this->assertEquals(200, $quotas["max"]);
    $this->assertEquals(1, $quotas["sent"]);

    // We re-enable the user newsletter
    $this->makePatchRequest($this->getIriFromResource($member), ['clubNewsletter' => true]);

    // Making send request as supervisor is forbidden
    $this->loggedAsSupervisorClub1();
    $this->makePostRequest($endpoint, [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'parameters' => $payloadC,
      ],
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
  }

  public function testSendEmailWithAttachment(): void {
    GlobalSettingStory::load(); // We load the default settings so we can send email

    $club1 = _InitStory::club_1();
    $member = _InitStory::MEMBER_member_club_1();
    $member2 = _InitStory::MEMBER_supervisor_club_1();

    $this->loggedAsAdminClub1();

    $endpoint = $this->getRootWClubUrl($club1) . "/-/send";
    $payload = [
      "title" => "Email Test",
      "content" => "<p>This is a test</p>",
      "members" => $member->getUuid()->toString() . ', ' . $member2->getUuid()->toString(),
      "isNewsletter" => false,
      "replyTo" => "test-email@narvik.app"
    ];

    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::PDF, true);

    // First is send
    $this->makePostRequest($endpoint, [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'parameters' => $payload,
        'files' => [
          'file' => $file,
        ],
      ],
    ]);
    FixtureFileManager::removeUploadedFile(FixtureFileManager::PDF);
    $this->assertResponseIsSuccessful();

    $quotas = $this->getQuotas();
    $this->assertEquals(2, $quotas["sent"]);
  }

  public function testSendEmailMaxMonthQuotaReached(): void {
    GlobalSettingStory::load(); // We load the default settings so we can send email

    $club1 = _InitStory::club_1();
    $member = _InitStory::MEMBER_member_club_1();
    $member2 = _InitStory::MEMBER_supervisor_club_1();

    $this->loggedAsSuperAdmin();
    $this->makePatchRequest($this->getIriFromResource($club1), ['maxMonthlyEmails' => 3]);

    $this->loggedAsAdminClub1();

    $endpoint = $this->getRootWClubUrl($club1) . "/-/send";
    $payload = [
      "title" => "Email Test",
      "content" => "<p>This is a test</p>",
      "members" => $member->getUuid()->toString() . ', ' . $member2->getUuid()->toString(),
      "isNewsletter" => false,
      "replyTo" => "test-email@narvik.app"
    ];

    $quotas = $this->getQuotas();
    $this->assertEquals(3, $quotas["max"]);
    $this->assertEquals(0, $quotas["sent"]);

    // First is send
    $this->makePostRequest($endpoint, [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'parameters' => $payload,
      ],
    ]);
    $this->assertResponseIsSuccessful();

    $quotas = $this->getQuotas();
    $this->assertEquals(3, $quotas["max"]);
    $this->assertEquals(2, $quotas["sent"]);

    // Second email batch is out of quota
    $this->makePostRequest($endpoint, [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'parameters' => $payload,
      ],
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Monthly email limit reached.",
    ]);
  }

  public function testUserUnsubscribeFail(): void {
    $club1 = _InitStory::club_1();
    $clubUuid = $club1->getUuid()->toString();

    $member = _InitStory::MEMBER_member_club_1();
    $memberEmail = $member->geteMail();

    $this->makePostRequest('/unsubscribe', [
      'club' => 'not-existing',
      'email' => $memberEmail,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Club not found.",
    ]);
  }

  public function testUserUnsubscribe(): void {
    GlobalSettingStory::load(); // We load the default settings so we can send email

    $club1 = _InitStory::club_1();
    $clubUuid = $club1->getUuid()->toString();

    $member = _InitStory::MEMBER_member_club_1();
    $memberEmail = $member->geteMail();

    $this->makePostRequest('/unsubscribe', [
      'club' => $clubUuid,
      'email' => $memberEmail,
    ]);
    $this->assertResponseIsSuccessful();

    $this->loggedAsAdminClub1();

    // We send a newsletter, should fail since user unsubscribe
    $endpoint = $this->getRootWClubUrl($club1) . "/-/send";
    $payload = [
      "title" => "Email Test",
      "content" => "<p>This is a test</p>",
      "members" => $member->getUuid()->toString(),
    ];

    $quotas = $this->getQuotas();
    $this->assertEquals(200, $quotas["max"]);
    $this->assertEquals(0, $quotas["sent"]);
    $this->makePostRequest($endpoint, [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'parameters' => $payload,
      ],
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "No matching members to send email to.",
    ]);

    $quotas = $this->getQuotas();
    $this->assertEquals(0, $quotas["sent"]);

    // We send a club email (not a newsletter) should still be sent
    $payload['isNewsletter'] = false;
    $this->makePostRequest($endpoint, [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'parameters' => $payload,
      ],
    ]);
    $this->assertResponseIsSuccessful();
    $quotas = $this->getQuotas();
    $this->assertEquals(1, $quotas["sent"]);
  }
}
