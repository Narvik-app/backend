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
   * After a permission is granted, automatically grant implied permissions
   */
  public function onPostWrite(ViewEvent $event): void {
    $permission = $event->getControllerResult();
    $method = $event->getRequest()->getMethod();

    if (!$permission instanceof MemberPermission || $method !== Request::METHOD_POST) {
      return;
    }

    $member = $permission->getMember();
    $template = $permission->getTemplate();
    $club = $permission->getClub();

    if (!$member && !$template) {
      return;
    }

    // Auto-grant implied permissions (e.g. SALE_NEW -> SALE_HISTORY_ACCESS)
    $grantedPerm = $permission->getPermission();
    $impliedPermissions = $grantedPerm->getImpliedPermissions();

    if (empty($impliedPermissions)) {
      return;
    }

    // Check using member or template's hasPermission
    $target = $member ?? $template;

    foreach ($impliedPermissions as $impliedPermission) {
      // Check if permission is explicitly granted (don't use hasPermission as it includes implications)
      $alreadyExplicitlyGranted = false;
      foreach ($target->getPermissions() as $existingP) {
        if ($existingP->getPermission() === $impliedPermission) {
          $alreadyExplicitlyGranted = true;
          break;
        }
      }

      if (!$alreadyExplicitlyGranted) {
        $newPermission = new MemberPermission();
        $newPermission->setClub($club);
        $newPermission->setPermission($impliedPermission);

        if ($member) {
          $newPermission->setMember($member);
        } else {
          $newPermission->setTemplate($template);
        }

        $this->entityManager->persist($newPermission);
      }
    }

    $this->entityManager->flush();
  }

  /**
   * Block removal of implied permissions if the parent permission is still enabled
   */
  public function onPreWrite(ViewEvent $event): void {
    $permission = $event->getControllerResult();
    $method = $event->getRequest()->getMethod();

    if (!$permission instanceof MemberPermission || $method !== Request::METHOD_DELETE) {
      return;
    }

    $permissionToRemove = $permission->getPermission();
    $member = $permission->getMember();
    $template = $permission->getTemplate();
    $target = $member ?? $template;

    if (!$target) {
      return;
    }

    // Check if any EXISTING permission implies the one being removed
    foreach ($target->getPermissions() as $existingMP) {
      if ($existingMP === $permission) {
        continue;
      }

      $parentPerm = $existingMP->getPermission();
      if (in_array($permissionToRemove, $parentPerm->getImpliedPermissions(), true)) {
        throw new BadRequestHttpException("Unable to remove this permission because '{$parentPerm->value}' is enabled and requires it.");
      }
    }
  }
}
