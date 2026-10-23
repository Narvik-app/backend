<?php

namespace App\Tests\Factory;

use League\Bundle\OAuth2ServerBundle\Model\Client;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<Client>
 */
final class ClientFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  private static ?string $secretHash = null;

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
   */
  public function __construct() {
  }

  public static function class(): string {
    return Client::class;
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
   */
  protected function defaults(): array|callable {
    return [
      'active'             => true,
      'allowPlainTextPkce' => false,
      // oauth2-server-bundle 2.x no longer accepts plaintext secrets: the stored value must be hashed
      'secret'             => self::$secretHash ??= new NativePasswordHasher(null, null, 4)->hash('secretTestOnly')
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(Client $client): void {})
      ;
  }
}
