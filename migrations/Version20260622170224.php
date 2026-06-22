<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622170224 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Improve inventory tracking';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE inventory_item_history ADD quantity INT DEFAULT NULL');
    $this->addSql('ALTER TABLE inventory_item_history ADD sale_id INT DEFAULT NULL');
    $this->addSql('CREATE INDEX IDX_27487B024A7E4868 ON inventory_item_history (sale_id)');
    $this->addSql('ALTER TABLE inventory_item_history ADD CONSTRAINT FK_27487B024A7E4868 FOREIGN KEY (sale_id) REFERENCES sale (id) ON DELETE SET NULL NOT DEFERRABLE');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE inventory_item_history DROP quantity');
    $this->addSql('ALTER TABLE inventory_item_history DROP sale_id');

    $this->addSql('ALTER TABLE inventory_item_history DROP CONSTRAINT FK_27487B024A7E4868');
    $this->addSql('DROP INDEX IDX_27487B024A7E4868');
  }
}
