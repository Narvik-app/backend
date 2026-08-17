<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\GlobalSetting;
use App\Enum\GlobalSetting as GlobalSettingEnum;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Decorates the default Doctrine provider for GlobalSetting so that settings
 * flagged GlobalSetting::isSecret() (see the enum) never leak their value
 * through the API, even encrypted. hasValue is set explicitly so clients can
 * still tell "configured" apart from "not configured".
 */
class GlobalSettingProvider implements ProviderInterface {
  public function __construct(
    #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
    private readonly ProviderInterface $itemProvider,
    #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
    private readonly ProviderInterface $collectionProvider,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null {
    if ($operation instanceof CollectionOperationInterface) {
      $result = $this->collectionProvider->provide($operation, $uriVariables, $context);

      if (is_iterable($result)) {
        foreach ($result as $item) {
          $this->maskIfSecret($item);
        }
      }

      return $result;
    }

    $item = $this->itemProvider->provide($operation, $uriVariables, $context);
    $this->maskIfSecret($item);

    return $item;
  }

  private function maskIfSecret(mixed $item): void {
    if (!$item instanceof GlobalSetting) {
      return;
    }

    $setting = $this->tryFromName($item->getName());
    $isSecret = $setting?->isSecret() ?? false;

    $item->setHasValue($item->getValue() !== null);

    if ($isSecret) {
      $item->setValue(null);
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
