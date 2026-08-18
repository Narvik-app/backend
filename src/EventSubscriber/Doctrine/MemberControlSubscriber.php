<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\MemberControl;
use App\Repository\ClubDependent\Plugin\Presence\MemberPresenceRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

/**
 * For automatic (activity-linked) control types, the date is owned by presence sync
 * (see MemberControlService) — any client-submitted date is silently overwritten with the
 * real computed value rather than rejected, so unrelated edits (e.g. toggling alertDisabled)
 * on an already-dated automatic control never fail validation.
 */
#[AsEntityListener(entity: MemberControl::class, event: 'prePersist')]
#[AsEntityListener(entity: MemberControl::class, event: 'preUpdate')]
class MemberControlSubscriber extends AbstractEventSubscriber {
  public function __construct(
    private readonly MemberPresenceRepository $memberPresenceRepository,
  ) {
  }

  public function prePersist(MemberControl $control, PrePersistEventArgs $args): void {
    $this->enforceAutomaticDate($control);
  }

  public function preUpdate(MemberControl $control, PreUpdateEventArgs $args): void {
    $this->enforceAutomaticDate($control);
    if ($args->hasChangedField('date')) {
      $args->setNewValue('date', $control->getDate());
    }
  }

  private function enforceAutomaticDate(MemberControl $control): void {
    $type = $control->getType();
    if (!$type || !$type->isAutomatic()) {
      return;
    }

    $activity = $type->getActivity();
    $member = $control->getMember();
    if (!$member || !$activity) {
      return;
    }

    $presence = $this->memberPresenceRepository->findLastOneByActivity($member, $activity);
    $control->setDate($presence?->getDate());
  }
}
