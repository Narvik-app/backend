<?php

namespace App\Tests\functional\DQL;

use App\Entity\Club;
use App\Tests\AbstractTestCase;

class UnaccentStringTest extends AbstractTestCase
{
  public function testUnaccentString(): void
  {
    $entityManager = self::getContainer()->get(\Doctrine\ORM\EntityManager::class);

    $club = new Club();
    $club->setName('éàç');
    $entityManager->persist($club);
    $entityManager->flush();

    $query = $entityManager->createQuery("SELECT UNACCENT(c.name) FROM App\Entity\Club c WHERE c.id = :id");
    $query->setParameter('id', $club->getId());
    $result = $query->getSingleScalarResult();
    $this->assertEquals('eac', $result);
  }
}
