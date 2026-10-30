<?php

namespace App\EventSubscriber\Kernel;

use App\Message\Abstract\ClubLinkedMessage;
use App\Service\ClubService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

final readonly class MessengerSubscriber
{
    public function __construct(
      private ClubService $clubService,
    ) {
    }
    #[AsEventListener(event: WorkerMessageHandledEvent::class, priority: 10)]
    public function messageHandled(WorkerMessageHandledEvent $event): void {
      $message = $event->getEnvelope()->getMessage();
      $this->recordJobResult($message, true);
    }
    #[AsEventListener(event: WorkerMessageFailedEvent::class, priority: 10)]
    public function messageFailed(WorkerMessageFailedEvent $event): void {
      // We only update the count for failed message
      if ($event->willRetry()) {
        return;
      }

      $message = $event->getEnvelope()->getMessage();
      $this->recordJobResult($message, false);
    }
    private function recordJobResult(object $message, bool $success): void {
      if ($message instanceof ClubLinkedMessage) {
        $this->clubService->recordJobResult($message->getClubUuid(), $message->getJobKey(), $success);
      }
    }
}
