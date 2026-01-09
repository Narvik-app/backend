<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108222233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add permissions support';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE member_permission_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE permission_template_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE member_permission (permission VARCHAR(255) NOT NULL, id INT NOT NULL, uuid UUID NOT NULL, member_id INT DEFAULT NULL, template_id INT DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B078C43D17F50A6 ON member_permission (uuid)');
        $this->addSql('CREATE INDEX IDX_B078C437597D3FE ON member_permission (member_id)');
        $this->addSql('CREATE INDEX IDX_B078C435DA0FB8 ON member_permission (template_id)');
        $this->addSql('CREATE INDEX IDX_B078C4361190A32 ON member_permission (club_id)');
        $this->addSql('CREATE UNIQUE INDEX member_permission_unique ON member_permission (permission, member_id)');
        $this->addSql('CREATE UNIQUE INDEX template_permission_unique ON member_permission (permission, template_id)');
        $this->addSql('CREATE TABLE permission_template (name VARCHAR(255) NOT NULL, id INT NOT NULL, uuid UUID NOT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6258CA35D17F50A6 ON permission_template (uuid)');
        $this->addSql('CREATE INDEX IDX_6258CA3561190A32 ON permission_template (club_id)');
        $this->addSql('CREATE UNIQUE INDEX permission_template_unique ON permission_template (name, club_id)');
        $this->addSql('ALTER TABLE member_permission ADD CONSTRAINT FK_B078C437597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE member_permission ADD CONSTRAINT FK_B078C435DA0FB8 FOREIGN KEY (template_id) REFERENCES permission_template (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE member_permission ADD CONSTRAINT FK_B078C4361190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE permission_template ADD CONSTRAINT FK_6258CA3561190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE member ADD permission_template_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA781044042B FOREIGN KEY (permission_template_id) REFERENCES permission_template (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_70E4FA781044042B ON member (permission_template_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE member_permission_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE permission_template_id_seq CASCADE');
        $this->addSql('ALTER TABLE member_permission DROP CONSTRAINT FK_B078C437597D3FE');
        $this->addSql('ALTER TABLE member_permission DROP CONSTRAINT FK_B078C435DA0FB8');
        $this->addSql('ALTER TABLE member_permission DROP CONSTRAINT FK_B078C4361190A32');
        $this->addSql('ALTER TABLE permission_template DROP CONSTRAINT FK_6258CA3561190A32');
        $this->addSql('DROP TABLE member_permission');
        $this->addSql('DROP TABLE permission_template');
        $this->addSql('ALTER TABLE member DROP CONSTRAINT FK_70E4FA781044042B');
        $this->addSql('DROP INDEX IDX_70E4FA781044042B');
        $this->addSql('ALTER TABLE member DROP permission_template_id');
    }
}
