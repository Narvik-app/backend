<?php

namespace App\MessageHandler;

use App\Message\MemberControlSyncMessage;
use App\Repository\ClubDependent\MemberControlTypeRepository;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubRepository;
use App\Service\MemberControlService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class MemberControlSyncMessageHandler {
  public function __construct(
    private readonly ClubRepository $clubRepository,
    private readonly MemberControlTypeRepository $memberControlTypeRepository,
    private readonly MemberRepository $memberRepository,
    private readonly MemberControlService $memberControlService,
    private readonly EntityManagerInterface $entityManager,
  ) {
  }

  public function __invoke(MemberControlSyncMessage $message): void {
    $club = $this->clubRepository->findOneByUuid($message->getClubUuid());
    if (!$club) {
      return;
    }

    $type = $this->memberControlTypeRepository->findOneByUuid($message->getTypeUuid());
    if (!$type) {
      return;
    }

    $members = $this->memberRepository->findAllByUuids($club, $message->getMemberUuids());
    foreach ($members as $member) {
      $this->memberControlService->syncMemberForType($member, $type);
    }

    $this->entityManager->flush();
  }
}
