<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250216090408 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add postgresql unaccent extension';
  }

  public function up(Schema $schema): void {
    $this->addSql('CREATE EXTENSION IF NOT EXISTS unaccent;');
  }

  public function down(Schema $schema): void {
    $this->addSql('DROP EXTENSION IF EXISTS unaccent;');
  }
}
