<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Tests\e2e\AbstractApiTestCase;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleFactory;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationFactory;
use App\Tests\Factory\MemberFactory;
use App\Tests\Story\_InitStory;
use App\Enum\VehicleCategory;
use App\Enum\VehicleEngineType;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Covers the draft -> lock -> unlock lifecycle: generation attaches the
 * exportable declarations and renders both PDF kinds, locking freezes them,
 * and only an unlock reopens them.
 */
class TimeAndTravelExportTest extends AbstractApiTestCase {
  use InteractsWithMessenger;

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
    ]);
    $this->assertResponseStatusCodeSame(201);
    $export = $exportResponse->toArray();

    $this->assertEquals('draft', $export['status']);
    $this->assertEquals(1, $export['declarationCount']);
    $this->assertEquals(1, $export['memberCount']);
    $this->assertNotNull($export['recapFile']);
    $this->assertNotNull($export['zipFile']);

    // The in-period declaration is now attached and locked-flagged by the (still draft) export...
    // ...but a draft export does not lock its declarations yet.
    $declarationResponse = $this->makeGetRequest($this->getIriFromResource($inPeriod));
    $this->assertFalse($declarationResponse->toArray()['isLocked']);

    // The out-of-period declaration was left untouched
    $outOfPeriodResponse = $this->makeGetRequest($this->getIriFromResource($outOfPeriod));
    $this->assertNull($outOfPeriodResponse->toArray()['export'] ?? null);
  }

  /**
   * The zip is meant to let a comptable download everything for a period in one go, so it must
   * contain the recap plus every member's attestation — nothing more, nothing missing.
   */
  public function testZipBundlesRecapAndEveryAttestation(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);

    $memberA = _InitStory::MEMBER_member_club_1();
    $memberB = MemberFactory::createOne(['club' => $club]);
    foreach ([$memberA, $memberB] as $member) {
      TimeAndTravelDeclarationFactory::createOne(['member' => $member, 'date' => new \DateTimeImmutable('-5 days')]);
    }

    $this->loggedAsAdminClub1();
    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $exportIri = $exportResponse->toArray()['@id'];

    // The freshly-persisted zipFile has no privateUrl yet (File::postLoad only fires on a genuine
    // reload), same as recapFile right after creation — fetch the export back to get it.
    $export = $this->makeGetRequest($exportIri)->toArray();
    $zipUrl = $export['zipFile']['privateUrl'] ?? null;
    $this->assertNotNull($zipUrl);

    $base64 = $this->makeGetRequest($zipUrl)->toArray()['base64'];
    $tmpPath = tempnam(sys_get_temp_dir(), 'zip_test_') . '.zip';
    file_put_contents($tmpPath, base64_decode(explode(',', $base64)[1]));

    $zip = new \ZipArchive();
    $zip->open($tmpPath);
    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $entries[] = $zip->getNameIndex($i);
    }
    $zip->close();
    unlink($tmpPath);

    $this->assertCount(3, $entries);
    $this->assertCount(1, array_filter($entries, static fn (string $name) => str_starts_with($name, 'recapitulatif-')));
    $this->assertCount(2, array_filter($entries, static fn (string $name) => str_starts_with($name, 'attestation-')));
  }

  public function testAttestationsAreOrderedAlphabeticallyByLastnameThenFirstname(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);

    $zoe = MemberFactory::createOne(['club' => $club, 'lastname' => 'Zorro', 'firstname' => 'Zoe']);
    $adamBernard = MemberFactory::createOne(['club' => $club, 'lastname' => 'Adams', 'firstname' => 'Bernard']);
    $adamAlice = MemberFactory::createOne(['club' => $club, 'lastname' => 'Adams', 'firstname' => 'Alice']);

    foreach ([$zoe, $adamBernard, $adamAlice] as $member) {
      TimeAndTravelDeclarationFactory::createOne(['member' => $member, 'date' => new \DateTimeImmutable('-5 days')]);
    }

    $this->loggedAsAdminClub1();
    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $this->assertResponseStatusCodeSame(201);
    $exportIri = $exportResponse->toArray()['@id'];

    $attestations = $this->makeGetRequest($exportIri . '/attestations')->toArray()['member'];
    $names = array_map(static fn (array $a) => $a['member']['fullName'] ?? null, $attestations);

    $this->assertEquals(['ADAMS Alice', 'ADAMS Bernard', 'ZORRO Zoe'], $names);
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

    // Locking a draft is accepted, then regenerates and locks in the background
    $this->makePostRequest($exportIri . '/lock');
    $this->assertResponseStatusCodeSame(202);

    $exportWhileRegenerating = $this->makeGetRequest($exportIri)->toArray();
    $this->assertEquals('draft', $exportWhileRegenerating['status']);
    $this->assertTrue($exportWhileRegenerating['isRegenerating']);

    $this->transport('async_medium')->process();

    $exportAfterLock = $this->makeGetRequest($exportIri)->toArray();
    $this->assertEquals('locked', $exportAfterLock['status']);
    $this->assertFalse($exportAfterLock['isRegenerating']);

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

  public function testTravelAmountUsesTheOfficialScaleOnTheMembersCumulativeDistance(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);
    $member = _InitStory::MEMBER_member_club_1();
    // 5 CV car: tier 2 (5 001-20 000 km) is (d * 0.357) + 1395
    $vehicle = MemberVehicleFactory::createOne([
      'member' => $member,
      'category' => VehicleCategory::car,
      'fiscalPower' => 5,
      'engineType' => VehicleEngineType::petrol,
    ]);

    // Two declarations on the same vehicle, summing to 6 000 km — must be priced as ONE 6 000 km
    // bracket lookup (2 142 + 1 395 = 3 537), not as two separate sub-5 000 km calculations.
    TimeAndTravelDeclarationFactory::createOne(['member' => $member, 'memberVehicle' => $vehicle, 'kilometers' => 4000, 'date' => new \DateTimeImmutable('-10 days')]);
    TimeAndTravelDeclarationFactory::createOne(['member' => $member, 'memberVehicle' => $vehicle, 'kilometers' => 2000, 'date' => new \DateTimeImmutable('-5 days')]);

    $this->loggedAsAdminClub1();
    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $this->assertResponseStatusCodeSame(201);

    $attestations = $this->makeGetRequest($exportResponse->toArray()['@id'] . '/attestations')->toArray()['member'];
    $this->assertCount(1, $attestations);
    $this->assertEquals('3537.00', $attestations[0]['totalTravelAmount']);
  }

  public function testElectricVehicleGetsTheConfiguredBonus(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);
    $member = _InitStory::MEMBER_member_club_1();
    // 5 CV car, 4 000 km stays in tier 1 (up to 5 000 km): d * 0.636 = 2544, +20% = 3052.80
    $vehicle = MemberVehicleFactory::createOne([
      'member' => $member,
      'category' => VehicleCategory::car,
      'fiscalPower' => 5,
      'engineType' => VehicleEngineType::electric,
    ]);
    TimeAndTravelDeclarationFactory::createOne(['member' => $member, 'memberVehicle' => $vehicle, 'kilometers' => 4000, 'date' => new \DateTimeImmutable('-5 days')]);

    $this->loggedAsAdminClub1();
    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $this->assertResponseStatusCodeSame(201);

    $attestations = $this->makeGetRequest($exportResponse->toArray()['@id'] . '/attestations')->toArray()['member'];
    $this->assertEquals('3052.80', $attestations[0]['totalTravelAmount']);
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

  public function testDeclarationsCanStillBeAddedToAPeriodCoveredByADraftExport(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);
    $member = _InitStory::MEMBER_member_club_1();
    $vehicle = MemberVehicleFactory::createOne(['member' => $member]);

    $this->loggedAsAdminClub1();

    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $this->assertResponseStatusCodeSame(201);
    $export = $exportResponse->toArray();
    $this->assertEquals('draft', $export['status']); // Still a draft, not locked

    // A new declaration inside that (still draft) period is accepted — a draft export gets
    // regenerated (picking up new declarations) right before it's locked.
    $this->makePostRequest($clubIri . '/time-and-travel-declarations', [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable('-5 days')->format('Y-m-d'),
      'departureLocation' => 'Home',
      'arrivalLocation' => 'Club',
      'kilometers' => 10,
      'memberVehicle' => $this->getIriFromResource($vehicle),
      'description' => 'Should be accepted',
    ]);
    $this->assertResponseStatusCodeSame(201);
  }

  public function testAddingADeclarationAutomaticallyRegeneratesTheCoveringDraftExport(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);
    $member = _InitStory::MEMBER_member_club_1();
    $vehicle = MemberVehicleFactory::createOne(['member' => $member]);

    $this->loggedAsAdminClub1();

    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $exportIri = $exportResponse->toArray()['@id'];
    $this->assertEquals(0, $exportResponse->toArray()['declarationCount']);

    $this->transport('async_medium')->queue()->assertEmpty();

    $this->makePostRequest($clubIri . '/time-and-travel-declarations', [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable('-5 days')->format('Y-m-d'),
      'departureLocation' => 'Home',
      'arrivalLocation' => 'Club',
      'kilometers' => 10,
      'memberVehicle' => $this->getIriFromResource($vehicle),
      'description' => 'Triggers auto-regeneration',
    ]);
    $this->assertResponseStatusCodeSame(201);

    // The request itself must not have regenerated anything inline
    $this->transport('async_medium')->queue()->assertContains(\App\Message\TimeAndTravelExportRegenerateMessage::class, 1);
    $exportWhileRegenerating = $this->makeGetRequest($exportIri)->toArray();
    $this->assertEquals(0, $exportWhileRegenerating['declarationCount']);
    $this->assertTrue($exportWhileRegenerating['isRegenerating']);

    $this->transport('async_medium')->process();

    $exportAfterRegeneration = $this->makeGetRequest($exportIri)->toArray();
    $this->assertEquals(1, $exportAfterRegeneration['declarationCount']);
    $this->assertFalse($exportAfterRegeneration['isRegenerating']);
  }

  public function testCannotCreateADeclarationInAPeriodCoveredByALockedExport(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);
    $member = _InitStory::MEMBER_member_club_1();

    $this->loggedAsAdminClub1();

    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $exportIri = $exportResponse->toArray()['@id'];
    $this->makePostRequest($exportIri . '/lock');
    $this->assertResponseStatusCodeSame(202);
    $this->transport('async_medium')->process();

    // A new declaration inside that now-locked period is refused
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

  public function testLockRegeneratesFirstSoLastMinuteDeclarationsAreIncluded(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);
    $member = _InitStory::MEMBER_member_club_1();
    $vehicle = MemberVehicleFactory::createOne(['member' => $member]);

    $this->loggedAsAdminClub1();

    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => new \DateTimeImmutable('-1 month')->format('Y-m-d'),
      'endDate' => new \DateTimeImmutable()->format('Y-m-d'),
    ]);
    $exportIri = $exportResponse->toArray()['@id'];
    $this->assertEquals(0, $exportResponse->toArray()['declarationCount']);

    // Added after the draft was generated, but before it's locked
    $this->makePostRequest($clubIri . '/time-and-travel-declarations', [
      'member' => $this->getIriFromResource($member),
      'date' => new \DateTimeImmutable('-5 days')->format('Y-m-d'),
      'departureLocation' => 'Home',
      'arrivalLocation' => 'Club',
      'kilometers' => 10,
      'memberVehicle' => $this->getIriFromResource($vehicle),
      'description' => 'Last minute declaration',
    ]);
    $this->assertResponseStatusCodeSame(201);

    $this->makePostRequest($exportIri . '/lock');
    $this->assertResponseStatusCodeSame(202);
    $this->transport('async_medium')->process();

    $lockedExport = $this->makeGetRequest($exportIri)->toArray();
    $this->assertEquals('locked', $lockedExport['status']);
    $this->assertEquals(1, $lockedExport['declarationCount']);
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
    $this->assertResponseStatusCodeSame(202);
    $this->transport('async_medium')->process();

    // Supervisor has TIME_TRAVEL_EXPORT but not TIME_TRAVEL_UNLOCK
    $this->loggedAsSupervisorClub1();
    $this->makePostRequest($exportIri . '/unlock');
    $this->assertResponseStatusCodeSame(403);
  }

  /**
   * Two exports covering the same dates would each only pick up whatever declarations aren't
   * already attached to the other — silently splitting a period, or leaving an empty duplicate
   * draft, instead of ever raising an error. Overlap must be refused outright.
   */
  public function testCannotCreateAnExportOverlappingAnotherOne(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);

    $this->loggedAsAdminClub1();
    $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => '2026-01-01',
      'endDate' => '2026-01-31',
    ]);
    $this->assertResponseStatusCodeSame(201);

    $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => '2026-01-15',
      'endDate' => '2026-02-15',
    ]);
    $this->assertResponseStatusCodeSame(422);
    $this->assertJsonContains(['violations' => [['propertyPath' => 'startDate']]]);

    // A non-overlapping period is still free to use
    $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => '2026-02-01',
      'endDate' => '2026-02-28',
    ]);
    $this->assertResponseStatusCodeSame(201);
  }

  public function testCanNarrowAnExportsOwnPeriodWithoutSelfConflicting(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);

    $this->loggedAsAdminClub1();
    $exportResponse = $this->makePostRequest($clubIri . '/time-and-travel-exports', [
      'startDate' => '2026-01-01',
      'endDate' => '2026-01-31',
    ]);
    $exportIri = $exportResponse->toArray()['@id'];

    $this->makePatchRequest($exportIri, ['startDate' => '2026-01-05']);
    $this->assertResponseIsSuccessful();
  }
}
