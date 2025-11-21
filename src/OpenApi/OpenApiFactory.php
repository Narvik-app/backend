<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\OpenApi;

class OpenApiFactory implements OpenApiFactoryInterface {
  public function __construct(
    private readonly OpenApiFactoryInterface $decorated,
  ) {
  }

  public function __invoke(array $context = []): OpenApi {
    $openApi = $this->decorated->__invoke($context);
    $openApi = $this->updateInfo($openApi);
    return $openApi;
  }

  private function updateInfo(OpenApi $openApi): OpenApi {
    $info = $openApi->getInfo()
      ->withVersion(\Composer\InstalledVersions::getRootPackage()['pretty_version']);
    return $openApi->withInfo($info);
  }
}
