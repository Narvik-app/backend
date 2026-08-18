<?php

namespace App\Tests\e2e\Entity\ClubDependent;

use App\Entity\ClubDependent\MemberControl;
use App\Enum\ClubRole;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\MemberControlFactory;
use App\Tests\Factory\MemberControlTypeFactory;
use App\Tests\Factory\MemberFactory;
use App\Tests\Story\_InitStory;

class MemberControlTest extends AbstractEntityClubLinkedTestCase {
  protected function getClassname(): string {
    return MemberControl::class;
  }

  protected function getRootUrl(): string {
    return "/member-controls";
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    // Read access is open to members and badgers (needed for the presence detail card)
    $access[ClubRole::member->value] = true;
    $access[ClubRole::badger->value] = true;
    return $access;
  }

  public function initDefaultFixtures(): void {
  }

  public function testCreate(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      memberClub1Code: ResponseCodeEnum::forbidden,
      supervisorClub1Code: ResponseCodeEnum::created,
      adminClub1Code: ResponseCodeEnum::created,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub1Code: ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $member = MemberFactory::createOne(['club' => _InitStory::club_1()]);
        $type = MemberControlTypeFactory::createOne();
        $payloadCheck = ['date' => '2025-01-01T00:00:00+00:00'];
        $this->makePostRequest($this->getRootWClubUrl(_InitStory::club_1()), [
          'member' => $this->getIriFromResource($member),
          'type' => $this->getIriFromResource($type),
          'date' => '2025-01-01',
        ]);
      },
    );
  }

  public function testPatch(): void {
    $payloadCheck = [];
    $this->makeAllLoggedRequests(
      $payloadCheck,
      memberClub1Code: ResponseCodeEnum::forbidden,
      supervisorClub1Code: ResponseCodeEnum::ok,
      adminClub1Code: ResponseCodeEnum::ok,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::ok,
      badgerClub1Code: ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use (&$payloadCheck) {
        $control = MemberControlFactory::createOne();
        $payloadCheck = ['date' => '2025-06-01T00:00:00+00:00'];
        $this->makePatchRequest($this->getIriFromResource($control), ['date' => '2025-06-01']);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::forbidden,
      supervisorClub1Code: ResponseCodeEnum::no_content,
      adminClub1Code: ResponseCodeEnum::no_content,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::no_content,
      badgerClub1Code: ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) {
        $control = MemberControlFactory::createOne();
        $this->makeDeleteRequest($this->getIriFromResource($control));
      },
    );
  }

  public function testSupervisorCanCreateAndPatchManualControl(): void {
    $member = _InitStory::MEMBER_member_club_1();
    $type = MemberControlTypeFactory::createOne(['name' => 'QCM']);

    $this->loggedAsSupervisorClub1();
    $response = $this->makePostRequest($this->getRootWClubUrl(_InitStory::club_1()), [
      'member' => $this->getIriFromResource($member),
      'type' => $this->getIriFromResource($type),
      'date' => '2025-01-01',
    ]);
    $this->assertResponseIsSuccessful();
    $controlIri = $response->toArray()['@id'];

    $this->makePatchRequest($controlIri, ['date' => '2025-06-01']);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['date' => '2025-06-01T00:00:00+00:00']);
  }

  public function testMemberCannotWrite(): void {
    $member = _InitStory::MEMBER_member_club_1();
    $type = MemberControlTypeFactory::createOne();

    $this->loggedAsMemberClub1();
    $this->makePostRequest($this->getRootWClubUrl(_InitStory::club_1()), [
      'member' => $this->getIriFromResource($member),
      'type' => $this->getIriFromResource($type),
      'date' => '2025-01-01',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
  }

  public function testDateIsIgnoredOnAutomaticType(): void {
    $member = _InitStory::MEMBER_member_club_1();
    $activity = \App\Tests\Story\ActivityStory::getRandom('activities_club1');
    $type = MemberControlTypeFactory::createOne(['activity' => $activity]);

    // No presence on that activity yet: the manually submitted date must be ignored (not rejected)
    $this->loggedAsSupervisorClub1();
    $response = $this->makePostRequest($this->getRootWClubUrl(_InitStory::club_1()), [
      'member' => $this->getIriFromResource($member),
      'type' => $this->getIriFromResource($type),
      'date' => '2025-01-01',
    ]);
    $this->assertResponseIsSuccessful();
    $this->assertArrayNotHasKey('date', $response->toArray());
    $controlIri = $response->toArray()['@id'];

    // Unrelated edits (e.g. muting the alert) on an automatic control must not fail validation,
    // even once it already carries a real (auto-synced) date
    \App\Tests\Factory\MemberPresenceFactory::new([
      'date' => new \DateTimeImmutable('2025-03-15'),
      'member' => $member,
      'activities' => [$activity],
    ])->create();

    $this->makePatchRequest($controlIri, ['alertDisabled' => true]);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['date' => '2025-03-15T00:00:00+00:00', 'alertDisabled' => true]);

    // Attempting to override the date manually is silently ignored, not rejected
    $this->makePatchRequest($controlIri, ['date' => '2020-01-01']);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['date' => '2025-03-15T00:00:00+00:00']);
  }

  public function testAlertDisabledForcesNullStatus(): void {
    $member = _InitStory::MEMBER_member_club_1();
    $type = MemberControlTypeFactory::createOne(['warningDays' => 335, 'alertDays' => 365]);

    $this->loggedAsSupervisorClub1();
    $response = $this->makePostRequest($this->getRootWClubUrl(_InitStory::club_1()), [
      'member' => $this->getIriFromResource($member),
      'type' => $this->getIriFromResource($type),
      'date' => (new \DateTimeImmutable('-400 days'))->format('Y-m-d'),
    ]);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains(['status' => 'expired']);

    $controlIri = $response->toArray()['@id'];
    $patchResponse = $this->makePatchRequest($controlIri, ['alertDisabled' => true]);
    $this->assertResponseIsSuccessful();
    // A null `status` is serialized as an absent key rather than an explicit null
    $this->assertArrayNotHasKey('status', $patchResponse->toArray());
  }

  public function testRawSqlInsertedControlIsVisibleOnMember(): void {
    // Mirrors how the backfill migration inserts rows: raw SQL, bypassing the ORM entirely.
    $member = _InitStory::MEMBER_member_club_1();
    $type = MemberControlTypeFactory::createOne(['name' => 'Migré']);

    $connection = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->getConnection();
    $connection->executeStatement(
      "INSERT INTO member_control (id, uuid, club_id, member_id, type_id, date, alert_disabled, created_at, updated_at)
       VALUES (nextval('member_control_id_seq'), gen_random_uuid(), :club, :member, :type, :date, false, NOW(), NOW())",
      ['club' => $member->getClub()->getId(), 'member' => $member->getId(), 'type' => $type->getId(), 'date' => '2026-07-03']
    );

    $this->loggedAsSupervisorClub1();
    $response = $this->makeGetRequest($this->getIriFromResource($member));
    $this->assertResponseIsSuccessful();
    $data = $response->toArray();

    $this->assertArrayHasKey('controls', $data);
    $this->assertCount(1, $data['controls']);
    $this->assertEquals('Migré', $data['controls'][0]['type']['name']);
    $this->assertEquals('2026-07-03T00:00:00+00:00', $data['controls'][0]['date']);
  }

  public function testStatusTransitionsAcrossThresholds(): void {
    $member = _InitStory::MEMBER_member_club_1();
    $type = MemberControlTypeFactory::createOne(['warningDays' => 335, 'alertDays' => 365]);

    $this->loggedAsSupervisorClub1();

    $response = $this->makePostRequest($this->getRootWClubUrl(_InitStory::club_1()), [
      'member' => $this->getIriFromResource($member),
      'type' => $this->getIriFromResource($type),
      'date' => (new \DateTimeImmutable('-100 days'))->format('Y-m-d'),
    ]);
    $this->assertJsonContains(['status' => 'valid']);

    $controlIri = $response->toArray()['@id'];
    $this->makePatchRequest($controlIri, ['date' => (new \DateTimeImmutable('-350 days'))->format('Y-m-d')]);
    $this->assertJsonContains(['status' => 'warning']);

    $this->makePatchRequest($controlIri, ['date' => (new \DateTimeImmutable('-400 days'))->format('Y-m-d')]);
    $this->assertJsonContains(['status' => 'expired']);
  }
}
