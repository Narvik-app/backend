<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\GlobalSetting;
use App\Enum\GlobalSetting as GlobalSettingEnum;
use App\Service\GlobalSettingService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Decorates the default Doctrine provider for GlobalSetting: settings
 * flagged GlobalSetting::isSecret() never return their value through the API
 * (hasValue is set explicitly instead, so clients can tell "configured"
 * apart from "not configured").
 */
class GlobalSettingProvider implements ProviderInterface {
  public function __construct(
    #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
    private readonly ProviderInterface $itemProvider,
    #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
    private readonly ProviderInterface $collectionProvider,
    private readonly GlobalSettingService $globalSettingService,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null {
    if ($operation instanceof CollectionOperationInterface) {
      $result = $this->collectionProvider->provide($operation, $uriVariables, $context);

      if (is_iterable($result)) {
        foreach ($result as $item) {
          $this->applyEncryptionVisibility($item);
        }
      }

      return $result;
    }

    $item = $this->itemProvider->provide($operation, $uriVariables, $context);
    $this->applyEncryptionVisibility($item);

    return $item;
  }

  private function applyEncryptionVisibility(mixed $item): void {
    if (!$item instanceof GlobalSetting) {
      return;
    }

    $setting = $this->tryFromName($item->getName());

    $item->setHasValue($item->getValue() !== null);

    if ($setting === null) {
      return;
    }

    if ($setting->isSecret()) {
      $item->setValue(null);
      return;
    }

    if ($setting->isEncrypted()) {
      $item->setValue($this->globalSettingService->decryptValue($setting, $item->getValue()));
    }
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
