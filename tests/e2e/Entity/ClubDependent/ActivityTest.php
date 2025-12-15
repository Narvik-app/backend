<?php

namespace App\Tests\e2e\Entity\ClubDependent;

use App\Entity\ClubDependent\Activity;
use App\Enum\ClubRole;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\ActivityFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\ActivityStory;
use Zenstruck\Foundry\Persistence\Proxy;

class ActivityTest extends AbstractEntityClubLinkedTestCase {
  protected int $TOTAL_SUPER_ADMIN = 9; // 9 = count ActivityStory::activities_club1
  protected int $TOTAL_ADMIN_CLUB_1 = 9;
  protected int $TOTAL_ADMIN_CLUB_2 = 1;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 9;
  protected int $TOTAL_MEMBER_CLUB_1 = 9;
  protected int $TOTAL_BADGER_CLUB_1 = 9;
  protected int $TOTAL_BADGER_CLUB_2 = 1;

  protected function getClassname(): string {
    return Activity::class;
  }

  protected function getRootUrl(): string {
    return "/activities";
  }

  #[\Override]
  protected function getCollectionGrantedAccess() : array {
    $access = parent::getCollectionGrantedAccess();
    $access[ClubRole::member->value] = true;
    $access[ClubRole::badger->value] = true;
    return $access;
  }

  public function initDefaultFixtures(): void {
    ActivityStory::load();
  }

  public function testCreate(): void {
    $club1 = _InitStory::club_1();

    $payload = [
      "name" => 'Test activity',
    ];

    $payloadCheck = $payload;
    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::created,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use ($club1, &$payload, &$payloadCheck) {
        // We update for each test since the name must be unique
        $payload['name'] .= '1';
        $payloadCheck['name'] .= '1';
        $this->makePostRequest($this->getRootWClubUrl($club1), $payload);
      },
    );
  }

  public function testPatch(): void {
    $activity = ActivityStory::getRandom("activities_club1");
    $iri = $this->getIriFromResource($activity);

    $payload = [
      "name" => 'Update activity',
    ];

    $this->makeAllLoggedRequests(
      $payload,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use ($iri, &$payload) {
        $this->makePatchRequest($iri, $payload);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $activity = ActivityFactory::createOne([
          'name' => 'Test activity to remove',
          'club' => _InitStory::club_1(),
        ]);
        $iri = $this->getIriFromResource($activity);

        $this->makeDeleteRequest($iri);
      },
    );
  }

  public function testActivityMergeWithNotExisting(): void {
    /** @var Proxy $activity */
    $activity = ActivityStory::getRandom("activities_club1");
    $iri = $this->getIriFromResource($activity);
    $activityUUid = $activity->getUuid();

    $this->loggedAsSuperAdmin();
    $this->makePatchRequest("$iri/merge", ["target" => "{$activityUUid}-notexisting"]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this::assertJsonContains([
      "detail" => "Target activity not found",
    ]);
  }

  public function testActivityMergeToSelf(): void {
    /** @var Proxy $activity */
    $activity = ActivityStory::getRandom("activities_club1");
    $iri = $this->getIriFromResource($activity);
    $activityUUid = $activity->getUuid();

    $this->loggedAsSuperAdmin();
    $this->makePatchRequest("$iri/merge", ["target" => $activityUUid]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this::assertJsonContains([
      "detail" => "Can't migrate to self activity",
    ]);
  }

  public function testActivityMergeWithOtherClub(): void {
    /** @var Proxy $activity */
    $activity = ActivityStory::getRandom("activities_club1");
    $iri = $this->getIriFromResource($activity);
    $activityUUid = $activity->getUuid();
    /** @var Proxy $activityOtherClub */
    $activityOtherClub = ActivityStory::getRandom("activities_club2");
    $activityUuidOtherClub = $activityOtherClub->getUuid();

    $this->loggedAsSuperAdmin();
    $this->makePatchRequest("$iri/merge", ["target" => $activityUuidOtherClub]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this::assertJsonContains([
      "detail" => "Activity club does not match target activity club",
    ]);
  }

  public function testActivityMerge(): void {
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      requestFunction: function () {
        $activity = ActivityFactory::createOne([
          'name' => 'activity to receive',
          'club' => _InitStory::club_1(),
        ]);
        $activity2 = ActivityFactory::createOne([
          'name' => 'activity to migrate',
          'club' => _InitStory::club_1(),
        ]);
        $iri = $this->getIriFromResource($activity);
        $this->makePatchRequest("$iri/merge", ["target" => $activity2->getUuid()]);
      },
    );
  }

  public function testPatchVisibility(): void {
    $activity = ActivityStory::getRandom("activities_club1");
    $iri = $this->getIriFromResource($activity);

    $this->loggedAsAdminClub1();

    $payload = [
      "visibility" => ClubRole::admin->value,
    ];

    $response = $this->makePatchRequest($iri, $payload);
    $this->assertResponseIsSuccessful();
    $this->assertEquals(ClubRole::admin->value, $response->toArray()['visibility']);

    // Sending with member payload should return null as it's consider the default value

    $payload = [
      "visibility" => ClubRole::member->value,
    ];

    $response = $this->makePatchRequest($iri, $payload);
    $this->assertResponseIsSuccessful();
    $this->assertJsonNotHasKey('visibility', $response);
  }
}
