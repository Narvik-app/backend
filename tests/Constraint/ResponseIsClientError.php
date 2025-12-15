<?php

namespace App\Tests\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\HttpFoundation\Response;

final class ResponseIsClientError extends Constraint {
  /**
   * @param bool $verbose If true, the entire response is printed on failure. If false, the response body is omitted.
   */
  public function __construct(private readonly bool $verbose = true) {
  }

  public function toString(): string {
    return 'is client error';
  }

  /**
   * @param Response $response
   */
  #[\Override]
  protected function matches($response): bool {
    return $response->isClientError();
  }

  /**
   * @param Response $response
   */
  #[\Override]
  protected function failureDescription($response): string {
    return 'the Response ' . $this->toString();
  }

  /**
   * @param Response $response
   */
  #[\Override]
  protected function additionalFailureDescription($response): string {
    return $this->verbose ? (string)$response : explode("\r\n\r\n", (string)$response)[0];
  }
}
