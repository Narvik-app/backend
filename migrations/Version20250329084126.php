<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250329084126 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add visibility field on Activity';
  }

  public function up(Schema $schema): void {
    $this->addSql('ALTER TABLE activity ADD visibility VARCHAR(255) DEFAULT NULL');
  }

  public function down(Schema $schema): void {
    $this->addSql('ALTER TABLE activity DROP visibility');
  }
}
