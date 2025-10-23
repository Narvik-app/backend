<?php

namespace App\Tests\functional\DQL;

use App\Tests\AbstractTestCase;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;

class PercentileTest extends AbstractTestCase
{
  public function testPercentile()
  {
    $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    $query = $entityManager->createQuery("SELECT PERCENTILE(0.5, c.id) FROM App\Entity\Club c");
    $result = $query->getResult(AbstractQuery::HYDRATE_SCALAR);
    $this->assertIsNumeric($result[0][1]);
  }
}
