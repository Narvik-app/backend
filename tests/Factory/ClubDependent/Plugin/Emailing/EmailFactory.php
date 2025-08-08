<?php

namespace App\Tests\Factory\ClubDependent\Plugin\Emailing;

use App\Entity\ClubDependent\Plugin\Emailing\Email;
use App\Enum\EmailStatus;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Email>
 */
final class EmailFactory extends PersistentProxyObjectFactory {
  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
   */
  public function __construct() {
  }

  public static function class(): string {
    return Email::class;
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
   */
  protected function defaults(): array|callable {
    return [
      'club'           => _InitStory::club_1(),
      'content'        => self::faker()->text(),
      'isNewsletter'   => self::faker()->boolean(),
      'recipientCount' => self::faker()->randomNumber(),
      'sender'         => self::faker()->email(),
      'status'         => self::faker()->randomElement(EmailStatus::cases()),
      'title'          => self::faker()->text(255),
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(Email $email): void {})
      ;
  }
}
