<?php

namespace App\Tests\e2e\Controller;

use App\Entity\User;
use App\Entity\WebauthnChallenge;
use App\Security\Grant\PasskeyGrant;
use App\Tests\e2e\AbstractApiTestCase;
use App\Tests\Helper\SoftwareAuthenticator;
use App\Tests\Story\_InitStory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PasskeyApiTest extends AbstractApiTestCase {

  private function registerPasskey(SoftwareAuthenticator $authenticator, string $name = 'Mon téléphone'): array {
    $options = $this->makePostRequest('/self/passkeys/-/options', []);
    $this->assertResponseIsSuccessful();
    $options = $options->toArray();

    $response = $this->makePostRequest('/self/passkeys', [
      'challengeId' => $options['challengeId'],
      'credential'  => $authenticator->create($options['publicKey']),
      'name'        => $name,
    ]);
    $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

    return $response->toArray();
  }

  private function getLoginOptions(): array {
    $response = $this->makeNotLoggedRequest('POST', '/public/passkeys/-/login-options', []);
    $this->assertResponseIsSuccessful();
    return $response->toArray();
  }

  private function loginWithPasskey(SoftwareAuthenticator $authenticator, ?array $options = null): ResponseInterface {
    $options ??= $this->getLoginOptions();
    return $this->requestToken([
      'grant_type'   => PasskeyGrant::IDENTIFIER,
      'challenge_id' => $options['challengeId'],
      'credential'   => $authenticator->get($options['publicKey']),
    ]);
  }

  public function testRegistrationOptions(): void {
    $this->loggedAsMemberClub1();
    $response = $this->makePostRequest('/self/passkeys/-/options', []);
    $this->assertResponseIsSuccessful();

    $data = $response->toArray();
    $this->assertNotEmpty($data['challengeId']);
    $this->assertSame('narvik.app', $data['publicKey']['rp']['id']);
    $this->assertSame('member@club1.fr', $data['publicKey']['user']['name']);
    $this->assertSame('required', $data['publicKey']['authenticatorSelection']['residentKey']);
    $this->assertSame('required', $data['publicKey']['authenticatorSelection']['userVerification']);
    $this->assertNotEmpty($data['publicKey']['challenge']);
  }

  public function testRegisterAndLoginWithPasskey(): void {
    $authenticator = new SoftwareAuthenticator();

    $this->loggedAsMemberClub1();
    $passkey = $this->registerPasskey($authenticator);
    $this->assertSame('Mon téléphone', $passkey['name']);
    $this->assertNull($passkey['lastUsedAt'] ?? null);

    $list = $this->makeGetRequest('/self/passkeys')->toArray();
    $this->assertCount(1, $list['member']);

    // The same passkey can't be registered twice: the browser receives it in excludeCredentials
    $options = $this->makePostRequest('/self/passkeys/-/options', [])->toArray();
    $this->assertSame($authenticator->getCredentialId(), $options['publicKey']['excludeCredentials'][0]['id']);

    // Passwordless login
    $response = $this->loginWithPasskey($authenticator);
    $this->assertResponseIsSuccessful();
    $tokens = $response->toArray();
    $this->assertArrayHasKey('access_token', $tokens);
    $this->assertArrayHasKey('refresh_token', $tokens);

    $self = $this->makeGetRequest('/self')->toArray();
    $this->assertSame('member@club1.fr', $self['email']);

    $list = $this->makeGetRequest('/self/passkeys')->toArray();
    $this->assertNotNull($list['member'][0]['lastUsedAt']);

    // The refresh token works like for a password login
    $this->requestToken([
      'grant_type'    => 'refresh_token',
      'refresh_token' => $tokens['refresh_token'],
    ]);
    $this->assertResponseIsSuccessful();
  }

  public function testChallengeCannotBeReplayed(): void {
    $authenticator = new SoftwareAuthenticator();
    $this->loggedAsMemberClub1();
    $this->registerPasskey($authenticator);

    $options = $this->getLoginOptions();
    $this->loginWithPasskey($authenticator, $options);
    $this->assertResponseIsSuccessful();

    $this->loginWithPasskey($authenticator, $options);
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
  }

  public function testExpiredChallenge(): void {
    $authenticator = new SoftwareAuthenticator();
    $this->loggedAsMemberClub1();
    $this->registerPasskey($authenticator);

    $options = $this->getLoginOptions();

    /** @var EntityManagerInterface $em */
    $em = self::getContainer()->get(EntityManagerInterface::class);
    $challenge = $em->getRepository(WebauthnChallenge::class)->findOneBy(['uuid' => $options['challengeId']]);
    $challenge->setExpireAt(new \DateTimeImmutable('-1 minute'));
    $em->flush();

    $this->loginWithPasskey($authenticator, $options);
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
  }

  public function testUnknownPasskey(): void {
    $authenticator = new SoftwareAuthenticator();
    $authenticator->setUserHandle(SoftwareAuthenticator::b64url(_InitStory::USER_member_club_1()->getUuid()->toRfc4122()));

    $this->loginWithPasskey($authenticator);
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    $this->assertJsonContains(['error' => 'invalid_grant']);
  }

  public function testWrongUserHandle(): void {
    $authenticator = new SoftwareAuthenticator();
    $this->loggedAsMemberClub1();
    $this->registerPasskey($authenticator);

    // The authenticator claims to be another user
    $authenticator->setUserHandle(SoftwareAuthenticator::b64url(_InitStory::USER_admin_club_1()->getUuid()->toRfc4122()));
    $this->loginWithPasskey($authenticator);
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
  }

  public function testSignCounterGoingBackwardIsRefused(): void {
    $authenticator = new SoftwareAuthenticator();
    $this->loggedAsMemberClub1();
    $this->registerPasskey($authenticator);

    $authenticator->setSignCount(10);
    $this->loginWithPasskey($authenticator); // counter 11
    $this->assertResponseIsSuccessful();

    // A cloned authenticator would send an older counter
    $authenticator->setSignCount(4);
    $this->loginWithPasskey($authenticator); // counter 5
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
  }

  public function testWrongOriginIsRefused(): void {
    $this->loggedAsMemberClub1();
    $options = $this->makePostRequest('/self/passkeys/-/options', [])->toArray();

    $phishing = new SoftwareAuthenticator('https://narvik.app.evil.com');
    $this->makePostRequest('/self/passkeys', [
      'challengeId' => $options['challengeId'],
      'credential'  => $phishing->create($options['publicKey']),
    ]);
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
  }

  public function testDeactivatedUserCannotLogin(): void {
    $authenticator = new SoftwareAuthenticator();
    $this->loggedAsMemberClub1();
    $this->registerPasskey($authenticator);

    /** @var EntityManagerInterface $em */
    $em = self::getContainer()->get(EntityManagerInterface::class);
    $user = $em->getRepository(User::class)->findOneBy(['email' => 'member@club1.fr']);
    $user->setAccountActivated(false);
    $em->flush();

    $this->loginWithPasskey($authenticator);
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
  }

  public function testOnlyOwnerCanManagePasskey(): void {
    $this->loggedAsMemberClub1();
    $passkey = $this->registerPasskey(new SoftwareAuthenticator());
    $iri = '/self/passkeys/' . $passkey['uuid'];
    // The frontend uses @id to update/delete
    $this->assertSame($iri, $passkey['@id']);
    $this->assertSame($iri, $this->makeGetRequest('/self/passkeys')->toArray()['member'][0]['@id']);

    $this->loggedAsAdminClub1();
    $this->assertCount(0, $this->makeGetRequest('/self/passkeys')->toArray()['member']);
    $this->makeGetRequest($iri);
    $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    $this->makePatchRequest($iri, ['name' => 'Hacked']);
    $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    $this->makeDeleteRequest($iri);
    $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

    $this->loggedAsMemberClub1();
    $response = $this->makePatchRequest($iri, ['name' => 'Clé USB']);
    $this->assertResponseIsSuccessful();
    $this->assertSame('Clé USB', $response->toArray()['name']);

    $this->makePatchRequest($iri, ['name' => '']);
    $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

    $this->makeDeleteRequest($iri);
    $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    $this->assertCount(0, $this->makeGetRequest('/self/passkeys')->toArray()['member']);
  }

  public function testRegistrationChallengeIsBoundToUser(): void {
    $this->loggedAsMemberClub1();
    $options = $this->makePostRequest('/self/passkeys/-/options', [])->toArray();

    $this->loggedAsAdminClub1();
    $this->makePostRequest('/self/passkeys', [
      'challengeId' => $options['challengeId'],
      'credential'  => new SoftwareAuthenticator()->create($options['publicKey']),
    ]);
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
  }

  public function testBadgerCannotRegisterPasskey(): void {
    $this->loggedAsBadgerClub1();
    $this->makePostRequest('/self/passkeys/-/options', []);
    $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
  }
}
