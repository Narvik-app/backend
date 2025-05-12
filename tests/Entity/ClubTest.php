<?php

namespace App\Tests\Entity;

use App\Entity\Club;
use App\Tests\Entity\Abstract\AbstractEntityTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\ClubFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\ActivityStory;
use App\Tests\Story\GlobalSettingStory;
use App\Tests\Story\SalePaymentModeStory;
use Zenstruck\Foundry\Persistence\Proxy;

class ClubTest extends AbstractEntityTestCase {
  protected int $TOTAL_SUPER_ADMIN = 5;

  protected function getClassname(): string {
    return Club::class;
  }

  protected function getRootUrl(): string {
    return '/clubs';
  }

  public function testCreate(): void {
    $payload = [
      "name" => 'Club de test',
    ];

    // Only super admin can create
    $this->makeAllLoggedRequests(
      $payload,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::created,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use ($payload) {
        $this->makePostRequest($this->getRootUrl(), $payload);
      }
    );

  }

  public function testPatch(): void {
    $club1 = _InitStory::club_1();
    $iri = $this->getIriFromResource($club1);

    $payload = [
      "name" => 'Update club de test',
    ];

    // Only super admin can update
    $this->makeAllLoggedRequests(
      $payload,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::forbidden,
      adminClub2Code: ResponseCodeEnum::not_found,
      badgerClub2Code: ResponseCodeEnum::not_found,
      requestFunction: function (string $level, ?int $id) use ($iri, $payload) {
        $this->makePatchRequest($iri, $payload);
      }
    );
  }

  public function testDelete(): void {
    // Only super admin can delete
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_found,
      supervisorClub1Code: ResponseCodeEnum::not_found,
      adminClub1Code: ResponseCodeEnum::not_found,
      adminClub2Code: ResponseCodeEnum::not_found,
      superAdminCode: ResponseCodeEnum::no_content,
      badgerClub1Code: ResponseCodeEnum::not_found,
      badgerClub2Code: ResponseCodeEnum::not_found,
      requestFunction: function (string $level, ?int $id) {
        $club = ClubFactory::createOne([
          'name' => 'Club to delete',
          'salesEnabled' => true,
          'badgerToken' => 'clubbadger',
        ]);
        $iri = $this->getIriFromResource($club);
        $this->makeDeleteRequest($iri);
      }
    );
  }

  public function testCascadeDeletion(): void {
    $this->loggedAsSuperAdmin();

    // Activity entity is part use ClubLinkedEntityInterface with SelfClubLinkedEntityTrait
    // All entity using SelfClubLinkedEntityTrait will have the same result (cascade delete is set in the trait)
    /** @var Proxy[] $activitiesPool */
    $activitiesPool = ActivityStory::getPool("activities_club1");
    $activityIris = [];
    foreach ($activitiesPool as $activityClub) {
      $activityIris[] = $this->getIriFromResource($activityClub->_real());
    }

    $club1 = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club1);

    // We check user exist before
    $user = _InitStory::USER_member_club_1();
    $userMembership = $user->getMemberships()->get(0);
    $userMemberIri = $this->getIriFromResource($userMembership->getMember());
    $this->makeGetRequest($userMemberIri);
    $this->assertResponseIsSuccessful();
    // Activity exist
    foreach ($activityIris as $activityIri) {
      $this->makeGetRequest($activityIri);
      $this->assertResponseIsSuccessful();
    }

    // We make the deletion
    $this->makeDeleteRequest($clubIri);
    $this->assertResponseIsSuccessful();

    // We verify all are well removed
    $this->makeGetRequest($userMemberIri);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::not_found->value);
    foreach ($activityIris as $activityIri) {
      $this->makeGetRequest($activityIri);
      $this->assertResponseStatusCodeSame(ResponseCodeEnum::not_found->value);
    }
  }

  public function testBadgerTokenFieldVisibility(): void {
    $club1 = _InitStory::club_1();
    $iri = $this->getIriFromResource($club1);

    $payloadMatch = [
      '@id' => $iri,
      'badgerToken' => $club1->getBadgerToken(),
    ];

    $success = [
      'loggedAsSuperAdmin',
      'loggedAsAdminClub1',
    ];
    foreach ($success as $function) {
      $this->$function();
      $this->makeGetRequest($iri);
      $this->assertJsonContains($payloadMatch);
    }

    $denied = [
      'loggedAsSupervisorClub1',
      'loggedAsMemberClub1',
    ];
    foreach ($denied as $function) {
      $this->$function();
      $response = $this->makeGetRequest($iri);
      $this->assertJsonNotHasKey('badgerToken', $response);
    }
  }

  public function testClubDisabledReadOnly(): void {
    $club1 = _InitStory::club_1();
    $clubSettingIri = $this->getIriFromResource($club1->getSettings());

    $iris = [];
    foreach (ActivityStory::getRandomRange('activities_club1', 1, 5) as $actvt) {
      $iris[] = $this->getIriFromResource($actvt);
    }
    $payload = [
      'excludedActivitiesFromOpeningDays' => $iris,
    ];

    $this->loggedAsAdminClub1();
    $this->makePatchRequest($clubSettingIri, $payload);
    $this->assertResponseIsSuccessful();

    // We disable the club, the patch should be denied
    $this->loggedAsSuperAdmin();
    $this->makePatchRequest($this->getIriFromResource($club1), ['isActivated' => false]);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains([
      "isActivated" => false,
    ]);

    $this->loggedAsAdminClub1();
    $this->makeGetRequest($clubSettingIri);
    $this->assertResponseIsSuccessful(); // Read only

    $this->makePatchRequest($clubSettingIri, $payload);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::locked_423->value);
    $this->assertJsonContains([
      "detail" => "Club not activated.",
    ]);

    // Super admin can still do whatever he wants
    $this->loggedAsSuperAdmin();
    $this->makePatchRequest($clubSettingIri, $payload);
    $this->assertResponseIsSuccessful();
  }

  public function testClubSalesDisabled(): void {
    $club1 = _InitStory::club_1();
    $paymentMode = $this->getIriFromResource(SalePaymentModeStory::getRandom('default'));

    $payload = [
      'name' => 'test',
    ];

    $this->loggedAsAdminClub1();
    $this->makePatchRequest($paymentMode, $payload);
    $this->assertResponseIsSuccessful();

    // We disable the club, the patch should be denied
    $this->loggedAsSuperAdmin();
    $this->makePatchRequest($this->getIriFromResource($club1), ['salesEnabled' => false]);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains([
      "salesEnabled" => false,
    ]);

    $this->loggedAsAdminClub1();
    $this->makeGetRequest($paymentMode);
    $this->assertResponseIsSuccessful(); // Read only

    $this->makePatchRequest($paymentMode, $payload);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::locked_423->value);
    $this->assertJsonContains([
      "detail" => "Sales plugin not activated.",
    ]);

    // Super admin can still do whatever he wants
    $this->loggedAsSuperAdmin();
    $this->makePatchRequest($paymentMode, $payload);
    $this->assertResponseIsSuccessful();
  }

  public function testClubGenerateBadgerToken(): void {
    $club1 = _InitStory::club_1();
    $iri = $this->getIriFromResource($club1);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($iri);
    $this->assertResponseIsSuccessful();
    $this->assertEquals("club1longbadgertoken", $response->toArray()["badgerToken"]);

    $this->makePatchRequest($this->getIriFromResource($club1) . "/generate-badger");
    $this->assertResponseIsSuccessful();

    $response = $this->makeGetRequest($iri);
    $this->assertResponseIsSuccessful();
    $this->assertNotEquals("club1longbadgertoken", $response->toArray()["badgerToken"]);
  }

  public function testClubProgramDeletion(): void {
    GlobalSettingStory::load(); // We load the default settings so we can send email

    $club1 = _InitStory::club_1();
    $iri = $this->getIriFromResource($club1);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($iri);
    $this->assertResponseIsSuccessful();
    $this->assertJsonNotHasKey("deletionDate", $response);

    $this->makePatchRequest($this->getIriFromResource($club1) . "/delete");
    $this->assertResponseIsSuccessful();

    $response = $this->makeGetRequest($iri);
    $this->assertResponseIsSuccessful();
    $this->assertJsonHasKey("deletionDate", $response);
  }

}
