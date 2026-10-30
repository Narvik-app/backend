<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Enum\TimeAndTravelExportGenerationStatus;
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
  /**
   * Per export, the members whose declarations changed — only their attestations get regenerated.
   * A null member list means "unknown", i.e. the whole export is regenerated.
   *
   * @var array<int, array{export: TimeAndTravelExport, memberUuids: array<string, true>|null}>
   */
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
        $this->queueExport($this->exportRepository->findDraftCoveringDate($declaration->getClub(), $oldDate), $declaration->getMember());
      }
    }

    // Reassigned to another member: the previous one's attestation loses it too
    if (array_key_exists('member', $changedProperties) && $changedProperties['member'][0] instanceof Member) {
      $this->queueCoveringExport($declaration, $changedProperties['member'][0]);
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

    foreach ($exports as $pending) {
      $pending['export']->setGenerationStatus(TimeAndTravelExportGenerationStatus::generating);
    }
    $this->entityManager->flush();

    foreach ($exports as $pending) {
      $this->messageBus->dispatch(new TimeAndTravelExportRegenerateMessage(
        $pending['export']->getUuid()->toString(),
        memberUuids: $pending['memberUuids'] === null ? [] : array_keys($pending['memberUuids']),
      ));
    }
  }

  private function queueCoveringExport(TimeAndTravelDeclaration $declaration, ?Member $member = null): void {
    $club = $declaration->getClub();
    $date = $declaration->getDate();
    if (!$club || !$date) {
      return;
    }

    $member ??= $declaration->getMember();
    $this->queueExport($this->exportRepository->findDraftCoveringDate($club, $date), $member);

    // Already attached to a (still draft) export — e.g. a non-date field changed on a declaration
    // that's inside the period but the lookup above already found the same one; harmless either way.
    $attachedExport = $declaration->getExport();
    if ($attachedExport && $attachedExport->getStatus() === TimeAndTravelExportStatus::draft) {
      $this->queueExport($attachedExport, $member);
    }
  }

  private function queueExport(?TimeAndTravelExport $export, ?Member $member): void {
    if (!$export) {
      return;
    }

    $pending = $this->pendingExports[$export->getId()] ?? ['export' => $export, 'memberUuids' => []];
    if ($member === null || $pending['memberUuids'] === null) {
      $pending['memberUuids'] = null;
    } else {
      $pending['memberUuids'][$member->getUuid()->toString()] = true;
    }
    $this->pendingExports[$export->getId()] = $pending;
  }
}
