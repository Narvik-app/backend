<?php

namespace App\EventSubscriber\Kernel;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Enum\TimeAndTravelExportGenerationStatus;
use App\Message\TimeAndTravelExportRegenerateMessage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * The export's isRegenerating flag is only cleared by the regeneration job itself, so a job that
 * fails for good would leave the export "in progress" forever (and its buttons disabled). Once
 * the retries are exhausted, clear the flag and mark the export as failed, so the UI can offer to regenerate it.
 */
final readonly class TimeAndTravelExportMessengerSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      WorkerMessageFailedEvent::class => [
        ['messageFailed', 10],
      ],
    ];
  }

  public function __construct(
    private Connection $connection,
    private EntityManagerInterface $entityManager,
  ) {
  }

  public function messageFailed(WorkerMessageFailedEvent $event): void {
    $message = $event->getEnvelope()->getMessage();
    if ($event->willRetry() || !$message instanceof TimeAndTravelExportRegenerateMessage) {
      return;
    }

    // Plain SQL: the entity manager is closed when the failure came from a flush
    $table = $this->entityManager->getClassMetadata(TimeAndTravelExport::class)->getTableName();
    $this->connection->executeStatement(
      sprintf('UPDATE %s SET generation_status = :status WHERE uuid = :uuid', $this->connection->quoteSingleIdentifier($table)),
      ['status' => TimeAndTravelExportGenerationStatus::failed->value, 'uuid' => $message->getExportUuid()],
    );
  }
}
