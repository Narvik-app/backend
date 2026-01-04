<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260103211450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Improve permissions';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE member_permission_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE member_permission (permission VARCHAR(255) NOT NULL, id INT NOT NULL, uuid UUID NOT NULL, member_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B078C43D17F50A6 ON member_permission (uuid)');
        $this->addSql('CREATE INDEX IDX_B078C437597D3FE ON member_permission (member_id)');
        $this->addSql('CREATE UNIQUE INDEX member_permission_unique ON member_permission (member_id, permission)');
        $this->addSql('ALTER TABLE member_permission ADD CONSTRAINT FK_B078C437597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE member_permission_id_seq CASCADE');
        $this->addSql('ALTER TABLE member_permission DROP CONSTRAINT FK_B078C437597D3FE');
        $this->addSql('DROP TABLE member_permission');
    }
}
