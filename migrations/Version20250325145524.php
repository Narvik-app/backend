<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250325145524 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add legals acceptation tracking date';
  }

  public function up(Schema $schema): void {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE "user" ADD legals_accepted TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
  }

  public function down(Schema $schema): void {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE "user" DROP legals_accepted');
  }
}
