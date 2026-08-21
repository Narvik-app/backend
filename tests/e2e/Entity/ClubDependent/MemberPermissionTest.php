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
      ClubRole::supervisor->value => true,
      ClubRole::member->value => false,
      ClubRole::badger->value => false,
    ];
  }

  /**
   * Get the permissions collection URL for a member (for GET collection and POST)
   */
  private function getMemberPermissionsUrl(Member $member): string {
    $memberUrl = $this->getIriFromResource($member);
    return "{$memberUrl}/permissions";
  }

  /**
   * Get the generic permission item URL (for GET item and DELETE)
   * Uses the route: /clubs/{clubUuid}/permissions/{uuid}
   */
  private function getPermissionItemUrl(Club $club, string $permissionUuid): string {
    $clubUrl = $this->getIriFromResource($club);
    return "{$clubUrl}/permissions/{$permissionUuid}";
  }

  // ============ Collection Test Overrides ============
  // Permissions is a member subresource - cross-club checks don't apply the same way
  // The parent tests call getRootWClubUrl(club_2) to test denial, but we always use club 1 member
  // Cross-club access denial is tested in testAdminFromOtherClubCannotModifyPermissions

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
    // Club 2 admin accessing club 1 member's permissions should fail
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $response = $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
    $this->assertResponseIsClientError();
    return $response;
  }

  #[\Override]
  public function testGetCollectionAsSupervisorClub1(): ResponseInterface {
    $this->loggedAsSupervisorClub1();
    // Supervisors can view permissions (read-only)
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $response = $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
    $this->assertResponseIsSuccessful();
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

  #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function testPatch(): void {
    // MemberPermission doesn't support PATCH - permissions are granted/revoked, not edited
    $this->markTestSkipped('MemberPermission does not support PATCH operation');
  }

  public function testDelete(): void {
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $club = _InitStory::club_1();
    $memberIri = $this->getIriFromResource($supervisor);

    $this->loggedAsAdminClub1();

    // First grant a permission
    $createResponse = $this->makePostRequest($this->getMemberPermissionsUrl($supervisor), [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
    $permissionUuid = $createResponse->toArray()['uuid'];

    // Then revoke it using generic item URL
    $this->makeDeleteRequest($this->getPermissionItemUrl($club, $permissionUuid));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::no_content->value);

    // Verify implied permission EMAIL_ACCESS remains
    $listResponse = $this->makeGetRequest($this->getMemberPermissionsUrl($supervisor));
    $data = $listResponse->toArray();
    $this->assertCount(1, $data['member']);
    $this->assertEquals(Permission::EMAIL_ACCESS->value, $data['member'][0]['permission']);
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
    // Verify implied permission is also present
    $this->assertContains(Permission::EMAIL_ACCESS->value, $profile['permissions']);
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
    // 3 explicit permissions + 2 implied (EMAIL_EDIT->EMAIL_ACCESS, EMAIL_TEMPLATE_EDIT->EMAIL_TEMPLATE_ACCESS)
    $this->assertCount(5, $data['member']);

    // Verify member has permissions (based on API response)
    $permissionValues = array_column($data['member'], 'permission');
    $this->assertCount(5, $permissionValues);
    $this->assertContains(Permission::EMAIL_EDIT->value, $permissionValues);
    $this->assertContains(Permission::EMAIL_ACCESS->value, $permissionValues);
    $this->assertContains(Permission::IMPORT_MEMBERS_ACCESS->value, $permissionValues);
    $this->assertContains(Permission::EMAIL_TEMPLATE_EDIT->value, $permissionValues);
    $this->assertContains(Permission::EMAIL_TEMPLATE_ACCESS->value, $permissionValues);
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
    $this->assertFalse($supervisorMember->hasPermission(Permission::EMAIL_EDIT));
  }

  public function testCannotRemoveImpliedPermissionWhileParentIsActive(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $memberIri = $this->getIriFromResource($supervisor);
    $clubIri = $this->getIriFromResource($club);

    $this->loggedAsAdminClub1();

    // Grant EMAIL_EDIT (implies EMAIL_ACCESS)
    $this->makePostRequest($memberIri . '/permissions', [
      'member' => $memberIri,
      'permission' => Permission::EMAIL_EDIT->value,
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Get the EMAIL_ACCESS permission UUID
    $response = $this->makeGetRequest($memberIri . '/permissions');
    $permissions = $response->toArray()['member'];
    $accessPermission = array_filter($permissions, fn($p) => $p['permission'] === Permission::EMAIL_ACCESS->value);
    $accessPermission = array_first($accessPermission);

    // Try to delete EMAIL_ACCESS - should fail
    $this->makeDeleteRequest($clubIri . '/permissions/' . $accessPermission['uuid']);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      'detail' => 'Unable to remove this permission because \'EMAIL_EDIT\' is enabled and requires it.',
    ]);
  }
}
