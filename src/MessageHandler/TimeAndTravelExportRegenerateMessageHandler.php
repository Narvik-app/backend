<?php

namespace App\MessageHandler;

use App\Enum\TimeAndTravelExportStatus;
use App\Message\TimeAndTravelExportRegenerateMessage;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportRepository;
use App\Service\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TimeAndTravelExportRegenerateMessageHandler {
  public function __construct(
    private readonly TimeAndTravelExportRepository $exportRepository,
    private readonly MemberRepository $memberRepository,
    private readonly TimeAndTravelExportGenerationService $generationService,
    private readonly EntityManagerInterface $entityManager,
  ) {
  }

  public function __invoke(TimeAndTravelExportRegenerateMessage $message): void {
    $export = $this->exportRepository->findOneByUuid($message->getExportUuid());
    // The export may have been deleted, or already locked/unlocked by the time this runs — in
    // either case there's nothing left to (re)generate here.
    if (!$export || $export->getStatus() !== TimeAndTravelExportStatus::draft) {
      return;
    }

    $this->generationService->regenerate($export);

    if ($message->isLockAfter()) {
      $lockedBy = $message->getLockedByMemberUuid() ? $this->memberRepository->findOneByUuid($message->getLockedByMemberUuid()) : null;
      $export
        ->setStatus(TimeAndTravelExportStatus::locked)
        ->setLockedAt(new \DateTimeImmutable())
        ->setLockedBy($lockedBy);
    }

    $export->setIsRegenerating(false);
    $this->entityManager->flush();
  }
}
