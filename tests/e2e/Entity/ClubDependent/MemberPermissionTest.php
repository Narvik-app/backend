<?php

namespace App\Tests\e2e\Entity\ClubDependent;

use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\MemberPermission;
use App\Entity\Club;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Enum\UserRole;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Story\_InitStory;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests for MemberPermission entity (supervisor permissions management)
 */
class MemberPermissionTest extends AbstractEntityClubLinkedTestCase {

  protected function getClassname(): string {
    return MemberPermission::class;
  }

  protected function getRootUrl(): string {
    throw new \Exception("Subresource! getRootUrl() must not be call.");
  }

  #[\Override]
  protected function getRootWClubUrl(Club $club): string {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    return $this->getMemberPermissionsUrl($supervisor);
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    // Only admins and super-admin can view permissions
    return [
      UserRole::super_admin->value => true,
      ClubRole::admin->value => true,
      ClubRole::supervisor->value => false,
      ClubRole::member->value => false,
      ClubRole::badger->value => false,
    ];
  }

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

  // Override admin tests - permissions is a member subresource, not a club subresource
  // So we skip the cross-club check (it's tested in testAdminFromOtherClubCannotModifyPermissions)
  #[\Override]
  public function testGetCollectionAsAdminClub1(): ResponseInterface {
    $this->loggedAsAdminClub1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $response = $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
    $this->assertResponseIsSuccessful();
    return $response;
  }

  #[\Override]
  public function testGetCollectionAsAdminClub2(): ResponseInterface {
    $this->loggedAsAdminClub2();
    // Club 2 admin trying to access club 1 member's permissions should fail
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $response = $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
    $this->assertResponseIsClientError();
    return $response;
  }

  public function testCreate(): void {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->loggedAsAdminClub1();
    $response = $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    $data = $response->toArray();
    $this->assertEquals(Permission::EMAIL_EDIT->value, $data['permission']);
  }

  public function testPatch(): void {
    // MemberPermission doesn't support PATCH - permissions are granted/revoked, not edited
    $this->markTestSkipped('MemberPermission does not support PATCH operation');
  }

  public function testDelete(): void {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->loggedAsAdminClub1();

    // First grant a permission
    $createResponse = $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
    $permissionUuid = $createResponse->toArray()['uuid'];

    // Then revoke it
    $this->makeDeleteRequest($this->getMemberPermissionsUrl($supervisor, $permissionUuid));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::no_content->value);

    // Verify it's gone
    $listResponse = $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
    $data = $listResponse->toArray();
    $this->assertEmpty($data['member']);
  }

  public function testSupervisorCannotModifyPermissions(): void {
    $this->loggedAsSupervisorClub1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
  }

  public function testAdminFromOtherClubCannotModifyPermissions(): void {
    $this->loggedAsAdminClub2();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
  }

  public function testPermissionsIncludedInSelfResponse(): void {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    // First, grant permission as admin
    $this->loggedAsAdminClub1();
    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

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

  public function testDuplicatePermissionReturnsError(): void {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->loggedAsAdminClub1();

    // Create first permission
    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Try to create duplicate
    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);
  }

  public function testEditPermissionImpliesAccess(): void {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    // Grant EMAIL_EDIT permission
    $this->loggedAsAdminClub1();
    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Check that supervisor has EMAIL_EDIT in /self
    $this->loggedAsSupervisorClub1();
    $response = $this->makeGetRequest("/self");
    $data = $response->toArray();

    $profile = $data['linkedProfiles'][0];
    $this->assertContains(Permission::EMAIL_EDIT->value, $profile['permissions']);

    // Test hasPermission hierarchy
    $supervisorMember = _InitStory::MEMBER_supervisor_club_1();
    $this->assertTrue($supervisorMember->hasPermission(Permission::EMAIL_EDIT));
    $this->assertTrue($supervisorMember->hasPermission(Permission::EMAIL_ACCESS)); // Implied by EDIT
    $this->assertFalse($supervisorMember->hasPermission(Permission::EMAIL_TEMPLATE_ACCESS));
  }

  public function testGrantMultiplePermissions(): void {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->loggedAsAdminClub1();

    // Grant multiple permissions
    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::IMPORT_MEMBERS_ACCESS->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_TEMPLATE_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Verify all permissions are listed
    $response = $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
    $data = $response->toArray();
    $this->assertCount(3, $data['member']);

    // Verify member has permissions
    $supervisorMember = _InitStory::MEMBER_supervisor_club_1();
    $permissionValues = $supervisorMember->getPermissionValues();
    $this->assertCount(3, $permissionValues);
    $this->assertContains(Permission::EMAIL_EDIT, $permissionValues);
    $this->assertContains(Permission::IMPORT_MEMBERS_ACCESS, $permissionValues);
    $this->assertContains(Permission::EMAIL_TEMPLATE_EDIT, $permissionValues);
  }

  public function testAccessPermissionDoesNotGrantEdit(): void {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->loggedAsAdminClub1();

    // Grant only ACCESS permission
    $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_ACCESS->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Verify member has ACCESS but not EDIT
    $supervisorMember = _InitStory::MEMBER_supervisor_club_1();
    $this->assertTrue($supervisorMember->hasPermission(Permission::EMAIL_ACCESS));
    $this->assertFalse($supervisorMember->hasPermission(Permission::EMAIL_EDIT));
  }
}
