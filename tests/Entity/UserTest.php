<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Enum\UserSecurityCodeTrigger;
use App\Tests\Entity\Abstract\AbstractEntityTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\MemberFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\UserSecurityCodeFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\GlobalSettingStory;

class UserTest extends AbstractEntityTestCase {

  protected int $TOTAL_SUPER_ADMIN = 11;

  protected function getClassname(): string {
    return User::class;
  }

  protected function getRootUrl(): string {
    return '/users';
  }

  public function testCreate(): void {
    $payload = [
      "email" => "uesrtest@test.fr",
      "firstname" => "Firstname",
      "lastname" => "LASTNAME",
    ];

    // Only super admin can create
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::forbidden,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::forbidden,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use ($payload) {
        $this->makePostRequest($this->getRootUrl(), $payload);
      }
    );
  }

  /**
   * The member and newly created user should be linked automatically
   */
  public function testCreateWithExistingMember(): void {
    $member = MemberFactory::createOne();

    $payload = [
      "firstname" => "firstname",
      "lastname" => "lastname",
      "email" => $member->getEmail(),
      "plainPassword" => "p@ssword1234",
      "accountActivated" => true
    ];

    // We check not a member of this club
    $this->loggedAsSuperAdmin();
    $this->makePostRequest($this->getRootUrl(), $payload);

    $logged = $this->loggedAs($member->getEmail(), "p@ssword1234");
    $this->assertTrue($logged);

    $response = $this->makeGetRequest("/self");
    $this->assertCount(1, $response->toArray()['linkedProfiles']);

    // They are sorted alphabetically
    $this->assertEquals($response->toArray()['linkedProfiles'][0]['club']['name'], 'Club 1');
  }

  public function testPatch(): void {
    // Only super admin can update
    $this->makeAllLoggedRequests(
      $payload,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) {
        $item = UserFactory::createOne();
        $payload = [
          "lastname" => 'lastname updated',
        ];

        $this->makePatchRequest($this->getIriFromResource($item), $payload);
      }
    );
  }

  public function testDelete(): void {
    // Only super admin can delete
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::forbidden,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::forbidden,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::no_content,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) {
        $item = UserFactory::createOne();
        $iri = $this->getIriFromResource($item);
        $this->makeDeleteRequest($iri);
      }
    );
  }

  public function testDeleteSuperAdminAccount(): void {
    $superAdmin = UserFactory::new()->superAdmin("testsuperadmin@admin.com")->create();

    $this->loggedAsSuperAdmin();
    $this->makeDeleteRequest($this->getIriFromResource($superAdmin));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
    $this->assertJsonContains([
      "detail" => "You can't delete an administrator account",
    ]);
  }

  public function testSuperAdminCantChangeOtherSuperAdminPassword(): void {
    $superAdmin = UserFactory::new()->superAdmin("testsuperadmin@admin.com")->create();

    $this->loggedAsSuperAdmin();
    $this->makePatchRequest($this->getIriFromResource($superAdmin), ["plainPassword" => "p@ssword1234"]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
    $this->assertJsonContains([
      "detail" => "You can't change the password of an administrator",
    ]);

    // Changing a regular user work
    $user = _InitStory::USER_member_club_1();
    $this->makePatchRequest($this->getIriFromResource($user), ["plainPassword" => "p@ssword1234"]);
    $this->assertResponseIsSuccessful();
    $logged = $this->loggedAsMemberClub1();
    $this->assertFalse($logged);
    $logged = $this->loggedAs($user->getEmail(), "p@ssword1234");
    $this->assertTrue($logged);
    $this->makeGetRequest("/self");
    $this->assertResponseIsSuccessful();
  }

  public function testSelf(): void {
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::ok,
      adminClub2Code: ResponseCodeEnum::ok,
      badgerClub1Code: ResponseCodeEnum::ok,
      badgerClub2Code: ResponseCodeEnum::ok,
      requestFunction: function (string $level, ?int $id = null) {
        $this->makeGetRequest("/self");
      }
    );
  }

  public function testSelfUpdatePassword(): void {
    // Badger is denied
    $this->loggedAsBadgerClub1();
    $this->makePutRequest("/self/update-password", [
      "current" => "none",
      "new" => "P@ssword1234",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);

    // Member
    $user = _InitStory::USER_member_club_1();
    $this->loggedAsMemberClub1();
    $this->makePutRequest("/self/update-password", [
      "current" => "member123",
      "new" => "P@ssword1234",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $logged = $this->loggedAsMemberClub1();
    $this->assertFalse($logged);
    $logged = $this->loggedAs($user->getEmail(), "P@ssword1234");
    $this->assertTrue($logged);

    // Supervisor
    $user = _InitStory::USER_supervisor_club_1();
    $this->loggedAsSupervisorClub1();
    $this->makePutRequest("/self/update-password", [
      "current" => "admin123",
      "new" => "P@ssword1234",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $logged = $this->loggedAsSupervisorClub1();
    $this->assertFalse($logged);
    $logged = $this->loggedAs($user->getEmail(), "P@ssword1234");
    $this->assertTrue($logged);

    // Admin
    $user = _InitStory::USER_admin_club_1();
    $this->loggedAsAdminClub1();
    $this->makePutRequest("/self/update-password", [
      "current" => "admin123",
      "new" => "P@ssword1234",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $logged = $this->loggedAsAdminClub1();
    $this->assertFalse($logged);
    $logged = $this->loggedAs($user->getEmail(), "P@ssword1234");
    $this->assertTrue($logged);

    // Super Admin
    $user = _InitStory::USER_super_admin();
    $this->loggedAsSuperAdmin();
    $this->makePutRequest("/self/update-password", [
      "current" => "admin123",
      "new" => "P@ssword1234",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $logged = $this->loggedAsSuperAdmin();
    $this->assertFalse($logged);
    $logged = $this->loggedAs($user->getEmail(), "P@ssword1234");
    $this->assertTrue($logged);

    // We test invalid current password
    $this->makePutRequest("/self/update-password", [
      "current" => "admin123",
      "new" => "P@ssword1234",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Invalid password",
    ]);
  }

  public function testPasswordResetRequest(): void {
    GlobalSettingStory::load(); // We load the default settings so we can send email

    $user = _InitStory::USER_member_club_1();
    $this->makePostRequest($this->getRootUrl() . "/-/initiate-reset-password", [
      "email" => $user->getEmail(),
      "token" => "XXXX.DUMMY.TOKEN.XXXX" // We send the dummy token from clouflare
    ]);
    $this->assertResponseIsSuccessful();
  }

  public function testPasswordReset(): void {
    $user = _InitStory::USER_member_club_1();

    $this->makePostRequest($this->getRootUrl() . "/-/reset-password", [
      "email" => $user->getEmail(),
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Missing required field: 'password'",
    ]);

    $this->makePostRequest($this->getRootUrl() . "/-/reset-password", [
      "email" => $user->getEmail(),
      "password" => "p@ssword1234",
      "securityCode" => "invalid"
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "A new security code has been sent.",
    ]);
  }

  public function testDeleteSelf(): void {
    $this->makeAllLoggedRequests(
      superAdminCode: ResponseCodeEnum::bad_request,
      memberClub1Code: ResponseCodeEnum::ok,
      adminClub2Code: ResponseCodeEnum::ok,
      badgerClub1Code: ResponseCodeEnum::ok,
      badgerClub2Code: ResponseCodeEnum::ok,
      requestFunction: function (string $level, ?int $id = null) {
        $this->makeDeleteRequest("/self");
      }
    );
  }

  public function testUserInitiateRegister(): void {
    GlobalSettingStory::load(); // We load the default settings so we can send email

    $this->makePostRequest($this->getRootUrl() . "/-/initiate-register", []);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);

    $this->makePostRequest($this->getRootUrl() . "/-/initiate-register", [
      "email" => "admin@admin.com",
      "accountType" => "personal",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Missing required field: 'token'",
    ]);
    $this->makePostRequest($this->getRootUrl() . "/-/initiate-register", [
      "email" => "admin@admin.com",
      "accountType" => "personal",
      "token" => ""
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Invalid cf token.",
    ]);
    $this->makePostRequest($this->getRootUrl() . "/-/initiate-register", [
      "email" => "admin@admin.com",
      "accountType" => "personal",
      "token" => "XXXX.DUMMY.TOKEN.XXXX" // We send the dummy token from clouflare
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "User already registered.",
    ]);

    $this->makePostRequest($this->getRootUrl() . "/-/initiate-register", [
      "email" => "newaccount@example.com",
      "accountType" => "personal",
      "token" => "XXXX.DUMMY.TOKEN.XXXX" // We send the dummy token from clouflare
    ]);
    $this->assertResponseIsSuccessful();
  }

  public function testAccountRegister(): void {
    GlobalSettingStory::load(); // We load the default settings so we can send email
    $user = UserFactory::createOne(["accountActivated" => false]);

    $this->makePostRequest($this->getRootUrl() . "/-/register", [
      "email" => "invalidemail",
      "securityCode" => "nop",
      "accountType" => "personal",
      "password" => "P@ssword1234",
      "firstname" => "John",
      "lastname" => "Doe",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);

    $this->makePostRequest($this->getRootUrl() . "/-/register", [
      "email" => $user->getEmail(),
      "securityCode" => "wrong code",
      "accountType" => "personal",
      "password" => "P@ssword1234",
      "firstname" => "John",
      "lastname" => "Doe",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "A new security code has been sent.",
    ]);

    // We create our security code
    $userSecurityCode = UserSecurityCodeFactory::createOne([
      "user" => $user,
      "trigger" => UserSecurityCodeTrigger::accountValidation
    ]);


    // Password too short
    $this->makePostRequest($this->getRootUrl() . "/-/register", [
      "accountType" => "personal",
      "email" => $user->getEmail(),
      "securityCode" => $userSecurityCode->getCode(),
      "password" => "short",
      "firstname" => "John",
      "lastname" => "Doe",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Password must be at least 8 letters long.",
    ]);


    $this->makePostRequest($this->getRootUrl() . "/-/register", [
      "accountType" => "personal",
      "email" => $user->getEmail(),
      "securityCode" => $userSecurityCode->getCode(),
      "password" => "P@ssword1234",
      "firstname" => "John",
      "lastname" => "Doe",
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
  }

  public function testClubAccountRegister(): void {
    GlobalSettingStory::load(); // We load the default settings so we can send email
    $user = UserFactory::createOne(["accountActivated" => false]);

    // We create our security code
    $userSecurityCode = UserSecurityCodeFactory::createOne([
      "user" => $user,
      "trigger" => UserSecurityCodeTrigger::accountValidation
    ]);

    $payload = [
      "accountType" => "club",
      "email" => $user->getEmail(),
      "securityCode" => $userSecurityCode->getCode(),
      "firstname" => "John",
      "lastname" => "Doe",
      "password" => "P@ssword1234",
    ];

    // Missing required club setting
    $this->makePostRequest($this->getRootUrl() . "/-/register", $payload);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Missing required field: 'clubName'",
    ]);

    // Club name already present
    $this->makePostRequest($this->getRootUrl() . "/-/register", [
      "clubName" => _InitStory::club_1()->getName(),
      "clubEmail" => "contact@testclub.fr",
      "clubAddress" => "test address",
      "clubZipCode" => 14000,
      "clubCity" => "CAEN",
    ] + $payload);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "Club with same name already exists.",
    ]);

    // Success creating with a new account
    $this->makePostRequest($this->getRootUrl() . "/-/register", [
      "clubName" => "test success new account",
      "clubEmail" => "contact@testclub.fr",
      "clubAddress" => "test address",
      "clubZipCode" => 14000,
      "clubCity" => "CAEN",
    ] + $payload);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);

    // Success creating with an existing account
    $userSecurityCode = UserSecurityCodeFactory::createOne([
      "user" => $user,
      "trigger" => UserSecurityCodeTrigger::accountValidation
    ]);
    $this->makePostRequest($this->getRootUrl() . "/-/register", [
      "accountType" => "club",
      "email" => $user->getEmail(),
      "securityCode" => $userSecurityCode->getCode(),

      "clubName" => "test42",
      "clubEmail" => "contact@testclub2.fr",
      "clubAddress" => "test address",
      "clubZipCode" => 14000,
      "clubCity" => "CAEN",
    ] );
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
  }

  public function testSelfLegalsAccepted(): void {
    // Badger is denied
    $this->loggedAsBadgerClub1();
    $this->makePatchRequest("/self/legals-accepted", []);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);

    // Member
    $this->loggedAsMemberClub1();
    $this->makePatchRequest("/self/legals-accepted", []);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);

    // Supervisor
    $this->loggedAsSupervisorClub1();
    $this->makePatchRequest("/self/legals-accepted", []);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);

    // Admin
    $this->loggedAsAdminClub1();
    $this->makePatchRequest("/self/legals-accepted", []);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);

    // Super Admin
    $this->loggedAsSuperAdmin();
    $this->makePatchRequest("/self/legals-accepted", []);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
  }
}
