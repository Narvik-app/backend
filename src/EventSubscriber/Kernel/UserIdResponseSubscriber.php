<?php

namespace App\EventSubscriber\Kernel;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class UserIdResponseSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => 'onKernelResponse',
    ];
  }

  public function __construct(
    private Security $security,
  ) {
  }

  public function onKernelResponse(ResponseEvent $event): void {
    $user = $this->security->getUser();

    if (!$user instanceof User) {
      return;
    }

    $event->getResponse()->headers->set('X-User-Id', (string) $user->getUuid());
  }
}
