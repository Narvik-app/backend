<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\MemberControl;
use App\Repository\ClubDependent\Plugin\Presence\MemberPresenceRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

#[AsEntityListener(event: 'prePersist', entity: MemberControl::class)]
#[AsEntityListener(event: 'preUpdate', entity: MemberControl::class)]
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
