<?php

namespace App\Tests\Unit\Service\PaymentTerminal\Credentials;

use App\Service\PaymentTerminal\Credentials\SumUpCredentials;
use PHPUnit\Framework\TestCase;

class SumUpCredentialsTest extends TestCase {
  public function testFromArrayWithAllFields(): void {
    $credentials = SumUpCredentials::fromArray([
      'apiKey' => 'sk_test_123',
      'merchantCode' => 'MC123',
      'readerId' => 'reader-1',
    ]);

    $this->assertSame('sk_test_123', $credentials->apiKey);
    $this->assertSame('MC123', $credentials->merchantCode);
    $this->assertSame('reader-1', $credentials->readerId);
  }

  public function testFromArrayWithoutReaderIdLeavesItNull(): void {
    $credentials = SumUpCredentials::fromArray([
      'apiKey' => 'sk_test_123',
      'merchantCode' => 'MC123',
    ]);

    $this->assertNull($credentials->readerId);
  }

  public function testFromArrayWithEmptyReaderIdLeavesItNull(): void {
    $credentials = SumUpCredentials::fromArray([
      'apiKey' => 'sk_test_123',
      'merchantCode' => 'MC123',
      'readerId' => '',
    ]);

    $this->assertNull($credentials->readerId);
  }

  public function testFromArrayMissingApiKeyThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("'apiKey'");
    SumUpCredentials::fromArray(['merchantCode' => 'MC123']);
  }

  public function testFromArrayMissingMerchantCodeThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("'merchantCode'");
    SumUpCredentials::fromArray(['apiKey' => 'sk_test_123']);
  }

  public function testAssertCompleteWithReaderIdDoesNotThrow(): void {
    $credentials = SumUpCredentials::fromArray([
      'apiKey' => 'sk_test_123',
      'merchantCode' => 'MC123',
      'readerId' => 'reader-1',
    ]);

    $credentials->assertComplete();
    $this->addToAssertionCount(1);
  }

  public function testAssertCompleteWithoutReaderIdThrows(): void {
    $credentials = SumUpCredentials::fromArray([
      'apiKey' => 'sk_test_123',
      'merchantCode' => 'MC123',
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("'readerId'");
    $credentials->assertComplete();
  }

  public function testJsonSerializeOmitsNullReaderId(): void {
    $credentials = SumUpCredentials::fromArray([
      'apiKey' => 'sk_test_123',
      'merchantCode' => 'MC123',
    ]);

    $this->assertSame([
      'apiKey' => 'sk_test_123',
      'merchantCode' => 'MC123',
    ], $credentials->jsonSerialize());
  }

  public function testJsonSerializeIncludesReaderIdWhenPresent(): void {
    $credentials = SumUpCredentials::fromArray([
      'apiKey' => 'sk_test_123',
      'merchantCode' => 'MC123',
      'readerId' => 'reader-1',
    ]);

    $this->assertSame([
      'apiKey' => 'sk_test_123',
      'merchantCode' => 'MC123',
      'readerId' => 'reader-1',
    ], $credentials->jsonSerialize());
  }
}
