<?php

namespace App\Tests\Unit\Controller\Abstract;

use App\Controller\Abstract\AbstractSalePaymentTerminalController;
use App\Repository\ClubRepository;
use App\Service\PaymentTerminal\PaymentTerminalException;
use App\Service\PaymentTerminal\PaymentTerminalManager;
use App\Service\RequestService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AbstractSalePaymentTerminalControllerTest extends TestCase {
  private function controller(): AbstractSalePaymentTerminalController {
    $requestService = new RequestService(
      $this->createStub(ClubRepository::class),
      $this->createStub(TokenStorageInterface::class),
      new RequestStack(),
    );
    $terminalManager = new PaymentTerminalManager([]);

    return new class($requestService, $terminalManager) extends AbstractSalePaymentTerminalController {
      public function call(callable $fn): mixed {
        return $this->callTerminal($fn);
      }
    };
  }

  public function testCallTerminalReturnsCallableResult(): void {
    $result = $this->controller()->call(fn() => 'ok');

    $this->assertSame('ok', $result);
  }

  public function testCallTerminalMapsInvalidArgumentExceptionTo422(): void {
    $controller = $this->controller();

    try {
      $controller->call(function () {
        throw new \InvalidArgumentException('Missing credential field');
      });
      $this->fail('Expected HttpException to be thrown.');
    }
    catch (HttpException $e) {
      $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getStatusCode());
      $this->assertSame('Missing credential field', $e->getMessage());
    }
  }

  public function testCallTerminalMapsPaymentTerminalExceptionTo502(): void {
    $controller = $this->controller();

    try {
      $controller->call(function () {
        throw new PaymentTerminalException('Terminal unreachable');
      });
      $this->fail('Expected HttpException to be thrown.');
    }
    catch (HttpException $e) {
      $this->assertSame(Response::HTTP_BAD_GATEWAY, $e->getStatusCode());
      $this->assertSame('Terminal unreachable', $e->getMessage());
    }
  }
}
