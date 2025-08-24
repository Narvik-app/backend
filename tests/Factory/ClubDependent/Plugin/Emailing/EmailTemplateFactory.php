<?php

namespace App\Tests\Factory\ClubDependent\Plugin\Emailing;

use App\Entity\ClubDependent\Plugin\Emailing\EmailTemplate;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<EmailTemplate>
 */
final class EmailTemplateFactory extends PersistentProxyObjectFactory {
  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
   */
  public function __construct() {
  }

  public static function class(): string {
    return EmailTemplate::class;
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
   */
  protected function defaults(): array|callable {
    return [
      'club'           => _InitStory::club_1(),
      'content'        => self::faker()->text(),
      'isNewsletter'   => self::faker()->boolean(),
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
