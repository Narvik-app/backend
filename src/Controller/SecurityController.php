<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Repository\ClubRepository;
use App\Service\ClubService;
use App\Service\UuidService;
use League\Bundle\OAuth2ServerBundle\Entity\Scope;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Nyholm\Psr7\Response as Psr7Response;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController {

  #[Route(path: ['/auth/bdg'], name: 'auth_bdg', methods: ['POST'])]
  public function loginBadger(
    ParameterBagInterface $params,
    Request $request,
    KernelInterface $kernel,
    ClientRepositoryInterface $clientRepository,
    AccessTokenRepositoryInterface $accessTokenRepository,
    RefreshTokenRepositoryInterface $refreshTokenRepository,
    ClubRepository $clubRepository,
    ClubService $clubService,
    RateLimiterFactoryInterface $badgerLoginLimiter
  ): Response {
    $json = $this->checkAndGetJsonValues($request, ['token', 'club']);

    $limiter = $badgerLoginLimiter->create("{$json['club']}/{$request->getClientIp()}");
    try {
      $limiter->consume(1)->ensureAccepted();
    } catch (RateLimitExceededException) {
      throw new HttpException(Response::HTTP_TOO_MANY_REQUESTS);
    }

    $club = $clubRepository->findOneByUuid($json['club']);
    $badgerToken = $club?->getBadgerToken();

    if (!$badgerToken || !hash_equals($badgerToken, $json['token'])) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    $limiter->reset();

    $user = $clubService->getBadger($club);
    if (!$user) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    $client = $clientRepository->getClientEntity('badger');
    if (!$client) {
      throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    $scope = new Scope();
    $scope->setIdentifier('badger');

    $key = str_replace('%kernel.project_dir%', $kernel->getProjectDir(), $params->get('app.oauth_private_key'));
    $cryptKey = new CryptKey($key, $params->get('app.oauth_passphrase'), false);

    $accessToken = $accessTokenRepository->getNewToken($client, [$scope], "badger@{$club->getUuid()}");
    $accessToken->setExpiryDateTime(new \DateTimeImmutable()->add(new \DateInterval('PT1H')));
    $accessToken->setIdentifier("badger-" . UuidService::generateUuid()->toString());
    $accessToken->setPrivateKey($cryptKey);

    try {
      $accessTokenRepository->persistNewAccessToken($accessToken);
    }
    catch (UniqueTokenIdentifierConstraintViolationException) {
      throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    $refreshToken = $refreshTokenRepository->getNewRefreshToken();
    $refreshToken->setIdentifier($accessToken->getIdentifier());
    $refreshToken->setAccessToken($accessToken);
    $refreshToken->setExpiryDateTime(new \DateTimeImmutable()->add(new \DateInterval('P1M')));

    try {
      $refreshTokenRepository->persistNewRefreshToken($refreshToken);
    } catch (UniqueTokenIdentifierConstraintViolationException) {
      throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    $bearer = new BearerTokenResponse();
    $bearer->setPrivateKey($cryptKey);
    $bearer->setEncryptionKey($params->get('league.oauth2_server.encryption_key'));
    $bearer->setAccessToken($accessToken);
    $bearer->setRefreshToken($refreshToken);
    $response = $bearer->generateHttpResponse(new Psr7Response());

    $httpFoundationFactory = new HttpFoundationFactory();
    return $httpFoundationFactory->createResponse($response);
  }
}
