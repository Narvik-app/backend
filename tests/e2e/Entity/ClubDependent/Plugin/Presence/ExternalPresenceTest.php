<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Presence;

use App\Entity\ClubDependent\Plugin\Presence\ExternalPresence;
use App\Enum\ClubRole;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\ExternalPresenceFactory;
use App\Tests\FixtureFileManager;
use App\Tests\Story\_InitStory;
use App\Tests\Story\ActivityStory;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use function Zenstruck\Foundry\faker;

class ExternalPresenceTest extends AbstractEntityClubLinkedTestCase {
  use InteractsWithMessenger;

  protected int $TOTAL_SUPER_ADMIN = 10;
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  protected int $TOTAL_ADMIN_CLUB_2 = 5;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 10;
  protected int $TOTAL_BADGER_CLUB_1 = 10;
  protected int $TOTAL_BADGER_CLUB_2 = 5;

  protected function getClassname(): string {
    return ExternalPresence::class;
  }

  protected function getRootUrl(): string {
    return "/external-presences";
  }

  #[\Override]
  protected function getCollectionGrantedAccess() : array {
    $access = parent::getCollectionGrantedAccess();
    $access[ClubRole::badger->value] = true;
    return $access;
  }

  public function initDefaultFixtures(): void {
    ExternalPresenceFactory::new([
      'date'       => new \DateTimeImmutable(),
    ])->many(5)->create();
    ExternalPresenceFactory::new([
      'date'       => \DateTimeImmutable::createFromMutable(faker()->dateTimeBetween('-10 days', '-2 days')),
    ])->many(5)->create();

    ExternalPresenceFactory::new([
      'activities' => [ActivityStory::getRandom('activities_club2')],
      'club' => _InitStory::club_2()
    ])->many(5)->create();
  }

  public function testCreate(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::created,
      adminClub1Code: ResponseCodeEnum::created,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub1Code: ResponseCodeEnum::created,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $club1 = _InitStory::club_1();

        $payload = [
          "firstname" => "Test",
          "lastname" => "NAME",
        ];

        $payloadCheck = $payloadCheck + $payload;
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
        $presence = ExternalPresenceFactory::createOne();

        $payloadCheck = [
          "activities" => []
        ];

        // We remove all activities
        $this->makePatchRequest($this->getIriFromResource($presence), ["activities" => []]);
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
        $presence = ExternalPresenceFactory::createOne();
        $this->makeDeleteRequest($this->getIriFromResource($presence));
      },
    );
  }

  public function testGetTodayPresences(): void {
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
    $this->assertCount(5, $response->toArray()['member']);
  }

  public function testExportPresencesInCSV(): void {
    $club = _InitStory::club_1();

    $this->loggedAsAdminClub1();
    $response = $this->makeGetCsvRequest($this->getRootWClubUrl($club) . ".csv");
    $this->assertResponseIsSuccessful();
    $csv = str_getcsv($response->getContent(), separator: ',', escape: '');
    $this->assertEquals("firstname", $csv[0]);
    $this->assertEquals("lastname", $csv[1]);
    $this->assertEquals("date", $csv[2]);
  }

  public function testImportPresencesFromCSV(): void {
    $club = _InitStory::club_1();

    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::EXTERNAL_PRESENCES_NARVIK);
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

    $this->assertCount(3, $response->toArray()['created']);
    $this->assertCount(0, $response->toArray()['warnings']);
    $this->assertCount(0, $response->toArray()['errors']);

    // 3 new presences
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 3, $response->toArray()['member']);

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
    $this->assertCount(0, $response->toArray()['created']);
    $this->assertCount(3, $response->toArray()['warnings']); // Already registered
    $this->assertCount(0, $response->toArray()['errors']);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 3, $response->toArray()['member']);
  }

  public function testCustomFilters(): void {
    $club = _InitStory::club_1();

    // Create 5 presences in current season (today)
    ExternalPresenceFactory::new([
      'date' => new \DateTimeImmutable(),
    ])->many(5)->create();
    
    // Create 5 presences in previous season (5 days before it ended)
    $previousSeasonEnd = \App\Service\SeasonService::getPreviousSeasonEndDate($club);
    ExternalPresenceFactory::new([
      'date' => $previousSeasonEnd->modify('-5 days'),
    ])->many(5)->create();

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['current-season[date]' => true]);
    $this->assertResponseIsSuccessful();
    // Current season should include only presences from the current season
    $this->assertEquals(5, $response->toArray()['totalItems']);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['previous-season[date]' => true]);
    $this->assertResponseIsSuccessful();
    // Previous season should include presences from the previous season
    $this->assertEquals(5, $response->toArray()['totalItems']);
  }
}
