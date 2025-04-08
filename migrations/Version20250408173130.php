<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250408173130 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add missing cascade deletion on sale purchased item';
  }

  public function up(Schema $schema): void {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE sale_purchased_item DROP CONSTRAINT FK_5F0E80A6126F525E');
    $this->addSql('ALTER TABLE sale_purchased_item ADD CONSTRAINT FK_5F0E80A6126F525E FOREIGN KEY (item_id) REFERENCES inventory_item (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
  }

  public function down(Schema $schema): void {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE sale_purchased_item DROP CONSTRAINT fk_5f0e80a6126f525e');
    $this->addSql('ALTER TABLE sale_purchased_item ADD CONSTRAINT fk_5f0e80a6126f525e FOREIGN KEY (item_id) REFERENCES inventory_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
  }
}
