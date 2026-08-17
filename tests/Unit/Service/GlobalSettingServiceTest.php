<?php

namespace App\Tests\Unit\Service;

use App\Entity\GlobalSetting as GlobalSettingEntity;
use App\Enum\GlobalSetting;
use App\Repository\GlobalSettingRepository;
use App\Service\EncryptionService;
use App\Service\GlobalSettingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GlobalSettingServiceTest extends TestCase {
  public function testUpdateSettingValueEncryptsAnEncryptedSetting(): void {
    $persisted = null;

    $em = $this->createStub(EntityManagerInterface::class);
    $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
      $persisted = $entity;
    });

    $repository = $this->createStub(GlobalSettingRepository::class);
    $repository->method('findOneByName')->willReturn(null);

    $encryptionService = new EncryptionService();
    $service = new GlobalSettingService($em, $repository, $encryptionService, new NullLogger());

    $service->updateSettingValue(GlobalSetting::SMTP_PASSWORD, 'my-secret-password');

    $this->assertNotNull($persisted);
    $this->assertNotSame('my-secret-password', $persisted->getValue());
    $this->assertSame('my-secret-password', $encryptionService->decrypt($persisted->getValue()));
  }

  public function testUpdateSettingValueLeavesUnencryptedSettingAsPlaintext(): void {
    $persisted = null;

    $em = $this->createStub(EntityManagerInterface::class);
    $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
      $persisted = $entity;
    });

    $repository = $this->createStub(GlobalSettingRepository::class);
    $repository->method('findOneByName')->willReturn(null);

    $encryptionService = new EncryptionService();
    $service = new GlobalSettingService($em, $repository, $encryptionService, new NullLogger());

    $service->updateSettingValue(GlobalSetting::SMTP_HOST, 'smtp.example.com');

    $this->assertSame('smtp.example.com', $persisted->getValue());
  }

  public function testGetSettingValueDecryptsAnEncryptedSetting(): void {
    $encryptionService = new EncryptionService();

    $entity = new GlobalSettingEntity();
    $entity->setName(GlobalSetting::SMTP_PASSWORD->name);
    $entity->setValue($encryptionService->encrypt('my-secret-password'));

    $em = $this->createStub(EntityManagerInterface::class);
    $repository = $this->createStub(GlobalSettingRepository::class);
    $repository->method('findOneByName')->willReturn($entity);

    $service = new GlobalSettingService($em, $repository, $encryptionService, new NullLogger());

    $this->assertSame('my-secret-password', $service->getSettingValue(GlobalSetting::SMTP_PASSWORD));
  }

  public function testGetSettingValueToleratesLegacyPlaintextWithoutThrowing(): void {
    $entity = new GlobalSettingEntity();
    $entity->setName(GlobalSetting::SMTP_PASSWORD->name);
    $entity->setValue('legacy-plaintext-password'); // written before encryption was introduced

    $em = $this->createStub(EntityManagerInterface::class);
    $repository = $this->createStub(GlobalSettingRepository::class);
    $repository->method('findOneByName')->willReturn($entity);

    $encryptionService = new EncryptionService();
    $service = new GlobalSettingService($em, $repository, $encryptionService, new NullLogger());

    $this->assertSame('legacy-plaintext-password', $service->getSettingValue(GlobalSetting::SMTP_PASSWORD));
  }
}
