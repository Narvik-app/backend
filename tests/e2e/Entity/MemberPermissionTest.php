<?php

namespace App\Tests\e2e\Entity;

use App\Enum\Permission;
use App\Tests\e2e\AbstractApiTestCase;
use App\Tests\Factory\MemberFactory;
use App\Tests\Story\_InitStory;

/**
 * Test permission management functionality using API Platform resources
 */
class MemberPermissionTest extends AbstractApiTestCase {

  /**
   * Test that admin can view supervisor permissions (collection)
   */
  public function testAdminCanViewSupervisorPermissions(): void {
    _InitStory::load();

    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $clubUuid = _InitStory::club_1()->getUuid()->toString();

    $response = $this->makeGetRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions");
    $this->assertResponseIsSuccessful();

    $data = $response->toArray();
    $this->assertArrayHasKey('member', $data); // API Platform collection format
  }

  /**
   * Test that admin can grant permissions to supervisor (POST)
   */
  public function testAdminCanGrantPermissions(): void {
    _InitStory::load();

    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $clubUuid = _InitStory::club_1()->getUuid()->toString();
    $memberIri = $this->getIriFromResource($supervisor);

    $response = $this->makePostRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions", [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_SEND->value,
    ]);
    $this->assertResponseStatusCodeSame(201);

    $data = $response->toArray();
    $this->assertEquals(Permission::EMAIL_SEND->value, $data['permission']);
  }

  /**
   * Test that admin can revoke permissions from supervisor (DELETE)
   */
  public function testAdminCanRevokePermissions(): void {
    _InitStory::load();

    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $clubUuid = _InitStory::club_1()->getUuid()->toString();
    $memberIri = $this->getIriFromResource($supervisor);

    // First grant a permission
    $createResponse = $this->makePostRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions", [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_SEND->value,
    ]);
    $this->assertResponseStatusCodeSame(201);
    $permissionUuid = $createResponse->toArray()['uuid'];

    // Then revoke it
    $this->makeDeleteRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions/{$permissionUuid}");
    $this->assertResponseStatusCodeSame(204);

    // Verify it's gone
    $listResponse = $this->makeGetRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions");
    $data = $listResponse->toArray();
    $this->assertEmpty($data['member']);
  }

  /**
   * Test that supervisor cannot create permissions
   */
  public function testSupervisorCannotModifyPermissions(): void {
    _InitStory::load();

    $this->loggedAsSupervisorClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $clubUuid = _InitStory::club_1()->getUuid()->toString();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->makePostRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions", [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_SEND->value,
    ]);
    $this->assertResponseStatusCodeSame(403);
  }

  /**
   * Test that member cannot view permissions
   */
  public function testMemberCannotViewPermissions(): void {
    _InitStory::load();

    $this->loggedAsMemberClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $clubUuid = _InitStory::club_1()->getUuid()->toString();

    $this->makeGetRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions");
    $this->assertResponseStatusCodeSame(403);
  }

  /**
   * Test that admin from another club cannot modify permissions
   */
  public function testAdminFromOtherClubCannotModifyPermissions(): void {
    _InitStory::load();

    $this->loggedAsAdminClub2();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $clubUuid = _InitStory::club_1()->getUuid()->toString();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->makePostRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions", [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_SEND->value,
    ]);
    $this->assertResponseStatusCodeSame(403);
  }

  /**
   * Test that permissions are included in /self response
   */
  public function testPermissionsIncludedInSelfResponse(): void {
    _InitStory::load();

    // First, grant permission as admin
    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $clubUuid = _InitStory::club_1()->getUuid()->toString();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->makePostRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions", [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_SEND->value,
    ]);
    $this->assertResponseStatusCodeSame(201);

    // Now login as supervisor and check /self
    $this->loggedAsSupervisorClub1();
    $response = $this->makeGetRequest("/self");
    $this->assertResponseIsSuccessful();

    $data = $response->toArray();
    $this->assertArrayHasKey('linkedProfiles', $data);
    $this->assertNotEmpty($data['linkedProfiles']);

    $profile = $data['linkedProfiles'][0];
    $this->assertArrayHasKey('permissions', $profile);
    $this->assertContains(Permission::EMAIL_SEND->value, $profile['permissions']);
  }

  /**
   * Test duplicate permission returns error
   */
  public function testDuplicatePermissionReturnsError(): void {
    _InitStory::load();

    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $clubUuid = _InitStory::club_1()->getUuid()->toString();
    $memberIri = $this->getIriFromResource($supervisor);

    // Create first permission
    $this->makePostRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions", [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_SEND->value,
    ]);
    $this->assertResponseStatusCodeSame(201);

    // Try to create duplicate
    $this->makePostRequest("/clubs/{$clubUuid}/members/{$supervisor->getUuid()}/permissions", [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_SEND->value,
    ]);
    $this->assertResponseStatusCodeSame(422); // Unique constraint violation
  }
}
