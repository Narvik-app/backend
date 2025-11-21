<?php

namespace App\EventSubscriber\Kernel;

use App\Message\Abstract\ClubLinkedMessage;
use App\Message\ItacMembersMessage;
use App\Service\ClubService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

final readonly class MessengerSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      WorkerMessageHandledEvent::class => [
        ['messageHandled', 10],
      ],
      WorkerMessageFailedEvent::class => [
        ['messageFailed', 10],
      ],
    ];
  }

  public function __construct(
    private ClubService $clubService,
  ) {
  }

  public function messageHandled(WorkerMessageHandledEvent $event): void {
    $message = $event->getEnvelope()->getMessage();
    $this->consumeMessage($message);
  }

  public function messageFailed(WorkerMessageFailedEvent $event): void {
    // We only update the count for failed message
    if ($event->willRetry()) {
      return;
    }

    $message = $event->getEnvelope()->getMessage();
    $this->consumeMessage($message);
  }

  private function consumeMessage(object $message): void {
    if ($message instanceof ClubLinkedMessage) {
      $this->clubService->consumeMessage($message->getClubUuid(), $message->getClubSettingRemainingField());
    }
  }
}
