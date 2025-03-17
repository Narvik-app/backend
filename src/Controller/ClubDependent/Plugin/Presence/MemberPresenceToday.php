<?php

namespace App\Controller\ClubDependent\Plugin\Presence;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Repository\ClubDependent\Plugin\Presence\MemberPresenceRepository;
use App\Service\GlobalSettingService;
use App\Service\MemberService;
use Symfony\Component\HttpFoundation\Request;

class MemberPresenceToday extends AbstractClubDependentController {

  public function __invoke(Request $request, MemberPresenceRepository $memberPresenceRepository, MemberService $memberService, GlobalSettingService $globalSettingService): ?array {

    /** @var MemberPresence[] $todayPresentMembers */
    $todayPresentMembers = $memberPresenceRepository->findAllPresentToday($this->getClub($request));

    $controlShootingActivity = $this->getClub($request)->getSettings()?->getControlShootingActivity();

    foreach ($todayPresentMembers as $memberPresence) {
      if (!$memberPresence->getMember()) {
        continue;
      }

      $memberService->setCurrentSeason($memberPresence->getMember());
      if ($controlShootingActivity) {
        $memberService->setLastControlShooting($memberPresence->getMember(), $controlShootingActivity);
      }
    }

    return $todayPresentMembers;
  }

}
