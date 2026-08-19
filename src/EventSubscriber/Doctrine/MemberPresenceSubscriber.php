<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Service\MemberControlService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(entity: MemberPresence::class, event: 'postPersist')]
#[AsEntityListener(entity: MemberPresence::class, event: 'postUpdate')]
#[AsEntityListener(entity: MemberPresence::class, event: 'postRemove')]
#[AsDoctrineListener(event: Events::postFlush)]
class MemberPresenceSubscriber extends AbstractEventSubscriber {
  /** @var array<int, Member> */
  private array $pendingMembers = [];

  public function __construct(
    private readonly MemberControlService $memberControlService,
  ) {
  }

  public function postPersist(MemberPresence $memberPresence, PostPersistEventArgs $args): void {
    $this->queue($memberPresence);
  }

  public function postUpdate(MemberPresence $memberPresence, PostUpdateEventArgs $args): void {
    $this->queue($memberPresence);
  }

  public function postRemove(MemberPresence $memberPresence, PostRemoveEventArgs $args): void {
    $this->queue($memberPresence);
  }

  /**
   * The `activities` many-to-many join-table rows are written during Doctrine's collection-update
   * phase, which runs AFTER postPersist/postUpdate/postRemove.
   * Doing it in postFlush is the only way.
   */
  public function postFlush(PostFlushEventArgs $args): void {
    if (empty($this->pendingMembers)) {
      return;
    }

    $members = $this->pendingMembers;
    $this->pendingMembers = [];

    foreach ($members as $member) {
      $this->memberControlService->syncAutoControls($member);
    }
  }

  private function queue(MemberPresence $memberPresence): void {
    $member = $memberPresence->getMember();
    if ($member) {
      $this->pendingMembers[$member->getId()] = $member;
    }
  }
}
