<?php

namespace App\Tests\Story;

use App\Enum\GlobalSetting;
use App\Tests\Factory\GlobalSettingFactory;
use Zenstruck\Foundry\Story;
use function Zenstruck\Foundry\faker;

final class GlobalSettingStory extends Story {

  public function build(): void {
    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::LEGALS_LAST_UPDATE->name,
      'value' => '2025-03-31',
    ]));
    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::LEGALS_CGU->name,
      'value' => null,
    ]));

    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::LEGALS_CGV->name,
      'value' => null,
    ]));

    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::LEGALS_PRIVACY_POLICY->name,
      'value' => null,
    ]));


    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::SMTP_ON->name,
      'value' => '1',
    ]));

    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::SMTP_HOST->name,
      'value' => 'mail',
    ]));

    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::SMTP_PORT->name,
      'value' => '1025',
    ]));

    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::SMTP_USERNAME->name,
      'value' => null,
    ]));

    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::SMTP_PASSWORD->name,
      'value' => null,
    ]));

    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::SMTP_SENDER->name,
      'value' => 'narvik@example.com',
    ]));

    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::SMTP_NEWSLETTER_SENDER->name,
      'value' => 'narvik-newsletter@example.com',
    ]));

    $this->addToPool('required_settings', GlobalSettingFactory::createOne([
      'name'  => GlobalSetting::SMTP_SENDER_NAME->name,
      'value' => null,
    ]));
  }
}
