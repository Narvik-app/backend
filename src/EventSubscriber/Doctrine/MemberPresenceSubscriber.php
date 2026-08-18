<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Service\MemberControlService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

#[AsEntityListener(entity: MemberPresence::class, event: 'postPersist')]
#[AsEntityListener(entity: MemberPresence::class, event: 'postUpdate')]
#[AsEntityListener(entity: MemberPresence::class, event: 'postRemove')]
class MemberPresenceSubscriber extends AbstractEventSubscriber {
  public function __construct(
    private readonly MemberControlService $memberControlService,
  ) {
  }

  public function postPersist(MemberPresence $memberPresence, PostPersistEventArgs $args): void {
    $this->sync($memberPresence);
  }

  public function postUpdate(MemberPresence $memberPresence, PostUpdateEventArgs $args): void {
    $this->sync($memberPresence);
  }

  public function postRemove(MemberPresence $memberPresence, PostRemoveEventArgs $args): void {
    $this->sync($memberPresence);
  }

  private function sync(MemberPresence $memberPresence): void {
    $member = $memberPresence->getMember();
    if (!$member) {
      return;
    }

    $this->memberControlService->syncAutoControls($member);
  }
}
