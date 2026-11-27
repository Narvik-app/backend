<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Checks shared by the ways to log in (password, passkey...). Each grant calls the ones it needs: a new
 * login method must call them too.
 */
final readonly class LoginGuardService {
  public function __construct(
    private RequestStack $requestStack,
    private RateLimiterFactoryInterface $ipLoginLimiter,
  ) {
  }

  /**
   * Consume one login attempt for the client IP.
   *
   * @throws \Symfony\Component\RateLimiter\Exception\RateLimitExceededException
   */
  public function consumeIpAttempt(): void {
    $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
    $this->ipLoginLimiter->create($ip)->consume(1)->ensureAccepted();
  }

  /**
   * Whether this user is allowed to get a token without a password (passkey, external provider...).
   * Badgers are technical accounts and log in with their own flow.
   */
  public function canLogin(User $user): bool {
    return $user->isAccountActivated() && $user->getRole() !== UserRole::badger;
  }
}
