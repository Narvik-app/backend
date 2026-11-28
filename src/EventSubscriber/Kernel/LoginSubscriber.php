<?php

namespace App\EventSubscriber\Kernel;

use App\Entity\User;
use App\Service\LoginGuardService;
use League\Bundle\OAuth2ServerBundle\Event\UserResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class LoginSubscriber
{
    public function __construct(
      private RequestStack $requestStack,
      private RateLimiterFactoryInterface $userIpLoginLimiter,
      private LoginGuardService $loginGuard,
      private UserProviderInterface $userProvider,
      private UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }
    #[AsEventListener(event: OAuth2Events::USER_RESOLVE, priority: 10)]
    public function onUserResolve(UserResolveEvent $event): void {
      $this->loginGuard->consumeIpAttempt();

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

      // Same shared checks as the other grants (activated account, not a badger)
      if (!$this->loginGuard->canLogin($user)) {
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
