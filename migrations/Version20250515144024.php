<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250515144024 extends AbstractMigration {
  public function getDescription(): string {
    return 'Presence registering is now an optional module';
  }

  public function up(Schema $schema): void {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            ALTER TABLE club ADD presences_enabled BOOLEAN DEFAULT false NOT NULL
        SQL
    );
  }

  public function down(Schema $schema): void {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            ALTER TABLE club DROP presences_enabled
        SQL
    );
  }
}
