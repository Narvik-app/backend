<?php

namespace App\Tests\functional\DQL;

use App\Tests\AbstractTestCase;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;

class DayOfWeekTest extends AbstractTestCase
{
  public function testDayOfWeek(): void
  {
    $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    $query = $entityManager->createQuery("SELECT DAYOFWEEK(c.createdAt) FROM App\Entity\Club c");
    $result = $query->getResult(AbstractQuery::HYDRATE_SCALAR);
    $this->assertIsNumeric($result[0][1]);
  }
}
