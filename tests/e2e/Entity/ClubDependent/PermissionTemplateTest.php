<?php

namespace App\Tests\e2e\Entity\ClubDependent;

use App\Entity\ClubDependent\PermissionTemplate;
use App\Entity\Club;
use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Story\_InitStory;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests for PermissionTemplate entity
 */
class PermissionTemplateTest extends AbstractEntityClubLinkedTestCase {

  protected function getClassname(): string {
    return PermissionTemplate::class;
  }

  protected function getRootUrl(): string {
    throw new \Exception("Subresource! getRootUrl() must not be call.");
  }

  #[\Override]
  protected function getRootWClubUrl(Club $club): string {
    return $this->getTemplatesUrl($club);
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    return [
      UserRole::super_admin->value => true,
      ClubRole::admin->value => true,
      ClubRole::supervisor->value => true,
      ClubRole::member->value => false,
      ClubRole::badger->value => false,
    ];
  }

  #[\Override]
  public function initDefaultFixtures(): void {
    _InitStory::load();
  }

  private function getTemplatesUrl(Club $club, ?string $templateUuid = null): string {
    $clubUrl = $this->getIriFromResource($club);
    $url = "{$clubUrl}/permission-templates";
    if ($templateUuid) {
      $url .= "/{$templateUuid}";
    }
    return $url;
  }

  // Override collection tests to use correct URLs
  #[\Override]
  public function testGetCollectionAsAdminClub1(): ResponseInterface {
    $this->loggedAsAdminClub1();
    $club = _InitStory::club_1();
    $response = $this->makeGetRequest($this->getTemplatesUrl($club));
    $this->assertResponseIsSuccessful();
    return $response;
  }

  #[\Override]
  public function testGetCollectionAsAdminClub2(): ResponseInterface {
    $this->loggedAsAdminClub2();
    $club = _InitStory::club_1();
    $response = $this->makeGetRequest($this->getTemplatesUrl($club));
    $this->assertResponseIsClientError();
    return $response;
  }

  #[\Override]
  public function testGetCollectionAsSupervisorClub1(): ResponseInterface {
    $this->loggedAsSupervisorClub1();
    $club = _InitStory::club_1();
    $response = $this->makeGetRequest($this->getTemplatesUrl($club));
    $this->assertResponseIsSuccessful();
    return $response;
  }

  public function testCreate(): void {
    $club = _InitStory::club_1();
    $clubIri = $this->getIriFromResource($club);

    $this->loggedAsAdminClub1();
    $response = $this->makePostRequest($this->getTemplatesUrl($club), [
      'club' => $clubIri,
      'name' => 'Test Template',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    $data = $response->toArray();
    $this->assertEquals('Test Template', $data['name']);
    $this->assertArrayHasKey('uuid', $data);
  }

  public function testPatch(): void {
    $club = _InitStory::club_1();
    $this->loggedAsAdminClub1();

    // Create a template first
    $createResponse = $this->makePostRequest($this->getTemplatesUrl($club), [
      'club' => $this->getIriFromResource($club),
      'name' => 'Original Name',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);
    $templateUuid = $createResponse->toArray()['uuid'];

    // Patch it
    $this->makePatchRequest($this->getTemplatesUrl($club, $templateUuid), [
      'name' => 'Updated Name',
    ]);
    $this->assertResponseIsSuccessful();

    // Verify
    $getResponse = $this->makeGetRequest($this->getTemplatesUrl($club, $templateUuid));
    $data = $getResponse->toArray();
    $this->assertEquals('Updated Name', $data['name']);
  }

  public function testDelete(): void {
    $club = _InitStory::club_1();
    $this->loggedAsAdminClub1();

    // Create a template
    $createResponse = $this->makePostRequest($this->getTemplatesUrl($club), [
      'club' => $this->getIriFromResource($club),
      'name' => 'To Delete',
    ]);
    $templateUuid = $createResponse->toArray()['uuid'];

    // Delete it
    $this->makeDeleteRequest($this->getTemplatesUrl($club, $templateUuid));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::no_content->value);

    // Verify it's gone
    $this->makeGetRequest($this->getTemplatesUrl($club, $templateUuid));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::not_found->value);
  }

  public function testSupervisorCannotModifyTemplates(): void {
    $this->loggedAsSupervisorClub1();
    $club = _InitStory::club_1();

    $this->makePostRequest($this->getTemplatesUrl($club), [
      'club' => $this->getIriFromResource($club),
      'name' => 'Forbidden Template',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
  }

  public function testDuplicateTemplateNameReturnsError(): void {
    $club = _InitStory::club_1();
    $this->loggedAsAdminClub1();

    // Create first template
    $this->makePostRequest($this->getTemplatesUrl($club), [
      'club' => $this->getIriFromResource($club),
      'name' => 'Unique Name',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::created->value);

    // Try to create duplicate
    $this->makePostRequest($this->getTemplatesUrl($club), [
      'club' => $this->getIriFromResource($club),
      'name' => 'Unique Name',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);
  }

  public function testAssignTemplateToMember(): void {
    $club = _InitStory::club_1();
    $supervisor = _InitStory::MEMBER_supervisor_club_1();
    $supervisorIri = $this->getIriFromResource($supervisor);

    $this->loggedAsAdminClub1();

    // Create a template
    $createResponse = $this->makePostRequest($this->getTemplatesUrl($club), [
      'club' => $this->getIriFromResource($club),
      'name' => 'Assignable Template',
    ]);
    $templateData = $createResponse->toArray();
    $templateIri = $templateData['@id'];

    // Assign template to supervisor
    $this->makePatchRequest($supervisorIri, [
      'permissionTemplate' => $templateIri,
    ]);
    $this->assertResponseIsSuccessful();

    // Verify assignment
    $memberResponse = $this->makeGetRequest($supervisorIri);
    $memberData = $memberResponse->toArray();
    $this->assertNotNull($memberData['permissionTemplate']);
  }

  public function testAdminFromOtherClubCannotAccessTemplates(): void {
    $club1 = _InitStory::club_1();

    // Club 2 admin trying to access club 1 templates
    $this->loggedAsAdminClub2();
    $this->makeGetRequest($this->getTemplatesUrl($club1));
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);

    // Club 2 admin trying to create template in club 1
    $this->makePostRequest($this->getTemplatesUrl($club1), [
      'club' => $this->getIriFromResource($club1),
      'name' => 'Cross Club Template',
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::forbidden->value);
  }
}
