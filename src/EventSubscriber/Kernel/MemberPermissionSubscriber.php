<?php

namespace App\EventSubscriber\Kernel;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\ClubDependent\MemberPermission;
use App\Enum\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles permission dependencies:
 * - SALE_NEW auto-grants SALE_HISTORY_ACCESS and SALE_INVENTORY_ACCESS on create
 * - Blocks removal of SALE_HISTORY_ACCESS/SALE_INVENTORY_ACCESS if SALE_NEW is still enabled
 */
final readonly class MemberPermissionSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::VIEW => [
        ['onPostWrite', EventPriorities::POST_WRITE],
        ['onPreWrite', EventPriorities::PRE_WRITE],
      ],
    ];
  }

  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }

  /**
   * After SALE_NEW is granted, automatically grant implied permissions
   */
  public function onPostWrite(ViewEvent $event): void {
    $permission = $event->getControllerResult();
    $method = $event->getRequest()->getMethod();

    if (!$permission instanceof MemberPermission || $method !== Request::METHOD_POST) {
      return;
    }

    if ($permission->getPermission() !== Permission::SALE_NEW) {
      return;
    }

    $member = $permission->getMember();
    if (!$member) {
      return;
    }

    // Auto-grant SALE_HISTORY_ACCESS and SALE_INVENTORY_ACCESS
    $impliedPermissions = [
      Permission::SALE_HISTORY_ACCESS,
      Permission::SALE_INVENTORY_ACCESS,
    ];

    foreach ($impliedPermissions as $impliedPermission) {
      if (!$member->hasPermission($impliedPermission)) {
        $newPermission = new MemberPermission();
        $newPermission->setMember($member);
        $newPermission->setPermission($impliedPermission);
        $this->entityManager->persist($newPermission);
      }
    }

    $this->entityManager->flush();
  }

  /**
   * Block removal of implied permissions if SALE_NEW is still enabled
   */
  public function onPreWrite(ViewEvent $event): void {
    $permission = $event->getControllerResult();
    $method = $event->getRequest()->getMethod();

    if (!$permission instanceof MemberPermission || $method !== Request::METHOD_DELETE) {
      return;
    }

    $permValue = $permission->getPermission();

    // Check if this is an implied permission
    if (!in_array($permValue, [Permission::SALE_HISTORY_ACCESS, Permission::SALE_INVENTORY_ACCESS], true)) {
      return;
    }

    $member = $permission->getMember();
    if (!$member) {
      return;
    }

    // Check if SALE_NEW is still active
    if ($member->hasPermission(Permission::SALE_NEW)) {
      throw new BadRequestHttpException(
        "Impossible de retirer cette permission car 'Faire une vente' est activée. Désactivez d'abord 'Faire une vente'."
      );
    }
  }
}
