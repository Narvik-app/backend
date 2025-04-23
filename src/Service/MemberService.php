<?php

namespace App\Service;

use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\Presence\Activity;
use App\Entity\User;
use App\Entity\UserMember;
use App\Enum\ClubRole;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubDependent\Plugin\Presence\ActivityRepository;
use App\Repository\ClubDependent\Plugin\Presence\MemberPresenceRepository;
use App\Repository\SeasonRepository;
use App\Repository\UserMemberRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class MemberService {
  public function __construct(
    private readonly MemberPresenceRepository $memberPresenceRepository,
    private readonly SeasonRepository $seasonRepository,
    private readonly MemberRepository $memberRepository,
    private readonly UserRepository $userRepository,
    private readonly UserMemberRepository $userMemberMemberRepository,
    private readonly EntityManagerInterface $entityManager
  ) {
  }

  public function setCurrentSeason(Member $member): void {
    $currentSeason = $this->seasonRepository->findCurrentSeason($member->getClub());
    if (!$currentSeason) return;

    foreach ($member->getMemberSeasons() as $season) {
      if ($season->getSeason() === $currentSeason) {
        $member->setCurrentSeason($season);
        break;
      }
    }
  }

  public function setLastControlShooting(Member $member, ?Activity $controlShootingActivity = null): void {
    if (!$controlShootingActivity) {
      return;
    }

    $presence = $this->memberPresenceRepository->findLastOneByActivity($member, $controlShootingActivity);
    if ($presence) {
      $member->setLastControlShooting($presence->getDate());
    }
  }

  public function autolinkMemberWithUser(Member $member, ClubRole $role = ClubRole::member): void {
    if ($member->isSkipAutoSetUserMember()) return;

    $email = $member->getEmail();
    if (empty($email)) return;

    $user = $this->userRepository->findOneByEmail($email);
    if (!$user) return;

    $userMember = $this->userMemberMemberRepository->findOneByMember($member);
    if ($userMember) {
      return;
    }

    // We create the link (with the lowest level)
    $userMember = new UserMember()
      ->setUser($user)
      ->setMember($member)
      ->setRole($role);

    $this->entityManager->persist($userMember);
    $this->entityManager->flush();
  }

  public function autolinkMemberFromUser(User $user): void {
    if ($user->isSkipAutoSetUserMember()) return;

    $email = $user->getEmail();
    if (empty($email)) return;

    $members = $this->memberRepository->findAllByEmail($email);

    foreach ($members as $member) {
      $userMember = $this->userMemberMemberRepository->findOneByMember($member);
      if ($userMember) {
        continue;
      }

      // We create the link (with the lowest level)
      $userMember = new UserMember()
        ->setUser($user)
        ->setMember($member)
        ->setRole(ClubRole::member);
      $this->entityManager->persist($userMember);
    }

    $this->entityManager->flush();
  }
}
