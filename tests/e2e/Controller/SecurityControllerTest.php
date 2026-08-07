<?php

namespace App\Tests\e2e\Controller;

use App\Tests\e2e\AbstractApiTestCase;
use App\Tests\Story\_InitStory;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityControllerTest extends AbstractApiTestCase {
  public function testWrongBadgerTokenIsRejected(): void {
    $club = _InitStory::club_1();

    static::createClient()->request(Request::METHOD_POST, '/auth/bdg', [
      'json' => [
        'token' => 'not-the-right-token',
        'club' => $club->getUuid()->toString(),
      ],
    ]);
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
  }

  public function testRepeatedWrongBadgerTokensAreRateLimited(): void {
    // Fresh club (random uuid) so this test's rate-limit bucket never collides with another test
    $club = \App\Tests\Factory\ClubFactory::createOne(['badgerToken' => 'the-real-token']);

    for ($i = 0; $i < 5; $i++) {
      static::createClient()->request(Request::METHOD_POST, '/auth/bdg', [
        'json' => ['token' => 'wrong', 'club' => $club->getUuid()->toString()],
      ]);
      $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    // 6th attempt within the window is throttled, even with the correct token this time.
    static::createClient()->request(Request::METHOD_POST, '/auth/bdg', [
      'json' => ['token' => 'the-real-token', 'club' => $club->getUuid()->toString()],
    ]);
    $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
  }

  public function testBadgerClientCannotUsePasswordGrant(): void {
    $club = _InitStory::club_1();

    $clientManager = self::getContainer()->get(ClientManagerInterface::class);
    $badgerClient = $clientManager->find('badger');
    $badgerClient->setSecret('');
    $badgerClient->setGrants(new Grant('refresh_token'));
    $badgerClient->setScopes(new Scope('badger'));
    $clientManager->save($badgerClient);

    $badgerLogin = static::createClient()->request(Request::METHOD_POST, '/auth/bdg', [
      'json' => ['token' => $club->getBadgerToken(), 'club' => $club->getUuid()->toString()],
    ]);
    $this->assertResponseIsSuccessful();
    $refreshToken = $badgerLogin->toArray()['refresh_token'];

    // Client is public now, so *any* value is accepted as the client secret in Basic auth...
    $response = static::createClient()->request(Request::METHOD_POST, '/token', [
      'json' => [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
      ],
      'headers' => ['Authorization' => 'Basic ' . base64_encode('badger:anything')],
    ]);
    $this->assertResponseIsSuccessful();

    // The client is restricted to the refresh_token grant, so password grant is refuse regardless of the secret
    static::createClient()->request(Request::METHOD_POST, '/token', [
      'json' => [
        'grant_type' => 'password',
        'username' => 'admin@admin.com',
        'password' => 'irrelevant',
      ],
      'headers' => ['Authorization' => 'Basic ' . base64_encode('badger:anything')],
    ]);
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    $this->assertJsonContains(['error' => 'unauthorized_client']);
  }
}
