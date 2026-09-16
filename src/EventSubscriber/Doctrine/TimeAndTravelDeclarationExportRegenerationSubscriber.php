<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Enum\TimeAndTravelExportStatus;
use App\Message\TimeAndTravelExportRegenerateMessage;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A declaration can be freely added/edited/removed while its period's export is still a draft
 * (see TimeAndTravelExportRepository::existsCoveringDate) — this keeps that draft's PDFs in sync
 * automatically instead of relying on someone remembering to click "Régénérer".
 *
 * Follows MemberPresenceSubscriber's shape: entities are queued during the write events and the
 * actual side effect (flagging + dispatching) happens in postFlush, once Doctrine's own flush has
 * safely completed — doing it inline would corrupt the in-progress UnitOfWork.
 */
#[AsEntityListener(event: 'postPersist', entity: TimeAndTravelDeclaration::class)]
#[AsEntityListener(event: 'postUpdate', entity: TimeAndTravelDeclaration::class)]
#[AsEntityListener(event: 'postRemove', entity: TimeAndTravelDeclaration::class)]
#[AsDoctrineListener(event: Events::postFlush)]
class TimeAndTravelDeclarationExportRegenerationSubscriber extends AbstractEventSubscriber {
  /** @var array<int, TimeAndTravelExport> */
  private array $pendingExports = [];

  public function __construct(
    private readonly TimeAndTravelExportRepository $exportRepository,
    private readonly MessageBusInterface $messageBus,
    private readonly EntityManagerInterface $entityManager,
  ) {
  }

  public function postPersist(TimeAndTravelDeclaration $declaration, PostPersistEventArgs $args): void {
    $this->queueCoveringExport($declaration);
  }

  public function postUpdate(TimeAndTravelDeclaration $declaration, PostUpdateEventArgs $args): void {
    // Regenerating itself reassigns `export` (detach then reattach) with nothing else in the
    // changeset — without this guard, that self-inflicted update would dispatch another
    // regeneration forever.
    if ($this->hasOnlyChangedProperties($args->getObjectManager(), $declaration, ['export'])) {
      return;
    }

    $this->queueCoveringExport($declaration);

    $changedProperties = $this->getChangedProperties($args->getObjectManager(), $declaration);
    if (array_key_exists('date', $changedProperties)) {
      $oldDate = $changedProperties['date'][0] ?? null;
      if ($oldDate instanceof \DateTimeImmutable && $declaration->getClub()) {
        $this->queueExport($this->exportRepository->findDraftCoveringDate($declaration->getClub(), $oldDate));
      }
    }
  }

  public function postRemove(TimeAndTravelDeclaration $declaration, PostRemoveEventArgs $args): void {
    $this->queueCoveringExport($declaration);
  }

  public function postFlush(PostFlushEventArgs $args): void {
    if (empty($this->pendingExports)) {
      return;
    }

    $exports = $this->pendingExports;
    $this->pendingExports = [];

    foreach ($exports as $export) {
      $export->setIsRegenerating(true);
    }
    $this->entityManager->flush();

    foreach ($exports as $export) {
      $this->messageBus->dispatch(new TimeAndTravelExportRegenerateMessage($export->getUuid()->toString()));
    }
  }

  private function queueCoveringExport(TimeAndTravelDeclaration $declaration): void {
    $club = $declaration->getClub();
    $date = $declaration->getDate();
    if (!$club || !$date) {
      return;
    }

    $this->queueExport($this->exportRepository->findDraftCoveringDate($club, $date));

    // Already attached to a (still draft) export — e.g. a non-date field changed on a declaration
    // that's inside the period but the lookup above already found the same one; harmless either way.
    $attachedExport = $declaration->getExport();
    if ($attachedExport && $attachedExport->getStatus() === TimeAndTravelExportStatus::draft) {
      $this->queueExport($attachedExport);
    }
  }

  private function queueExport(?TimeAndTravelExport $export): void {
    if ($export) {
      $this->pendingExports[$export->getId()] = $export;
    }
  }
}
