<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250507093129 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add club activity type setting';
  }

  public function up(Schema $schema): void {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            ALTER TABLE club_setting ADD activity VARCHAR(255) DEFAULT 'GENERIC' NOT NULL
        SQL
    );
  }

  public function down(Schema $schema): void {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            ALTER TABLE club_setting DROP activity
        SQL
    );
  }
}
