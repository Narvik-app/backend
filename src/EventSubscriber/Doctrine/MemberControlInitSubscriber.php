<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Member;
use App\Service\MemberControlService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

/**
 * A newly created member gets a control row for every type flagged "alert if missing",
 * so the missing date is flagged right away.
 */
#[AsEntityListener(event: 'postPersist', entity: Member::class)]
#[AsDoctrineListener(event: Events::postFlush)]
class MemberControlInitSubscriber extends AbstractEventSubscriber {
  /** @var Member[] */
  private array $pendingMembers = [];

  public function __construct(
    private readonly MemberControlService $memberControlService,
  ) {
  }

  public function postPersist(Member $member, PostPersistEventArgs $args): void {
    $this->pendingMembers[] = $member;
  }

  // Rows can't be persisted from within postPersist: wait for the end of the flush.
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
}
