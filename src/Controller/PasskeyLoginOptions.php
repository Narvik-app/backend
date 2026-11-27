<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Service\WebauthnService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Public: every call stores a challenge, so it is rate limited per IP.
 */
class PasskeyLoginOptions extends AbstractController {

  public function __invoke(Request $request, WebauthnService $webauthnService, RateLimiterFactoryInterface $passkeyOptionsLimiter): JsonResponse {
    try {
      $passkeyOptionsLimiter->create($request->getClientIp() ?? 'unknown')->consume(1)->ensureAccepted();
    } catch (RateLimitExceededException) {
      throw new HttpException(Response::HTTP_TOO_MANY_REQUESTS);
    }

    return new JsonResponse($webauthnService->createLoginOptions());
  }
}
