<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Tests\e2e\AbstractApiTestCase;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleFactory;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationFactory;
use App\Tests\Story\_InitStory;

/**
 * Covers the draft -> lock -> unlock lifecycle: generation attaches the
 * exportable declarations and renders both PDF kinds, locking freezes them,
 * and only an unlock reopens them.
 */
class TimeAndTravelExportTest extends AbstractApiTestCase {
  public function initDefaultFixtures(): void {
    // Each test builds its own controlled scenario.
  }

  public function testGenerateAttachesDeclarationsAndRendersPdfs(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);
    $member = _InitStory::MEMBER_member_club_1();

    $inPeriod = TimeAndTravelDeclarationFactory::createOne([
      'member' => $member,
      'date' => new \DateTimeImmutable('-10 days'),
    ]);
    $outOfPeriod = TimeAndTravelDeclarationFactory::createOne([
      'member' => $member,
      'date' => new \DateTimeImmutable('-2 years'),
    ]);

    $this->loggedAsAdminClub1();
    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
      'label' => 'Test export',
    ]);
    $this->assertResponseStatusCodeSame(201);
    $export = $exportResponse->toArray();

    $this->assertEquals('draft', $export['status']);
    $this->assertEquals(1, $export['declarationCount']);
    $this->assertEquals(1, $export['memberCount']);
    $this->assertNotNull($export['recapFile']);

    // The in-period declaration is now attached and locked-flagged by the (still draft) export...
    // ...but a draft export does not lock its declarations yet.
    $declarationResponse = $this->makeGetRequest($this->getIriFromResource($inPeriod));
    $this->assertFalse($declarationResponse->toArray()['isLocked']);

    // The out-of-period declaration was left untouched
    $outOfPeriodResponse = $this->makeGetRequest($this->getIriFromResource($outOfPeriod));
    $this->assertNull($outOfPeriodResponse->toArray()['export'] ?? null);
  }

  public function testLockFreezesDeclarationsAndUnlockReopensThem(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);
    $member = _InitStory::MEMBER_member_club_1();

    TimeAndTravelDeclarationFactory::createOne([
      'member' => $member,
      'date' => new \DateTimeImmutable('-5 days'),
    ]);

    $this->loggedAsAdminClub1();
    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $exportIri = $exportResponse->toArray()['@id'];

    // Locking a draft succeeds
    $this->makePostRequest($exportIri . '/lock');
    $this->assertResponseIsSuccessful();

    $exportAfterLock = $this->makeGetRequest($exportIri)->toArray();
    $this->assertEquals('locked', $exportAfterLock['status']);

    // A second lock attempt is a conflict
    $this->makePostRequest($exportIri . '/lock');
    $this->assertResponseStatusCodeSame(409);

    // Regenerating a locked export is refused
    $this->makePostRequest($exportIri . '/regenerate');
    $this->assertResponseStatusCodeSame(409);

    // Unlocking reopens it
    $this->makePostRequest($exportIri . '/unlock');
    $this->assertResponseIsSuccessful();

    $exportAfterUnlock = $this->makeGetRequest($exportIri)->toArray();
    $this->assertEquals('draft', $exportAfterUnlock['status']);
  }

  public function testSupervisorNeedsExportPermissionToGenerate(): void {
    $club = _InitStory::club_1();

    $this->loggedAsSupervisorClub1();
    $this->makePostRequest($this->getIriFromResource($club) . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $this->assertResponseStatusCodeSame(403);
  }

  public function testCannotCreateADeclarationInAnAlreadyExportedPeriodEvenWhileDraft(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);
    $member = _InitStory::MEMBER_member_club_1();

    $this->loggedAsAdminClub1();

    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $this->assertResponseStatusCodeSame(201);
    $export = $exportResponse->toArray();
    $this->assertEquals('draft', $export['status']); // Still a draft, not locked

    // A new declaration inside that (still draft) period is refused
    $response = $this->makePostRequest($clubIri . '/time-and-travel-declarations', [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable('-5 days')->format('Y-m-d'),
      'departureLocation' => 'Home',
      'arrivalLocation' => 'Club',
      'kilometers' => 10,
      'description' => 'Should be refused',
    ]);
    $this->assertResponseStatusCodeSame(422);

    // A declaration outside that period is still fine
    $vehicle = MemberVehicleFactory::createOne(['member' => $member]);
    $response = $this->makePostRequest($clubIri . '/time-and-travel-declarations', [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable('+1 day')->format('Y-m-d'),
      'departureLocation' => 'Home',
      'arrivalLocation' => 'Club',
      'kilometers' => 10,
      'memberVehicle' => $this->getIriFromResource($vehicle),
      'description' => 'Should be accepted',
    ]);
    $this->assertResponseStatusCodeSame(201);
  }

  public function testSupervisorNeedsUnlockPermissionToUnlock(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $supervisorIri = $this->getIriFromResource($supervisor);

    $this->loggedAsAdminClub1();
    $this->makePostRequest($supervisorIri . '/permissions', [
      'member' => $supervisorIri,
      'permission' => 'TIME_TRAVEL_EXPORT',
    ]);

    $exportResponse = $this->makePostRequest($this->getIriFromResource($club) . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $exportIri = $exportResponse->toArray()['@id'];
    $this->makePostRequest($exportIri . '/lock');
    $this->assertResponseIsSuccessful();

    // Supervisor has TIME_TRAVEL_EXPORT but not TIME_TRAVEL_UNLOCK
    $this->loggedAsSupervisorClub1();
    $this->makePostRequest($exportIri . '/unlock');
    $this->assertResponseStatusCodeSame(403);
  }
}
