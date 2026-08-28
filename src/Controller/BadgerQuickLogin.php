<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\User;
use App\Entity\UserMember;
use App\Enum\ClubActivity;
use App\Enum\UserSecurityCodeTrigger;
use App\Repository\ClubRepository;
use App\Repository\UserRepository;
use App\Repository\UserSecurityCodeRepository;
use App\Service\ClubService;
use App\Service\TurnstileService;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class BadgerQuickLogin extends AbstractController {
  public function __construct(
    private readonly UserSecurityCodeRepository $userSecurityCodeRepository,
    private readonly UserService $userService,
    private readonly TurnstileService $turnstileService,
    private readonly RateLimiterFactoryInterface $badgerLoginLimiter
  ) {
  }


  public function __invoke(Request $request): JsonResponse {
    $limiter = $this->badgerLoginLimiter->create($request->getClientIp());
    try {
      $limiter->consume(1)->ensureAccepted();
    } catch (RateLimitExceededException) {
      throw new HttpException(Response::HTTP_TOO_MANY_REQUESTS);
    }

    $payloadRequiredFields = ['securityCode'];
    if ($this->turnstileService->isEnabled()) {
      $payloadRequiredFields[] = 'cf_token';
    }

    $payload = $this->checkAndGetJsonValues($request, $payloadRequiredFields);

    // We must check the token is valid
    if ($this->turnstileService->isEnabled()) {
      $token = $payload['cf_token'];
      $validated = $this->turnstileService->verifyToken($token);
      if (!$validated) {
        throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid cf token.');
      }
    }

    $securityCode = $payload['securityCode'];
    $userSecurityCode = $this->userSecurityCodeRepository->findLastOneBySecurityCode($securityCode, UserSecurityCodeTrigger::badgerQuickLogin);
    $user = $userSecurityCode?->getUser();

    /** @var UserMember|null $userMember */
    $userMember = $user?->getMemberships()->first();
    $club = $userMember?->getBadgerClub();

    if (!$user || !$club) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, "Invalid security code.");
    }

    $limiter->reset();

    // We can now consume the security code
    $this->userService->consumeAllSecurityCodes($user, UserSecurityCodeTrigger::badgerQuickLogin);

    // We get the badger quick login url
    $data = [
      'club' => $club->getUuid(),
      'token' => $club->getBadgerToken(),
    ];

    return new JsonResponse($data);
  }

}
