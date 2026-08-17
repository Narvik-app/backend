<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Enum\GlobalSetting;
use App\Service\GlobalSettingService;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

class GlobalSettingSmtp extends AbstractController {

  public function __invoke(Request $request, KernelInterface $kernel, GlobalSettingService $globalSettingService) {
    // "password" is deliberately not required: it's masked by the API on
    // read (GlobalSetting::isSecret()), so the client can't always send it
    // back — omitting it means "keep the current password".
    $json = $this->checkAndGetJsonValues($request, ['on', 'host', 'port', 'username', 'sender', 'senderName']);

    // We apply the settings
    $globalSettingService->updateSettingValue(GlobalSetting::SMTP_ON, $this->toBoolean($json['on']) ? '1' : '0');
    $globalSettingService->updateSettingValue(GlobalSetting::SMTP_HOST, $json['host']);
    $globalSettingService->updateSettingValue(GlobalSetting::SMTP_PORT, (string) $json['port']);
    $globalSettingService->updateSettingValue(GlobalSetting::SMTP_USERNAME, !empty($json['username']) ? $json['username'] : null);

    // The password is never returned by the API (GlobalSetting::isSecret()),
    // so the form can't round-trip it. An absent/empty "password" means
    // "keep the current one"; an explicit null clears it.
    if (array_key_exists('password', $json) && $json['password'] !== '') {
      $globalSettingService->updateSettingValue(GlobalSetting::SMTP_PASSWORD, $json['password']);
    }

    $globalSettingService->updateSettingValue(GlobalSetting::SMTP_SENDER, $json['sender']);
    $globalSettingService->updateSettingValue(GlobalSetting::SMTP_SENDER_NAME, !empty($json['senderName']) ? $json['senderName'] : 'Narvik');

    if (!empty($json['newsletterSender'])) {
      $globalSettingService->updateSettingValue(GlobalSetting::SMTP_NEWSLETTER_SENDER, $json['newsletterSender']);
    }

    // We restart the messenger so the cache is refreshed
    $application = new Application($kernel);
    $command = new ArrayInput([
      'command' => 'messenger:stop-workers',
    ]);
    $application->doRun($command, new NullOutput());

    return new JsonResponse();
  }

}
