<?php

namespace App\Tests\functional\Validator;

use App\Tests\AbstractTestCase;
use App\Validator\Constraints\VatNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class VatNumberValidatorTest extends AbstractTestCase
{
    private ValidatorInterface $validator;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

  public static function getValidVatNumbers(): \Generator
  {
    yield ['FR53837575646']; // Valid checksum
    yield ['FRXX123456789']; // Alphanumeric key, no checksum validation
    yield ['DE123456788'];   // Valid checksum
  }

    #[DataProvider('getValidVatNumbers')]
    public function testValidVatNumbers(string $vatNumber): void
    {
        $violations = $this->validator->validate($vatNumber, new VatNumber());
        $this->assertCount(0, $violations);
    }

    #[DataProvider('getInvalidVatNumbers')]
    public function testInvalidVatNumbers(string $vatNumber): void
    {
        $violations = $this->validator->validate($vatNumber, new VatNumber());
        $this->assertCount(1, $violations);
    }

    public static function getInvalidVatNumbers(): \Generator
    {
        yield ['FR12345']; // Invalid format
        yield ['FR00306138900']; // Invalid checksum (Key 00 instead of 51)
        yield ['DE123']; // Invalid format
        yield ['DE123456789']; // Invalid checksum (Calculated 8, got 9)
        yield ['XX123456789']; // Invalid country code
    }

    public function testNullValue(): void
    {
        $violations = $this->validator->validate(null, new VatNumber());
        $this->assertCount(0, $violations);
    }

    public function testEmptyStringValue(): void
    {
        $violations = $this->validator->validate('', new VatNumber());
        $this->assertCount(0, $violations);
    }
}
