<?php

namespace App\Tests\Entity;

use App\Controller\GlobalSettingGetPublic;
use App\Entity\GlobalSetting;
use App\Tests\Entity\Abstract\AbstractEntityTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Story\GlobalSettingStory;

class GlobalSettingTest extends AbstractEntityTestCase {
  protected int $TOTAL_SUPER_ADMIN = 8;

  protected function getClassname(): string {
    return GlobalSetting::class;
  }

  protected function getRootUrl(): string {
    return '/global-settings';
  }

  public function initDefaultFixtures(): void {
    GlobalSettingStory::load();
  }


  public function testCreate(): void {
    // No API creation possible
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) {
        $iri = $this->getRootUrl();
        $this->makePostRequest($iri);
      },
    );
  }

  public function testPatch(): void {
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::forbidden,
      supervisorClub1Code: ResponseCodeEnum::forbidden,
      adminClub1Code: ResponseCodeEnum::forbidden,
      adminClub2Code: ResponseCodeEnum::forbidden,
      superAdminCode: ResponseCodeEnum::ok,
      badgerClub1Code: ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) {
        $iri = $this->getRootUrl() . "/SMTP_HOST";
        $this->makePatchRequest($iri, ['test']);
      },
    );
  }

  public function testDelete(): void {
    // No API deletion possible
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) {
        $iri = $this->getRootUrl() . "/SMTP_HOST";
        $this->makeDeleteRequest($iri);
      },
    );
  }

  public function testPublicSettingsAreVisible(): void {
    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::ok,
      supervisorClub1Code: ResponseCodeEnum::ok,
      adminClub1Code: ResponseCodeEnum::ok,
      adminClub2Code: ResponseCodeEnum::ok,
      superAdminCode: ResponseCodeEnum::ok,
      badgerClub1Code: ResponseCodeEnum::ok,
      badgerClub2Code: ResponseCodeEnum::ok,
      requestFunction: function (string $level, ?int $id) {
        // A private one
        $iri = "/public" . $this->getRootUrl() . "/SMTP_HOST";
        $this->makeGetRequest($iri);
        $this->assertResponseStatusCodeSame(ResponseCodeEnum::not_found->value);

        foreach (GlobalSettingGetPublic::AVAILABLE_PUBLICLY as $item) {
          $this->makeGetRequest("/public" . $this->getRootUrl() . "/$item");
        }
      },
    );
  }
}
