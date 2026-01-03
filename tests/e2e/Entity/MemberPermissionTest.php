<?php

namespace App\Tests\e2e\Entity;

use App\Entity\ClubDependent\Member;
use App\Enum\Permission;
use App\Tests\e2e\AbstractApiTestCase;
use App\Tests\Factory\MemberFactory;
use App\Tests\Story\_InitStory;

/**
 * Test permission management functionality using API Platform resources
 */
class MemberPermissionTest extends AbstractApiTestCase {

  /**
   * Get the permissions URL for a member
   */
  private function getMemberPermissionsUrl(Member $member, ?string $permissionUuid = null): string {
    $memberUrl = $this->getIriFromResource($member);
    $url = "{$memberUrl}/permissions";
    if ($permissionUuid) {
      $url .= "/{$permissionUuid}";
    }
    return $url;
  }

  /**
   * Test that admin can view supervisor permissions (collection)
   */
  public function testAdminCanViewSupervisorPermissions(): void {
    _InitStory::load();

    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];

    $response = $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
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
    $memberIri = $this->getIriFromResource($supervisor);

    $response = $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(201);

    $data = $response->toArray();
    $this->assertEquals(Permission::EMAIL_EDIT->value, $data['permission']);
  }

  /**
   * Test that admin can revoke permissions from supervisor (DELETE)
   */
  public function testAdminCanRevokePermissions(): void {
    _InitStory::load();

    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $memberIri = $this->getIriFromResource($supervisor);

    // First grant a permission
    $createResponse = $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(201);
    $permissionUuid = $createResponse->toArray()['uuid'];

    // Then revoke it
    $this->makeDeleteRequest($this->getMemberPermissionsUrl($supervisor, $permissionUuid));
    $this->assertResponseStatusCodeSame(204);

    // Verify it's gone
    $listResponse = $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
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
    $memberIri = $this->getIriFromResource($supervisor);

    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
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

    $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
    $this->assertResponseStatusCodeSame(403);
  }

  /**
   * Test that admin from another club cannot modify permissions
   */
  public function testAdminFromOtherClubCannotModifyPermissions(): void {
    _InitStory::load();

    $this->loggedAsAdminClub2();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $memberIri = $this->getIriFromResource($supervisor);

    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
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
    $memberIri = $this->getIriFromResource($supervisor);

    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
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
    $this->assertContains(Permission::EMAIL_EDIT->value, $profile['permissions']);
  }

  /**
   * Test duplicate permission returns error
   */
  public function testDuplicatePermissionReturnsError(): void {
    _InitStory::load();

    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $memberIri = $this->getIriFromResource($supervisor);

    // Create first permission
    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(201);

    // Try to create duplicate
    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(422); // Unique constraint violation
  }

  /**
   * Test that EDIT permission implies ACCESS permission (hierarchy)
   */
  public function testEditPermissionImpliesAccessInSelfResponse(): void {
    _InitStory::load();

    // Grant EMAIL_EDIT permission to supervisor
    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $memberIri = $this->getIriFromResource($supervisor);

    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(201);

    // Check that supervisor has EMAIL_EDIT in /self
    $this->loggedAsSupervisorClub1();
    $response = $this->makeGetRequest("/self");
    $data = $response->toArray();

    // Should have EMAIL_EDIT in permissions
    $profile = $data['linkedProfiles'][0];
    $this->assertContains(Permission::EMAIL_EDIT->value, $profile['permissions']);

    // Get the supervisor's member entity directly and test hasPermission
    $supervisorMember = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];

    // EDIT permission should match directly
    $this->assertTrue($supervisorMember->hasPermission(Permission::EMAIL_EDIT));

    // EDIT permission should also grant ACCESS (hierarchy)
    $this->assertTrue($supervisorMember->hasPermission(Permission::EMAIL_ACCESS));

    // Should NOT have other permissions
    $this->assertFalse($supervisorMember->hasPermission(Permission::EMAIL_TEMPLATE_ACCESS));
    $this->assertFalse($supervisorMember->hasPermission(Permission::IMPORT_MEMBERS_EDIT));
  }

  /**
   * Test granting multiple permissions (ACCESS and EDIT together)
   */
  public function testGrantMultiplePermissions(): void {
    _InitStory::load();

    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $memberIri = $this->getIriFromResource($supervisor);

    // Grant multiple permissions
    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(201);

    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::IMPORT_MEMBERS_ACCESS->value,
    ]);
    $this->assertResponseStatusCodeSame(201);

    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_TEMPLATE_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(201);

    // Verify all permissions are listed
    $response = $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
    $data = $response->toArray();
    $this->assertCount(3, $data['member']);

    // Verify member has permissions
    $supervisorMember = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $permissionValues = $supervisorMember->getPermissionValues();
    $this->assertCount(3, $permissionValues);
    $this->assertContains(Permission::EMAIL_EDIT, $permissionValues);
    $this->assertContains(Permission::IMPORT_MEMBERS_ACCESS, $permissionValues);
    $this->assertContains(Permission::EMAIL_TEMPLATE_EDIT, $permissionValues);
  }

  /**
   * Test ACCESS permission does not grant EDIT
   */
  public function testAccessPermissionDoesNotGrantEdit(): void {
    _InitStory::load();

    $this->loggedAsAdminClub1();
    $supervisor = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $memberIri = $this->getIriFromResource($supervisor);

    // Grant only ACCESS permission
    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_ACCESS->value,
    ]);
    $this->assertResponseStatusCodeSame(201);

    // Verify member has ACCESS but not EDIT
    $supervisorMember = MemberFactory::findBy(['email' => 'supervisor@club1.fr'])[0];
    $this->assertTrue($supervisorMember->hasPermission(Permission::EMAIL_ACCESS));
    $this->assertFalse($supervisorMember->hasPermission(Permission::EMAIL_EDIT));
  }
}
