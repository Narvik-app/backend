<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\GlobalSetting;
use App\Enum\GlobalSetting as GlobalSettingEnum;
use App\Service\GlobalSettingService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Routes writes to GlobalSetting through GlobalSettingService so that
 * PATCH /global-settings/{name} can't write a setting flagged
 * GlobalSetting::isEncrypted() as plaintext, bypassing encryption.
 */
class GlobalSettingProcessor implements ProcessorInterface {
  public function __construct(
    private readonly GlobalSettingService $globalSettingService,
    #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
    private readonly ProcessorInterface $persistProcessor,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed {
    if (!$data instanceof GlobalSetting) {
      return $data;
    }

    $setting = $this->tryFromName($data->getName());

    if ($setting === null) {
      // Unknown name (shouldn't normally happen: GlobalSetting rows are
      // seeded from the enum) — fall back to a plain save so Patch still
      // works, without going through the encryption allowlist.
      return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    // GlobalSettingProvider masks isSecret() values to null before the PATCH
    // body is merged onto the entity. A request that omits "value" entirely
    // would otherwise silently wipe the secret through this generic
    // endpoint. Treat that combination as a no-op instead of a clear; a
    // client that genuinely wants to clear a secret must submit an explicit
    // empty string.
    if ($setting->isSecret() && $data->getValue() === null) {
      return $data;
    }

    $this->globalSettingService->updateSettingValue($setting, $data->getValue());

    return $data;
  }

  private function tryFromName(string $name): ?GlobalSettingEnum {
    foreach (GlobalSettingEnum::cases() as $case) {
      if ($case->name === $name) {
        return $case;
      }
    }

    return null;
  }
}
