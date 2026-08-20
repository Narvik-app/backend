<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\MemberControlType;
use App\Service\MemberControlService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

/**
 * When a type is created (or its linked activity changes) with an activity already set,
 * backfill every member's control from their existing presence history.
 *
 * This dispatches a background job (see MemberControlService::dispatchSyncForType).
 */
#[AsEntityListener(event: 'postPersist', entity: MemberControlType::class)]
#[AsEntityListener(event: 'postUpdate', entity: MemberControlType::class)]
class MemberControlTypeSubscriber extends AbstractEventSubscriber {
  public function __construct(
    private readonly MemberControlService $memberControlService,
  ) {
  }

  public function postPersist(MemberControlType $type, PostPersistEventArgs $args): void {
    if ($type->isAutomatic()) {
      $this->memberControlService->dispatchSyncForType($type);
    }
  }

  public function postUpdate(MemberControlType $type, PostUpdateEventArgs $args): void {
    if ($this->isPropertyChanged($args->getObjectManager(), $type, 'activity') && $type->isAutomatic()) {
      $this->memberControlService->dispatchSyncForType($type);
    }
  }
}
