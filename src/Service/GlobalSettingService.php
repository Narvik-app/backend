<?php

namespace App\Service;

use App\Entity\GlobalSetting as GlobalSettingEntity;
use App\Enum\GlobalSetting;
use App\Repository\GlobalSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class GlobalSettingService {
  public function __construct(
    private readonly EntityManagerInterface $em,
    private readonly GlobalSettingRepository $globalSettingRepository,
    private readonly EncryptionService $encryptionService,
    private readonly LoggerInterface $logger,
  ) {

  }

  public function settingExist(GlobalSetting $setting): bool {
    $dbSetting = $this->globalSettingRepository->findOneByName($setting->name);
    return (bool) $dbSetting;
  }

  public function getSettingValue(GlobalSetting $setting): ?string {
    $dbSetting = $this->globalSettingRepository->findOneByName($setting->name);

    if (!$dbSetting) {
      return null;
    }

    return $this->decryptValue($setting, $dbSetting->getValue());
  }

  public function decryptValue(GlobalSetting $setting, ?string $value): ?string {
    if ($value === null || !$setting->isEncrypted()) {
      return $value;
    }

    try {
      return $this->encryptionService->decrypt($value);
    } catch (\Throwable $e) {
      $this->logger->warning('Failed to decrypt GlobalSetting "{name}", returning raw value.', [
        'name' => $setting->name,
        'exception' => $e,
      ]);

      return $value;
    }
  }

  public function getRequiredSettingValue(GlobalSetting $setting): string {
    $dbSetting = $this->getSettingValue($setting);
    if (empty($dbSetting)) {
      throw new \Exception("Required GlobalSetting \"{$setting->name}\" not defined");
    }

    return $dbSetting;
  }

  public function updateSettingValue(GlobalSetting $setting, ?string $value): void {
    $dbSetting = $this->globalSettingRepository->findOneByName($setting->name);

    if (!$dbSetting) {
      $dbSetting = new GlobalSettingEntity();
      $dbSetting->setName($setting->name);
    }

    if ($value !== null && $value !== '' && $setting->isEncrypted()) {
      $value = $this->encryptionService->encrypt($value);
    }

    $dbSetting->setValue($value);
    $this->em->persist($dbSetting);
    $this->em->flush();
  }
}
