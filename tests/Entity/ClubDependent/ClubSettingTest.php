<?php

namespace App\Tests\Entity\ClubDependent;

use App\Entity\Club;
use App\Entity\ClubDependent\ClubSetting;
use App\Entity\ClubDependent\Member;
use App\Enum\ClubRole;
use App\Message\ItacMembersMessage;
use App\Message\ItacSecondaryClubMembersMessage;
use App\Tests\AbstractTestCase;
use App\Tests\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\MemberFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\FixtureFileManager;
use App\Tests\Story\_InitStory;

class ClubSettingTest extends AbstractTestCase {

  protected function getClassname(): string {
    return ClubSetting::class;
  }

  private function getRootUrl(Club $club): string {
    if (!$club->getSettings()) return '';
    return $this->getIriFromResource($club->getSettings());
  }

  public function testImportLogo(): void {
    $club = _InitStory::club_1();

    $this->loggedAsAdminClub1();
    $response = $this->makeGetRequest($this->getRootUrl($club));
    $this->assertResponseIsSuccessful();
    $this->assertMatchesResourceItemJsonSchema($this->getClassname());
    $this->assertJsonNotHasKey("logo", $response);

    // We upload the logo
    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::PROFILE_PICTURES);
    $this->makePostRequest($this->getRootUrl($club) . "/logo", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::bad_request->value);
    $this->assertJsonContains([
      "detail" => "The \"file\" must be an image (png, jpg, webp).",
    ]);

    $file = FixtureFileManager::getUploadedFile(FixtureFileManager::LOGO, true);
    $this->makePostRequest($this->getRootUrl($club) . "/logo", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
        'files' => [
          'file' => $file,
        ],
      ],
    ]);
    // FixtureFileManager::removeUploadedFile(FixtureFileManager::LOGO); // Not needed since the upload controller will move it
    $this->assertResponseIsSuccessful();

    $response = $this->makeGetRequest($this->getRootUrl($club));
    $this->assertResponseIsSuccessful();
    $this->assertMatchesResourceItemJsonSchema($this->getClassname());
    $responseArray = $response->toArray();
    $this->assertArrayHasKey("logo", $responseArray);
    $this->assertArrayHasKey("publicUrl", $responseArray["logo"]);

    // Logo is a public image
    $this->logout();
    $this->makeGetRequest($responseArray["logo"]['publicUrl']);
    $this->assertResponseIsSuccessful();


    // We remove the logo
    $this->loggedAsAdminClub1();
    $this->makePostRequest($this->getRootUrl($club) . "/logo", [
      '_not_json' => true,
      'headers' => ['Content-Type' => 'multipart/form-data'],
      'extra' => [
      ],
    ]);
    $this->assertResponseIsSuccessful();

    $response = $this->makeGetRequest($this->getRootUrl($club));
    $this->assertResponseIsSuccessful();
    $responseArray = $response->toArray();
    $this->assertArrayNotHasKey("logo", $responseArray);
  }

  public function testUpdateSeasonEnd(): void {
    $club = _InitStory::club_1();
    $clubUrl = $this->getRootUrl($club);

    $this->loggedAsAdminClub1();

    $this->makeGetRequest($clubUrl);
    $this->assertJsonContains([
      "seasonEnd" => "31-12",
    ]);

    // We update it to an invalid format
    $this->makePatchRequest($clubUrl, ['seasonEnd' => '31/12']);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);
    $this->assertJsonContains([
      "detail" => "seasonEnd: This value is not valid.",
    ]);

    // We don't set the 0, should still set it correctly
    $this->makePatchRequest($clubUrl, ['seasonEnd' => '1-2']);
    $this->assertResponseIsSuccessful();
    $this->assertJsonContains([
      "seasonEnd" => "01-02",
    ]);
  }
}
