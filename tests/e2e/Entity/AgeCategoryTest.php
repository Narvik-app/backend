<?php

namespace App\Tests\e2e\Entity;

use App\Entity\AgeCategory;
use App\Enum\ClubRole;
use App\Tests\e2e\Entity\Abstract\AbstractEntityApiTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\AgeCategoryFactory;
use App\Tests\Story\AgeCategoryStory;

class AgeCategoryTest extends AbstractEntityApiTestCase {
  protected int $TOTAL_SUPER_ADMIN = 16;
  protected int $TOTAL_ADMIN_CLUB_1 = 16;
  protected int $TOTAL_ADMIN_CLUB_2 = 16;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 16;
  protected int $TOTAL_MEMBER_CLUB_1 = 16;

  protected function getClassname(): string {
    return AgeCategory::class;
  }

  protected function getRootUrl(): string {
    return '/age-categories';
  }

  #[\Override]
  protected function getCollectionGrantedAccess(): array {
    $access = parent::getCollectionGrantedAccess();
    $access[ClubRole::admin->value] = true;
    $access[ClubRole::supervisor->value] = true;

    $access[ClubRole::member->value] = true;
    return $access;
  }

  public function initDefaultFixtures(): void {
    AgeCategoryStory::load();
  }


  public function testCreate(): void {
    $payload = [
      "name" => 'Dame 1',
      "code" => 'D1',
    ];

    // Only super admin can create
    $this->makeAllLoggedRequests(
      $payload,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::created,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use ($payload) {
        $this->makePostRequest($this->getRootUrl(), $payload);
      }
    );

  }

  public function testPatch(): void {
    $ageCategory = AgeCategoryStory::getRandom('age_categories');
    $iri = $this->getIriFromResource($ageCategory);

    $payload = [
      "name" => 'Updated',
    ];

    // Only super admin can update
    $this->makeAllLoggedRequests(
      $payload,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      ResponseCodeEnum::forbidden,
      badgerClub2Code: ResponseCodeEnum::forbidden,
      requestFunction: function (string $level, ?int $id) use ($iri, $payload) {
        $this->makePatchRequest($iri, $payload);
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
        $ageCategory = AgeCategoryFactory::createOne([
          "name" => 'Dame 1',
          "code" => 'D1',
        ]);
        $iri = $this->getIriFromResource($ageCategory);
        $this->makeDeleteRequest($iri);
      }
    );
  }
}
