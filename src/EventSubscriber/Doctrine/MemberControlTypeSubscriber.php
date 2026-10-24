<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\MemberControlType;
use App\Service\MemberControlService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

/**
 * When a type is created (or its linked activity / "alert if missing" flag changes) and the backend
 * must maintain its rows, backfill every member's control (from presence history for automatic types).
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
    if ($type->isSynced()) {
      $this->memberControlService->dispatchSyncForType($type);
    }
  }

  public function postUpdate(MemberControlType $type, PostUpdateEventArgs $args): void {
    if ($this->hasChangedProperties($args->getObjectManager(), $type, ['activity', 'alertIfMissing']) && $type->isSynced()) {
      $this->memberControlService->dispatchSyncForType($type);
    }
  }
}
