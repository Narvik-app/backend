<?php

namespace App\Tests\Controller;

use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\Enum\UserSecurityCodeTrigger;
use App\Tests\AbstractTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\MemberFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\UserMemberFactory;
use App\Tests\Factory\UserSecurityCodeFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\GlobalSettingStory;
use Symfony\Component\HttpFoundation\Response;

class LoginTest extends AbstractTestCase {
  public function testSuccessful(): void {
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::ok,
      supervisorClub1Code: ResponseCodeEnum::ok,
      adminClub1Code: ResponseCodeEnum::ok,
      adminClub2Code: ResponseCodeEnum::ok,
      superAdminCode: ResponseCodeEnum::ok,
      badgerClub1Code: ResponseCodeEnum::ok,
      badgerClub2Code: ResponseCodeEnum::ok,
    );
  }

  public function testLoginAsNotActivated(): void {
    // We create a not activated account
    $notActivated = UserFactory::createOne([
      'email' => 'notactivated@user.fr',
      'plainPassword' => 'testuser123',
      'accountActivated' => false,
    ]);

    $this->loggedAs("notactivated@user.fr", "testuser123");
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

    // We enable the account
    $this->loggedAsSuperAdmin();
    $iri = $this->getIriFromResource($notActivated);
    $this->makePatchRequest($iri, ['accountActivated' => true]);

    // Account is now activated, the user can log in
    $this->loggedAs("notactivated@user.fr", "testuser123");
    $this->assertResponseStatusCodeSame(Response::HTTP_OK);
  }

  public function testLoginWithWrongPassword(): void {
    $this->loggedAs("admin@admin.com", "wrong");
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

    // We request a PRIVATE resource
    $this->makeGetRequest('/clubs');
    $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
  }

  /**
   * Account does not exist
   *
   * @return void
   */
  public function testLoginAsUnknown(): void {
    $this->loggedAs("notexisting@test.fr", "test");
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
  }

  public function testLoginWithMultipleProfiles(): void {
    $club1 = _InitStory::club_1();

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest('/self');
    $this->assertCount(1, $response->toArray()['linkedProfiles']);

    // No error when getting members
    $this->makeGetRequest($this->getIriFromResource($club1) . '/members');
    $this->assertResponseIsSuccessful();

    // We linked the admin club 1 with another account
    $member = MemberFactory::createOne(['lastname' => 'Zebre']);
    $userMember = UserMemberFactory::createOne([
      "member" => $member,
      "user" => _InitStory::USER_admin_club_1(),
      "role" => ClubRole::member
    ]);

    $response = $this->makeGetRequest('/self');
    $this->assertCount(2, $response->toArray()['linkedProfiles']);
    $linkedProfiles = $response->toArray()['linkedProfiles'];

    $memberProfile = $linkedProfiles[1];
    $this->assertEquals($member->getUuid()->toString(), $memberProfile['member']['uuid']);
    $this->assertEquals(ClubRole::member->value, $memberProfile['role']);

    $adminProfile = $linkedProfiles[0];
    $this->assertEquals(ClubRole::admin->value, $adminProfile['role']);

    $this->makeGetRequest($this->getIriFromResource($club1) . '/members');
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Missing required 'Profile' header.",
    ]);

    // We try getting as regular member, access should be denied
    $this->selectedProfile($memberProfile['id']);
    $this->makeGetRequest($this->getIriFromResource($club1) . '/members');
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    // As admin club no problem
    $this->selectedProfile($adminProfile['id']);
    $this->makeGetRequest($this->getIriFromResource($club1) . '/members');
    $this->assertResponseIsSuccessful();
  }

  public function testBadgerQuickLogin(): void {
    $this->makePostRequest("/auth/quick-login/bdg", [
      "securityCode" => "invalid",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Invalid security code.",
    ]);

    $club1 = _InitStory::club_1();
    $iri = $this->getIriFromResource($club1);

    // We log as a club admin
    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($iri . "/badger-quick-login");
    $this->assertResponseIsSuccessful();

    $securityCode = $response->toArray()['securityCode'];

    // We log out
    $this->logout();

    $this->makePostRequest("/auth/quick-login/bdg", [
      "securityCode" => $securityCode,
    ]);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains([
      "token" => $club1->getBadgerToken(),
    ]);

  }
}
