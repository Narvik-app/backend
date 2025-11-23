<?php

namespace App\EventSubscriber\Kernel;

use ApiPlatform\State\ApiResource\Error;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Enum\UserRole;
use App\Service\RequestService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ClubLinkedEntitySubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => [
        ['preRead', EventPriorities::PRE_READ],
        ['requestEvent', EventPriorities::POST_DESERIALIZE],
      ],
    ];
  }

  public function __construct(
    private RequestService $requestService,
    private Security $security,
  ) {
  }

  public function preRead(RequestEvent $event): void {
    $this->validateClubLinkedRequest($event);
  }

  public function requestEvent(RequestEvent $event): void {
    if ($event->getRequest()->getMethod() !== Request::METHOD_POST) {
      return;
    }

    // We auto set the club uuid for any ClubLinkedEntity to the uri set one
    $clubLinkedObject = $event->getRequest()->attributes->get('data');
    if (!$clubLinkedObject instanceof ClubLinkedEntityInterface) {
      return;
    }

    $clubRequest = $this->requestService->getClubFromRequest($event->getRequest());
    if (!$clubRequest) {
      return;
    }

    // We update to the matched one
    $clubLinkedObject->setClub($clubRequest);
    $event->getRequest()->attributes->set('data', $clubLinkedObject);
  }

  private function validateClubLinkedRequest(RequestEvent $event): void {
    $request = $event->getRequest();

    // Method is GET, we always allow so they can get their data back
    // Read only mode
    if ($request->getMethod() === Request::METHOD_GET || $this->security->isGranted(UserRole::super_admin->value)) {
       return;
    }

    $path = $request->getPathInfo();

    $regex = '/^\/clubs\/(.*?)\/(.*)/m';
    $matches = [];
    $clubDependant = preg_match($regex, $path, $matches);
    if (!$clubDependant) {
      return;
    }

    $club = $this->requestService->getClubFromRequest($event->getRequest(), false);
    if (!$club) {
      return;
    }

    // We get the club, if he is not activated we deny all the request
    if (!$club->getIsActivated()) {
      $this->throwLockedException($event, 'Club not activated.');
      return;
    }

    $this->verifyAccessForPlugins($event, $club, "/$matches[2]");
  }

  private function verifyAccessForPlugins(RequestEvent $event, Club $club, string $cleanedPath): void {
    $presencesPattern = [
      '/member-presences',
      '/external-presences',
    ];
    $checked = $this->verifyAccessForPlugin("Presences", $presencesPattern, $club->getPresencesEnabled(), $cleanedPath, $event);
    if ($checked) {
      return;
    }

    $salesPattern = [
      '/sales',
      '/sale-payment-modes',
      '/inventory-'
    ];
    $checked = $this->verifyAccessForPlugin("Sales", $salesPattern, $club->getSalesEnabled(), $cleanedPath, $event);
    if ($checked) {
      return;
    }
  }

  private function verifyAccessForPlugin(string $pluginName, array $urlPatterns, bool $pluginEnabled, string $requestedPath, RequestEvent $event): bool {
    foreach ($urlPatterns as $urlPattern) {
      if (str_starts_with($requestedPath, (string) $urlPattern)) {
        if (!$pluginEnabled) {
          $this->throwLockedException($event, "$pluginName plugin not activated.");
        }
        return true;
      }
    }

    return false;
  }

  private function throwLockedException(RequestEvent $event, string $message = ''): void {
    $error = Error::createFromException(new \Exception($message), Response::HTTP_LOCKED);
    $formatedError = [
      "title" => $error->getTitle(),
      "detail" => $error->getDetail(),
      "status" => $error->getStatus(),
      "type" => $error->getType(),
      "description" => $error->getDescription(),
    ];

    $event->setResponse(
      new JsonResponse($formatedError, Response::HTTP_LOCKED)
    );
    $event->stopPropagation();
  }

}
