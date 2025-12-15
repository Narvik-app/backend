<?php

namespace App\Tests\e2e\Entity;

use App\Entity\Season;
use App\Enum\ClubRole;
use App\Tests\e2e\Entity\Abstract\AbstractEntityApiTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\SeasonFactory;
use App\Tests\Story\SeasonStory;

class SeasonTest extends AbstractEntityApiTestCase {
  protected int $TOTAL_SUPER_ADMIN = 10;
  protected int $TOTAL_ADMIN_CLUB_1 = 10;
  protected int $TOTAL_ADMIN_CLUB_2 = 10;
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 10;
  protected int $TOTAL_MEMBER_CLUB_1 = 10;

  protected function getClassname(): string {
    return Season::class;
  }

  protected function getRootUrl(): string {
    return '/seasons';
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
    SeasonStory::load();
  }


  public function testCreate(): void {
    $payload = [
      "name" => '2010/2011',
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

  public function testPatch(): void {
    $season = SeasonStory::season_2019();
    $iri = $this->getIriFromResource($season);

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
        $season = SeasonFactory::createOne([
          "name" => '2018/2019',
        ]);
        $iri = $this->getIriFromResource($season);
        $this->makeDeleteRequest($iri);
      }
    );
  }

  public function testCreateSeasonWithSameName(): void {
    $this->loggedAsSuperAdmin();
    $this->makePostRequest($this->getRootUrl(), ['name' => '2024/2025']);
    $this->assertResponseStatusCodeSame(ResponseCodeEnum::unprocessable_422->value);
    $this->assertJsonContains([
      "detail" => "name: This value is already used.",
    ]);
  }
}
