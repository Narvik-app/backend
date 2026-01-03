<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260103144419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member_permission DROP CONSTRAINT fk_b078c4375775e1a');
        $this->addSql('DROP INDEX member_permission_unique');
        $this->addSql('DROP INDEX idx_b078c4375775e1a');
        $this->addSql('ALTER TABLE member_permission RENAME COLUMN user_member_id TO member_id');
        $this->addSql('ALTER TABLE member_permission ADD CONSTRAINT FK_B078C437597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_B078C437597D3FE ON member_permission (member_id)');
        $this->addSql('CREATE UNIQUE INDEX member_permission_unique ON member_permission (member_id, permission)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member_permission DROP CONSTRAINT FK_B078C437597D3FE');
        $this->addSql('DROP INDEX IDX_B078C437597D3FE');
        $this->addSql('DROP INDEX member_permission_unique');
        $this->addSql('ALTER TABLE member_permission RENAME COLUMN member_id TO user_member_id');
        $this->addSql('ALTER TABLE member_permission ADD CONSTRAINT fk_b078c4375775e1a FOREIGN KEY (user_member_id) REFERENCES user_member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_b078c4375775e1a ON member_permission (user_member_id)');
        $this->addSql('CREATE UNIQUE INDEX member_permission_unique ON member_permission (user_member_id, permission)');
    }
}
