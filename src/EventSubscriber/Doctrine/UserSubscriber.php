<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\User;
use App\Enum\GlobalSetting;
use App\Service\GlobalSettingService;
use App\Service\MemberService;
use App\Service\UserService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[AsEntityListener(entity: User::class)]
class UserSubscriber extends AbstractEventSubscriber {
  public function __construct(
    private readonly UserService $userService,
    private readonly MemberService $memberService,
    private readonly GlobalSettingService $globalSettingService,
  ) {
  }

  public function prePersist(User $user, PrePersistEventArgs $args): void {
    $this->updatePassword($user);
  }

  public function postPersist(User $user, PostPersistEventArgs $args): void {
    $this->memberService->autolinkMemberFromUser($user);
  }

  public function postLoad(User $user, PostLoadEventArgs $args): void {
    $this->setLegalsStatus($user);
  }

  private function updatePassword(User $user): void {
    if (!empty($user->getPlainPassword())) {
      $changeError = $this->userService->changeUserPassword($user, $user->getPlainPassword(), false);
      if ($changeError) {
        throw new HttpException(Response::HTTP_BAD_REQUEST, $changeError);
      }
    }
  }

  private function setLegalsStatus(User $user): void {
    if (!$user->getLegalsAccepted()) {
      $user->setLegalsExpired(true);
      return;
    }

    $legalExpirationDate = \DateTimeImmutable::createFromFormat('Y-m-d', $this->globalSettingService->getSettingValue(GlobalSetting::LEGALS_LAST_UPDATE) ?? date("Y-m-d"));
    $legalExpirationDate = $legalExpirationDate->setTime(0, 0, 0);

    if ($user->getLegalsAccepted() <= $legalExpirationDate) {
      $user->setLegalsExpired(true);
      return;
    }

    $user->setLegalsExpired(false);
  }
}
