<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Presence;

use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Enum\ClubRole;
use App\Message\CerberePresencesDateMessage;
use App\Message\MemberPresencesCsvMessage;
use App\Service\SeasonService;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\ExternalPresenceFactory;
use App\Tests\Factory\MemberFactory;
use App\Tests\Factory\MemberPresenceFactory;
use App\Tests\FixtureFileManager;
use App\Tests\Story\_InitStory;
use App\Tests\Story\ActivityStory;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use function Zenstruck\Foundry\faker;

class MemberPresenceTest extends AbstractEntityClubLinkedTestCase {
  use InteractsWithMessenger;

  protected int $TOTAL_SUPER_ADMIN = 10;
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  protected int $TOTAL_ADMIN_CLUB_2 = 5;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 10;
  protected int $TOTAL_BADGER_CLUB_1 = 10;
  protected int $TOTAL_BADGER_CLUB_2 = 5;

  private Member $selectedMember;

  #[\Override]
  public function setUp(): void {
    parent::setUp();
    $this->selectedMember = _InitStory::MEMBER_member_club_1();
  }

  protected function getClassname(): string {
    return MemberPresence::class;
  }

  protected function getRootUrl(): string {
    return "/member-presences";
  }

  #[\Override]
  protected function getCollectionGrantedAccess() : array {
    $access = parent::getCollectionGrantedAccess();
    $access[ClubRole::badger->value] = true;
    return $access;
  }

  public function initDefaultFixtures(): void {
    // Create 10 presences in current season (today)
    MemberPresenceFactory::new([
      'date'       => new \DateTimeImmutable(),
      'member' => _InitStory::MEMBER_member_club_1(),
    ])->many(10)->create();

    MemberPresenceFactory::new([
      'member' => _InitStory::MEMBER_member_club_2(),
      'activities' => [ActivityStory::getRandom('activities_club2')],
    ])->many(5)->create();
  }

  public function testCreate(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::created,
      adminClub1Code: ResponseCodeEnum::created,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub1Code: ResponseCodeEnum::created,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $club1 = _InitStory::club_1();

        // New member to be sure it has no presence registered to him
        $member = MemberFactory::createOne([
          'club' => $club1,
          'email' => 'membertest@club1.fr',
        ]);

        $memberIri = $this->getIriFromResource($member);

        $payload = [
          "member" => $memberIri,
        ];

        $payloadCheck = [
          "member" => [
            '@id' => $memberIri,
          ]
        ];

        $this->makePostRequest($this->getRootWClubUrl($club1), $payload);
      },
    );
  }

  public function testPatch(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      badgerClub1Code: ResponseCodeEnum::ok,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $memberPresence = MemberPresenceFactory::createOne([
          'club' => _InitStory::club_1(),
          'member' => MemberFactory::createOne(),
        ]);

        $payloadCheck = [
          "activities" => []
        ];

        // We remove all activities
        $this->makePatchRequest($this->getIriFromResource($memberPresence), ["activities" => []]);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::no_content,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      badgerClub1Code: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $memberPresence = MemberPresenceFactory::createOne([
          'club' => _InitStory::club_1(),
          'member' => _InitStory::MEMBER_member_club_1(),
        ]);
        $this->makeDeleteRequest($this->getIriFromResource($memberPresence));
      },
    );
  }

  public function testGetTodayPresences(): void {
    MemberPresenceFactory::new([
      'date'       => \DateTimeImmutable::createFromMutable(faker()->dateTimeBetween('-1 days', '-1 days')),
    ])->many(5)->create();
    MemberPresenceFactory::new([
      'date'       => \DateTimeImmutable::createFromMutable(faker()->dateTimeBetween('+1 days', '+1 days')),
    ])->many(5)->create();

    $this->makeAllLoggedRequests(
      adminClub2Code: ResponseCodeEnum::ok,
      badgerClub1Code: ResponseCodeEnum::ok,
      badgerClub2Code: ResponseCodeEnum::ok,
      requestFunction: function (string $level, ?int $id) {
        $club = "club_" . ($id ?? 1);
        $this->makeGetRequest($this->getRootWClubUrl(_InitStory::$club()) . "/-/today");
      },
    );

    $this->loggedAsBadgerClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl(_InitStory::club_1()) . "/-/today");
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $this->assertCount(10, $response->toArray()['member']);
  }

  public function testImportPresencesFromCerbere(): void {
    $club = _InitStory::club_1();

    $transport = $this->transport('async_low');
    $transport->queue()->assertEmpty();

    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::PRESENCES_CERBERE);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1, $response->toArray()['member']);

    $response = $this->makeGetRequest($this->getIriFromResource($club) . '/external-presences');
    $this->assertCount(0, $response->toArray()['member']);

    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/from-cerbere", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);

    $this->assertResponseIsSuccessful();
    $this->assertEquals(2, $response->toArray()['days']);
    $transport->queue()->assertCount(2);
    $transport->queue()->assertContains(CerberePresencesDateMessage::class, 2);

    // We consume the queue
    $transport->process();
    $transport->queue()->assertEmpty();

    // 2 new presences and 1 external
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 2, $response->toArray()['member']);
    $response = $this->makeGetRequest($this->getIriFromResource($club) . '/external-presences');
    $this->assertCount(1, $response->toArray()['member']);

    // Running the import a second time should not change the count
    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/from-cerbere", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);

    $this->assertResponseIsSuccessful();
    $this->assertEquals(2, $response->toArray()['days']);
    // We consume the queue
    $transport->process();
    $transport->queue()->assertEmpty();

    // 2 new presences and 1 external
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 2, $response->toArray()['member']);
    $response = $this->makeGetRequest($this->getIriFromResource($club) . '/external-presences');
    $this->assertCount(1, $response->toArray()['member']);

  }

  public function testExportPresencesInCSV(): void {
    $club = _InitStory::club_1();

    $this->loggedAsAdminClub1();
    $response = $this->makeGetCsvRequest($this->getRootWClubUrl($club) . ".csv");
    $this->assertResponseIsSuccessful();
    $csv = str_getcsv($response->getContent(), separator: ',', escape: '');
    $this->assertEquals("member.fullName", $csv[0]);
    $this->assertEquals("member.licence", $csv[1]);
    $this->assertEquals("member.uuid", $csv[2]);
    $this->assertEquals("date", $csv[3]);
  }

  public function testImportPresencesFromCSV(): void {
    $club = _InitStory::club_1();

    $transport = $this->transport('async_low');
    $transport->queue()->assertEmpty();

    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::PRESENCES_NARVIK);
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

    $transport->queue()->assertCount(1);
    $transport->queue()->assertContains(MemberPresencesCsvMessage::class, 1);

    // We consume the queue
    $transport->process();
    $transport->queue()->assertEmpty();

    // 2 new presences
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 2, $response->toArray()['member']);

    // Running the import a second time should not change the count
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

    $transport->queue()->assertCount(1);
    $transport->queue()->assertContains(MemberPresencesCsvMessage::class, 1);

    // We consume the queue
    $transport->process();
    $transport->queue()->assertEmpty();

    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 2, $response->toArray()['member']);
  }

  public function testImportPresencesFromExternalPresences(): void {
    $club = _InitStory::club_1();
    ExternalPresenceFactory::createOne([
      'licence' => '10000001',
      'date' => \DateTimeImmutable::createFromFormat('Y-m-d', '2020-01-01'),
    ]);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1, $response->toArray()['member']);

    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/import-from-external-presences");
    $this->assertResponseIsSuccessful();
    $this->assertEquals(1, $response->toArray()['imported']);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 1, $response->toArray()['member']);

    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/import-from-external-presences");
    $this->assertResponseIsSuccessful();
    $this->assertEquals(0, $response->toArray()['imported']);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 1, $response->toArray()['member']);
  }

  public function testGetMemberPresences(): void {
    $this->loggedAsMemberClub1();
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::ok,
      badgerClub1Code: ResponseCodeEnum::ok,

      adminClub2Code: ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) {
        $this->makeGetRequest($this->getIriFromResource($this->selectedMember) . "/presences");
      }
    );
  }

  public function testCustomFilters(): void {
    $club = _InitStory::club_1();

    // Create 5 presences in previous season (5 days before it ended)
    $previousSeasonEnd = SeasonService::getPreviousSeasonEndDate($club);
    MemberPresenceFactory::new([
      'date' => $previousSeasonEnd->modify('-5 days'),
      'member' => _InitStory::MEMBER_member_club_1(),
    ])->many(5)->create();

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['current-season[date]' => true]);
    $this->assertResponseIsSuccessful();
    // Current season should include only presences from the current season
    $this->assertEquals(10, $response->toArray()['totalItems']);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['previous-season[date]' => true]);
    $this->assertResponseIsSuccessful();
    // Previous season should include presences from the previous season
    $this->assertEquals(5, $response->toArray()['totalItems']);
  }
}
