<?php

namespace App\Security\Grant;

use App\Exception\InvalidPasskeyException;
use App\Service\LoginGuardService;
use App\Service\WebauthnService;
use League\Bundle\OAuth2ServerBundle\Converter\UserConverterInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;

/**
 * POST /token with grant_type=urn:narvik:grant:passkey, challenge_id and credential (the JSON
 * returned by navigator.credentials.get()). Issues the same access/refresh tokens as the password grant.
 */
#[AutoconfigureTag('league.oauth2_server.authorization_server.grant')]
final class PasskeyGrant extends AbstractGrant {
  public const string IDENTIFIER = 'urn:narvik:grant:passkey';

  public function __construct(
    RefreshTokenRepositoryInterface $refreshTokenRepository,
    // Every grant is built with the AuthorizationServer on each /token call (password, refresh...)
    #[Autowire(lazy: true)]
    private readonly WebauthnService $webauthnService,
    private readonly LoginGuardService $loginGuard,
    private readonly UserConverterInterface $userConverter,
    #[Autowire(param: 'league.oauth2_server.refresh_token_ttl.default')]
    string $refreshTokenTTL,
  ) {
    $this->setRefreshTokenRepository($refreshTokenRepository);
    $this->setRefreshTokenTTL(new \DateInterval($refreshTokenTTL));
  }

  public function getIdentifier(): string {
    return self::IDENTIFIER;
  }

  public function respondToAccessTokenRequest(ServerRequestInterface $request, ResponseTypeInterface $responseType, \DateInterval $accessTokenTTL): ResponseTypeInterface {
    $client = $this->validateClient($request);
    $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));

    $challengeId = $this->getRequestParameter('challenge_id', $request)
      ?? throw OAuthServerException::invalidRequest('challenge_id');
    // The credential is an object: sent as a JSON string (form body) or as a nested object (JSON body)
    $credential = ((array) $request->getParsedBody())['credential'] ?? null;
    if ((!is_string($credential) || $credential === '') && !is_array($credential)) {
      throw OAuthServerException::invalidRequest('credential');
    }

    try {
      $this->loginGuard->consumeIpAttempt();
    } catch (RateLimitExceededException) {
      throw new OAuthServerException('Too many login attempts, retry later.', 10, 'too_many_requests', 429);
    }

    try {
      $user = $this->webauthnService->verifyLogin($challengeId, $credential);
    } catch (InvalidPasskeyException) {
      $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));
      throw OAuthServerException::invalidCredentials();
    }

    if (!$this->loginGuard->canLogin($user)) {
      $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));
      throw OAuthServerException::invalidCredentials();
    }

    $leagueUser = $this->userConverter->toLeague($user);
    $finalizedScopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client, $leagueUser->getIdentifier());

    $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $leagueUser->getIdentifier(), $finalizedScopes);
    $this->getEmitter()->emit(new RequestAccessTokenEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request, $accessToken));
    $responseType->setAccessToken($accessToken);

    $refreshToken = $this->issueRefreshToken($accessToken);
    if ($refreshToken !== null) {
      $this->getEmitter()->emit(new RequestRefreshTokenEvent(RequestEvent::REFRESH_TOKEN_ISSUED, $request, $refreshToken));
      $responseType->setRefreshToken($refreshToken);
    }

    return $responseType;
  }
}
