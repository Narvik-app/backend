<?php

namespace App\Command;

use App\Enum\GlobalSetting;
use App\Service\GlobalSettingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'install:default-settings', description: 'Generate default settings')]
class InstallDefaultSettings extends Command {
  private SymfonyStyle $io;

  public function __construct(
    private readonly GlobalSettingService $globalSettingService,
  ) {
    parent::__construct();
  }


  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->io = new SymfonyStyle($input, $output);

    $this->io->section("Définition des settings globaux par défaut");

    if (!$this->globalSettingService->settingExist(GlobalSetting::LEGALS_LAST_UPDATE)) {
      $this->io->writeln("Dernière modification documents légaux");
      $this->globalSettingService->updateSettingValue(GlobalSetting::LEGALS_LAST_UPDATE, null);
    }

    /** @var GlobalSetting[] $smtpFields */
    $smtpFields = [
      GlobalSetting::SMTP_ON,
      GlobalSetting::SMTP_HOST,
      GlobalSetting::SMTP_PORT,
      GlobalSetting::SMTP_USERNAME,
      GlobalSetting::SMTP_PASSWORD,
      GlobalSetting::SMTP_SENDER,
      GlobalSetting::SMTP_NEWSLETTER_SENDER,
      GlobalSetting::SMTP_SENDER_NAME,
    ];
    foreach ($smtpFields as $field) {
      if (!$this->globalSettingService->settingExist($field)) {
        $this->io->writeln("Configuration SMTP: " . $field->name);
        $this->globalSettingService->updateSettingValue($field, null);
      }
    }

    return Command::SUCCESS;
  }

}
