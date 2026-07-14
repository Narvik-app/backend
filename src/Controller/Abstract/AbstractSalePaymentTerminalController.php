<?php

namespace App\Controller\Abstract;

use App\Service\PaymentTerminal\PaymentTerminalException;
use App\Service\PaymentTerminal\PaymentTerminalManager;
use App\Service\RequestService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class AbstractSalePaymentTerminalController extends AbstractClubDependentController {
  public function __construct(
    RequestService $requestService,
    protected readonly PaymentTerminalManager $terminalManager,
  ) {
    parent::__construct($requestService);
  }

  /**
   * Runs $fn against the payment terminal provider, translating its errors
   * into the appropriate HTTP exception.
   *
   * @template T
   *
   * @param callable(): T $fn
   *
   * @return T
   */
  protected function callTerminal(callable $fn): mixed {
    try {
      return $fn();
    }
    catch (\InvalidArgumentException $e) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage(), $e);
    }
    catch (PaymentTerminalException $e) {
      throw new HttpException(Response::HTTP_BAD_GATEWAY, $e->getMessage(), $e);
    }
  }
}
