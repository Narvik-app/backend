<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Member;
use App\Service\MemberService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;

#[AsEntityListener(entity: Member::class)]
class MemberSubscriber extends AbstractEventSubscriber {
  public function __construct(
    private readonly MemberService $memberService,
    private readonly EntityManagerInterface $entityManager,
  ) {
  }

  public function postLoad(Member $member, PostLoadEventArgs $args): void {
    $this->memberService->setCurrentSeason($member);

    $controlShootingActivity = $member->getClub()?->getSettings()?->getControlShootingActivity();
    $this->memberService->setLastControlShooting($member, $controlShootingActivity);
  }

  public function postPersist(Member $member, PostPersistEventArgs $args): void {
    $this->memberService->autolinkMemberWithUser($member);
  }

  public function preRemove(Member $member, PreRemoveEventArgs $args): void {
    $image = $member->getProfileImage();
    if ($image) {
      $this->entityManager->initializeObject($image);
      $this->entityManager->remove($image);
    }
  }
}
