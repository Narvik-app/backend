<?php

namespace App\EventSubscriber\Kernel;

use App\Entity\User;
use League\Bundle\OAuth2ServerBundle\Event\UserResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class LoginSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      OAuth2Events::USER_RESOLVE => [
        ['onUserResolve', 10],
      ]
    ];
  }

  public function __construct(
    private RequestStack $requestStack,
    private RateLimiterFactory $userIpLoginLimiter,
    // private readonly RateLimiterFactory $ipLoginLimiter,
    private UserProviderInterface $userProvider,
    private UserPasswordHasherInterface $userPasswordHasher,
  ) {
  }

  public function onUserResolve(UserResolveEvent $event): void {
    // $ipLimiter = $this->ipLoginLimiter->create($ip);
    // $ipLimiter->consume(1)->ensureAccepted();

    try {
      $user = $this->userProvider->loadUserByIdentifier($event->getUsername());
    } catch (AuthenticationException) {
      return;
    }

    if (!$user instanceof User) {
      return;
    }

    // We consume one user login token
    $userLimiter = $this->getUserLimiter($user);
    $userLimiter->consume(1)->ensureAccepted();

    if (!$this->userPasswordHasher->isPasswordValid($user, $event->getPassword())) {
      return;
    }

    // We reset the user login token
    $userLimiter = $this->getUserLimiter($user);
    $userLimiter->reset();

    // We log in the user
    $event->setUser($user);
  }

  private function getUserLimiter(User $user): LimiterInterface {
    $request = $this->requestStack->getCurrentRequest();
    $email = $user->getEmail();
    return $this->userIpLoginLimiter->create("{$email}/{$request->getClientIp()}");
  }
}
