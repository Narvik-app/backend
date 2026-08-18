<?php

namespace App\Service;

use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\MemberControl;
use App\Entity\ClubDependent\MemberControlType;
use App\Repository\ClubDependent\MemberControlRepository;
use App\Repository\ClubDependent\MemberControlTypeRepository;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubDependent\Plugin\Presence\MemberPresenceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps automatic (activity-linked) MemberControl rows in sync with member presences,
 * so the "last control" date is stored instead of recomputed on every read.
 */
class MemberControlService {
  public function __construct(
    private readonly MemberPresenceRepository $memberPresenceRepository,
    private readonly MemberControlRepository $memberControlRepository,
    private readonly MemberControlTypeRepository $memberControlTypeRepository,
    private readonly MemberRepository $memberRepository,
    private readonly EntityManagerInterface $entityManager,
  ) {
  }

  /**
   * Refresh every automatic control of a single member (e.g. after one of their presences changed).
   */
  public function syncAutoControls(Member $member): void {
    $club = $member->getClub();
    if (!$club) {
      return;
    }

    $changed = false;
    foreach ($this->memberControlTypeRepository->findAllAutomaticByClub($club) as $type) {
      $changed = $this->syncMemberForType($member, $type) || $changed;
    }

    if ($changed) {
      $this->entityManager->flush();
    }
  }

  /**
   * Full rebuild of one automatic type across the whole club (e.g. after its linked activity changed).
   */
  public function syncForType(MemberControlType $type): void {
    if (!$type->isAutomatic()) {
      return;
    }

    $club = $type->getClub();
    if (!$club) {
      return;
    }

    $changed = false;
    foreach ($this->memberRepository->findAllByClub($club) as $member) {
      $changed = $this->syncMemberForType($member, $type) || $changed;
    }

    if ($changed) {
      $this->entityManager->flush();
    }
  }

  private function syncMemberForType(Member $member, MemberControlType $type): bool {
    $activity = $type->getActivity();
    if (!$activity) {
      return false;
    }

    $presence = $this->memberPresenceRepository->findLastOneByActivity($member, $activity);
    $control = $this->memberControlRepository->findOneByMemberAndType($member, $type);
    $newDate = $presence?->getDate();

    if (!$control) {
      if (!$newDate) {
        return false;
      }
      $control = new MemberControl();
      $control->setMember($member)->setType($type);
      $this->entityManager->persist($control);
      $control->setDate($newDate);
      return true;
    }

    if ($control->getDate() != $newDate) {
      $control->setDate($newDate);
      return true;
    }

    return false;
  }
}
