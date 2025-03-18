<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250318131043 extends AbstractMigration {
  public function getDescription(): string {
    return 'GDPR: Remove not needed fields';
  }

  public function up(Schema $schema): void {
    $this->addSql('ALTER TABLE member DROP handisport');
  }

  public function down(Schema $schema): void {
    $this->addSql('ALTER TABLE member ADD handisport BOOLEAN NOT NULL');
  }
}
