<?php

namespace App\Tests\e2e\Entity\ClubDependent;

use App\Entity\ClubDependent\Member;
use App\Enum\ClubRole;
use App\Message\ItacMembersMessage;
use App\Message\ItacSecondaryClubMembersMessage;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\MemberFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\FixtureFileManager;
use App\Tests\Story\_InitStory;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MemberTest extends AbstractEntityClubLinkedTestCase {
  use InteractsWithMessenger;

  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 3;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 3;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 2;
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 3;

  protected function getClassname(): string {
    return Member::class;
  }

  protected function getRootUrl(): string {
    return "/members";
  }

  public function testCreate(): void {
    $club1 = _InitStory::club_1();

    $payload = [
      "firstname" => "firstname",
      "lastname" => "lastname",
      "email" => "email@email.com", // Multiple member can have same email (i.e: Children with no email, parent email so)
    ];

    $payloadCheck = $payload;
    $payloadCheck['firstname'] = ucfirst($payload['firstname']);
    $payloadCheck['lastname'] = strtoupper($payload['lastname']);
    $payloadCheck["fullName"] = $payloadCheck['lastname'] . " " . $payloadCheck['firstname'];

    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::created,
      adminClub1Code: ResponseCodeEnum::created,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::created,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use ($club1, $payload) {
        $this->makePostRequest($this->getRootWClubUrl($club1), $payload);
      },
    );
  }

  /**
   * The user and newly created member should be linked automatically
   */
  public function testCreateWithActiveUserAccount(): void {
    $user = _InitStory::USER_member_club_2();

    $club1 = _InitStory::club_1();

    $payload = [
      "firstname" => "firstname",
      "lastname" => "lastname",
      "email" => $user->getEmail(),
    ];

    // We check not a member of this club
    $this->loggedAsMemberClub2();
    $response = $this->makeGetRequest("/self");
    $this->assertCount(1, $response->toArray()['linkedProfiles']);
    $this->assertEquals($response->toArray()['linkedProfiles'][0]['club']['name'], 'Club 2');

    $this->loggedAsAdminClub1();
    $this->makePostRequest($this->getRootWClubUrl($club1), $payload);

    $this->loggedAsMemberClub2();
    $response = $this->makeGetRequest("/self");
    $this->assertCount(2, $response->toArray()['linkedProfiles']);

    // They are sorted alphabetically
    $this->assertEquals($response->toArray()['linkedProfiles'][0]['club']['name'], 'Club 1');
    $this->assertEquals($response->toArray()['linkedProfiles'][1]['club']['name'], 'Club 2');
  }

  public function testPatch(): void {
    $this->makeAllLoggedRequests(
      requestFunction: function (string $level, ?int $id) {
        $member = MemberFactory::createOne([
          'club' => _InitStory::club_1(),
        ]);
        $iri = $this->getIriFromResource($member);

        $payload = [
          "firstname" => 'Updated name',
        ];

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
        $member = MemberFactory::createOne([
          'club' => _InitStory::club_1(),
        ]);
        $iri = $this->getIriFromResource($member);
        $this->makeDeleteRequest($iri);
      },
    );
  }

  public function testSearchMember(): void {
    $club = _InitStory::club_1();
    $memberClub1 = _InitStory::MEMBER_member_club_1();
    $memberClub2 = _InitStory::MEMBER_member_club_2();

    $this->loggedAsBadgerClub1();
    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/search", [
      'query' => $memberClub1->getFullName(),
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $this->assertCount(1, $response->toArray());

    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/search", [
      'query' => $memberClub2->getFullName(),
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::ok->value);
    $this->assertCount(0, $response->toArray());
  }

  public function testImportEdenMembers(): void {
    $club = _InitStory::club_1();
    $member = _InitStory::MEMBER_member_club_1();

    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::EDEN_MEMBERS);
    $fileFail = FixtureFileManager::getUploadedFile(FixtureFileManager::LOGO);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getIriFromResource($member));
    $this->assertJsonNotHasKey('medicalCertificateExpiration', $response);
    $this->assertJsonContains([
      "medicalCertificateStatus" => "none",
    ]);

    // Wrong file
    $this->makePostRequest($this->getRootWClubUrl($club) . "/-/from-eden", [
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
      "detail" => "The \"file\" must be an XLSX.",
    ]);

    // For the moment it only imports the medical certificate expiration date
    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/from-eden", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);

    $this->assertResponseIsSuccessful();

    $response = $this->makeGetRequest($this->getIriFromResource($member));
    $this->assertJsonHasKey('medicalCertificateExpiration', $response);
    $this->assertJsonContains([
      "medicalCertificateStatus" => "expired",
    ]);
  }

  public function testImportItacMembers(): void {
    $club = _InitStory::club_1();

    $this->transport('async_medium')->queue()->assertEmpty();

    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::ITAC_MEMBERS);
    $fileFail = FixtureFileManager::getUploadedFile(FixtureFileManager::LOGO);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1, $response->toArray()['member']);

    $this->makePostRequest($this->getRootWClubUrl($club) . "/-/from-itac", [
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
      "detail" => "The \"file\" must be a CSV",
    ]);

    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/from-itac", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);

    $this->assertResponseIsSuccessful();
    $this->assertEquals(2, $response->toArray()['lines']);
    $this->transport('async_medium')->queue()->assertCount(1);
    $this->transport('async_medium')->queue()->assertContains(ItacMembersMessage::class, 1);

    // We consume the queue
    $this->transport('async_medium')->process();
    $this->transport('async_medium')->queue()->assertEmpty();

    // 2 new members
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 2, $response->toArray()['member']);

    // Running the import a second time should not change the count
    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/from-itac", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);

    $this->assertResponseIsSuccessful();
    $this->assertEquals(2, $response->toArray()['lines']);
    // We consume the queue
    $this->transport('async_medium')->process();
    $this->transport('async_medium')->queue()->assertEmpty();

    // 2 new members
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 2, $response->toArray()['member']);
  }

  public function testImportItacSecondaryMembers(): void {
    $club = _InitStory::club_1();

    $transport = $this->transport('async_low');
    $transport->queue()->assertEmpty();

    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::ITAC_SECONDARY_MEMBERS);
    $fileFail = FixtureFileManager::getUploadedFile(FixtureFileManager::LOGO);

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1, $response->toArray()['member']);

    $this->makePostRequest($this->getRootWClubUrl($club) . "/-/secondary-from-itac", [
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
      "detail" => "The \"file\" must be a CSV",
    ]);

    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/secondary-from-itac", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);

    $this->assertResponseIsSuccessful();
    $this->assertEquals(2, $response->toArray()['lines']);
    $transport->queue()->assertCount(1);
    $transport->queue()->assertContains(ItacSecondaryClubMembersMessage::class, 1);

    // We consume the queue
    $transport->process();
    $transport->queue()->assertEmpty();

    // 2 new members
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 2, $response->toArray()['member']);

    // Running the import a second time should not change the count
    $response = $this->makePostRequest($this->getRootWClubUrl($club) . "/-/secondary-from-itac", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);

    $this->assertResponseIsSuccessful();
    $this->assertEquals(2, $response->toArray()['lines']);
    // We consume the queue
    $transport->process();
    $transport->queue()->assertEmpty();

    // 2 new members
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1 + 2, $response->toArray()['member']);
  }

  public function testImportItacPhotos(): void {
    $club = _InitStory::club_1();
    // We update a member licence so it's match the one in the fixture zip
    $member = _InitStory::MEMBER_member_club_1();
    $memberIri = $this->getIriFromResource($member);

    $this->loggedAsAdminClub1();
    $this->makePatchRequest($memberIri, ["licence" => "01234321"]);
    $this->assertResponseIsSuccessful();

    $response = $this->makeGetRequest($memberIri);
    $this->assertJsonNotHasKey("profileImage", $response);
    $this->assertJsonContains(["licence" => "01234321"]);

    // We upload the zip
    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::PROFILE_PICTURES);
    $this->makePostRequest($this->getRootWClubUrl($club) . "/-/photos-from-itac", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);
    $this->assertResponseIsSuccessful();

    $response = $this->makeGetRequest($memberIri);
    $this->assertJsonHasKey("profileImage", $response);

    // We get the image
    $r = $this->makeGetRequest($response->toArray(false)['profileImage']['privateUrl']);
    $this->assertResponseIsSuccessful();
  }

  public function testDeleteUserShouldNotCascade(): void {
    $club = _InitStory::club_1();

    $this->loggedAsSuperAdmin();
    $this->makeDeleteRequest($this->getIriFromResource(_InitStory::USER_member_club_1()));
    $this->assertResponseIsSuccessful();

    // Count should not change since we don't delete a member
    $response = $this->makeGetRequest($this->getRootWClubUrl($club));
    $this->assertCount($this->TOTAL_ADMIN_CLUB_1, $response->toArray()['member']);
  }

  public function testUpdateMemberRole(): void {
    $member = _InitStory::MEMBER_member_club_1();
    $iri = $this->getIriFromResource($member);
    $url = $iri . "/role";
    $payload = [
      "role" => ClubRole::admin->value
    ];

    $this->loggedAsMemberClub1();
    $this->makePatchRequest($url, $payload);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    $this->loggedAsSupervisorClub1();
    $this->makePatchRequest($url, $payload);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    $this->loggedAsAdminClub1();
    $this->makePatchRequest($url, ["role" => "toto"]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains(["detail" => "Invalid role."]);

    $this->makePatchRequest($url, $payload);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains($payload);


  }

  /**
   * We set a role on a member that don't have a user account (it will be created in that case but not activated)
   */
  public function testUpdateMemberRoleNoUserAccount(): void {
    $member = MemberFactory::createOne(['club' => _InitStory::club_1()]);
    $iri = $this->getIriFromResource($member);
    $url = $iri . "/role";
    $payload = [
      "role" => ClubRole::admin->value
    ];

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($iri);
    $this->assertJsonNotHasKey("role", $response); // No role in response since not linked with an user

    $this->makePatchRequest($url, $payload);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains($payload);

    $this->makeGetRequest($iri);
    $this->assertJsonContains($payload);
  }

  public function testUpdateMemberRoleNoEmail(): void {
    $member = MemberFactory::createOne(['club' => _InitStory::club_1(), 'email' => null]);
    $iri = $this->getIriFromResource($member);
    $url = $iri . "/role";
    $payload = [
      "role" => ClubRole::admin->value
    ];

    $this->loggedAsAdminClub1();
    $this->makePatchRequest($url, $payload);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains(["detail" => "Member must have an email address."]);

  }

  public function testSelfMember(): void {
    $member = _InitStory::MEMBER_member_club_1();
    $member2 = _InitStory::MEMBER_member_club_2();

    // We can get our self member infos
    $this->loggedAsMemberClub1();

    $this->makeGetRequest($this->getIriFromResource($member));
    $this->assertResponseIsSuccessful();
    $this->assertMatchesResourceItemJsonSchema($this->getClassname());

    // We can't read other member datas
    $this->makeGetRequest($this->getIriFromResource($member2));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
  }

  public function testMemberLinkWithUser(): void {
    $member = _InitStory::MEMBER_member_club_1();
    $memberIri = $this->getIriFromResource($member);

    $member2 = MemberFactory::createOne(['club' => _InitStory::club_1()]);
    $member2Iri = $this->getIriFromResource($member2);

    $user = UserFactory::createOne();
    $user2 = _InitStory::USER_member_club_2();


    $payload = [
      "email" => $user->getEmail(),
    ];

    $this->loggedAsMemberClub1();
    $this->makePatchRequest($memberIri . "/link", $payload);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    $this->loggedAsSupervisorClub1();
    $this->makePatchRequest($memberIri . "/link", $payload);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);


    $this->loggedAsAdminClub1();
    $this->makePatchRequest($memberIri . "/link", ['email' => 'toto']);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "No active account exist with this email.",
    ]);

    $this->makePatchRequest($memberIri . "/link", $payload);
    $this->assertResponseIsSuccessful();

    $this->makePatchRequest($memberIri . "/link", ['email' => $user2->getEmail()]);
    $this->assertResponseIsSuccessful();

    // Member with no linked user
    $response = $this->makeGetRequest($member2Iri);
    $this->assertJsonNotHasKey('linkedEmail', $response);
    $this->makePatchRequest($member2Iri . "/link", ['email' => $user2->getEmail()]);
    $this->assertResponseIsSuccessful();
    $response = $this->makeGetRequest($member2Iri);
    $this->assertNotNull($response->toArray()['linkedEmail']);
  }

  public function testRegisteringMemberSameInfoAsClub1(): void {
    $member = _InitStory::MEMBER_member_club_1();
    $payload = [
      "licence" => $member->getLicence(),
      "email" => $member->getEmail(),
      "firstname" => $member->getFirstname(),
      "lastname" => $member->getLastname(),
    ];

    $this->loggedAsAdminClub2();
    $response = $this->makePostRequest($this->getRootWClubUrl(_InitStory::club_2()), $payload);
    $this->assertResponseIsSuccessful();
    $userIri = $response->toArray()['@id'];

    $this->loggedAsAdminClub1();
    $this->makeDeleteRequest($this->getIriFromResource($member));
    $this->assertResponseIsSuccessful();

    $this->loggedAsAdminClub2();
    $this->makeGetRequest($userIri);
    $this->assertResponseIsSuccessful();
  }

  public function testCustomFilters(): void {
    $club = _InitStory::club_1();

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['current-season[memberSeasons.season]' => true]);
    $this->assertResponseIsSuccessful();
    $this->assertEquals(3, $response->toArray()['totalItems']);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['previous-season[memberSeasons.season]' => true]);
    $this->assertResponseIsSuccessful();
    $this->assertEquals(0, $response->toArray()['totalItems']);

    $response = $this->makeGetRequest($this->getRootWClubUrl($club), ['season-not-renewed[memberSeasons.season]' => true]);
    $this->assertResponseIsSuccessful();
    $this->assertEquals(0, $response->toArray()['totalItems']);
  }
}
