<?php

namespace App\Tests\e2e\Entity\ClubDependent;

use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\MemberSeason;
use App\Enum\ClubRole;
use App\Tests\e2e\Entity\Abstract\AbstractEntityClubLinkedTestCase;
use App\Tests\Enum\ResponseCodeEnum;
use App\Tests\Factory\MemberSeasonFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\SeasonStory;

class MemberSeasonTest extends AbstractEntityClubLinkedTestCase {

  #[\Override]
  protected int $TOTAL_SUPER_ADMIN = 1;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_1 = 1;
  #[\Override]
  protected int $TOTAL_ADMIN_CLUB_2 = 0; // Since we are forced on member club 1
  #[\Override]
  protected int $TOTAL_SUPERVISOR_CLUB_1 = 1;
  #[\Override]
  protected int $TOTAL_MEMBER_CLUB_1 = 1;

  private Member $selectedMember;

  #[\Override]
  public function setUp(): void {
    parent::setUp();
    $this->selectedMember = _InitStory::MEMBER_member_club_1();
  }

  protected function getClassname(): string {
    return MemberSeason::class;
  }

  protected function getRootUrl(): string {
    // The "root url" is specific since it's a sub-resource of member
    // This should never be call
    throw new \Exception("Subresource! getRootUrl() must not be call.");
  }

  #[\Override]
  protected function getRootWClubUrl(Club $club): string {
    return $this->getIriFromResource($club) . "/members/{$this->selectedMember->getUuid()}/seasons";
  }

  #[\Override]
  protected function getCollectionGrantedAccess() : array {
    $access = parent::getCollectionGrantedAccess();
    $access[ClubRole::member->value] = true;
    return $access;
  }

  public function testCreate(): void {
    $club1 = _InitStory::club_1();

    $count = 2015;
    $this->makeAllLoggedRequests(
      $payloadCheck,
      supervisorClub1Code: ResponseCodeEnum::created,
      adminClub1Code: ResponseCodeEnum::created,
      superAdminCode: ResponseCodeEnum::created,
      requestFunction: function (string $level, ?int $id) use ($club1, &$payloadCheck, &$count) {
        // We update for each test since the name must be unique
        $sn = "season_" . $count;
        $season = SeasonStory::$sn();

        $payload = [
          "member" => $this->getIriFromResource($this->selectedMember),
          "season" => $this->getIriFromResource($season),
        ];

        $payloadCheck = $payload;
        $payloadCheck["member"] = [
          "@id" => $this->getIriFromResource($this->selectedMember),
        ];
        $payloadCheck["season"] = [
          "@id" => $this->getIriFromResource($season),
        ];

        $this->makePostRequest($this->getRootWClubUrl($club1), $payload);
        $count++;
      },
    );
  }

  public function testPatch(): void {
    // Patch is not enabled
    $memberSeason = MemberSeasonFactory::random();
    $iri = $this->getIriFromResource($memberSeason);

    $this->makeAllLoggedRequests(
      memberClub1Code: ResponseCodeEnum::not_allowed,
      supervisorClub1Code: ResponseCodeEnum::not_allowed,
      adminClub1Code: ResponseCodeEnum::not_allowed,
      adminClub2Code: ResponseCodeEnum::not_allowed,
      superAdminCode: ResponseCodeEnum::not_allowed,
      badgerClub1Code: ResponseCodeEnum::not_allowed,
      badgerClub2Code: ResponseCodeEnum::not_allowed,
      requestFunction: function (string $level, ?int $id) use ($iri) {
        $this->makePatchRequest($iri);
      },
    );
  }

  public function testDelete(): void {
    $this->makeAllLoggedRequests(
      supervisorClub1Code: ResponseCodeEnum::no_content,
      adminClub1Code: ResponseCodeEnum::no_content,
      superAdminCode: ResponseCodeEnum::no_content,
      requestFunction: function (string $level, ?int $id) {
        $memberSeason = MemberSeasonFactory::createOne([
          'member' => $this->selectedMember,
        ]);
        $iri = $this->getIriFromResource($memberSeason);
        $this->makeDeleteRequest($iri);
      },
    );
  }
}
