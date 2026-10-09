<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251115150005 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add Time and Travel Declaration plugin';
  }

  public function up(Schema $schema): void
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql('CREATE SEQUENCE member_vehicle_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
    $this->addSql('CREATE SEQUENCE time_and_travel_declaration_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
    $this->addSql('CREATE TABLE member_vehicle (brand VARCHAR(255) NOT NULL, model VARCHAR(255) DEFAULT NULL, license_plate VARCHAR(20) NOT NULL, engine_type VARCHAR(255) NOT NULL, fiscal_power INT NOT NULL, fiscal_coefficient NUMERIC(8, 4) NOT NULL, is_enabled BOOLEAN NOT NULL, id INT NOT NULL, uuid UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, member_id INT DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_A4745312D17F50A6 ON member_vehicle (uuid)');
    $this->addSql('CREATE INDEX IDX_A47453127597D3FE ON member_vehicle (member_id)');
    $this->addSql('CREATE INDEX IDX_A474531261190A32 ON member_vehicle (club_id)');
    $this->addSql('CREATE TABLE time_and_travel_declaration (date DATE NOT NULL, departure_location VARCHAR(255) NOT NULL, arrival_location VARCHAR(255) NOT NULL, kilometers INT NOT NULL, hours NUMERIC(4, 2) NOT NULL, description VARCHAR(60) NOT NULL, is_roundtrip BOOLEAN NOT NULL, id INT NOT NULL, uuid UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, member_id INT DEFAULT NULL, member_vehicle_id INT DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_6289DC74D17F50A6 ON time_and_travel_declaration (uuid)');
    $this->addSql('CREATE INDEX IDX_6289DC747597D3FE ON time_and_travel_declaration (member_id)');
    $this->addSql('CREATE INDEX IDX_6289DC74BF1D0938 ON time_and_travel_declaration (member_vehicle_id)');
    $this->addSql('CREATE INDEX IDX_6289DC7461190A32 ON time_and_travel_declaration (club_id)');
    $this->addSql('ALTER TABLE member_vehicle ADD CONSTRAINT FK_A47453127597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
    $this->addSql('ALTER TABLE member_vehicle ADD CONSTRAINT FK_A474531261190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
    $this->addSql('ALTER TABLE time_and_travel_declaration ADD CONSTRAINT FK_6289DC747597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
    $this->addSql('ALTER TABLE time_and_travel_declaration ADD CONSTRAINT FK_6289DC74BF1D0938 FOREIGN KEY (member_vehicle_id) REFERENCES member_vehicle (id) ON DELETE SET NULL NOT DEFERRABLE');
    $this->addSql('ALTER TABLE time_and_travel_declaration ADD CONSTRAINT FK_6289DC7461190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
    $this->addSql('ALTER TABLE club_setting ADD smic_hourly_rate NUMERIC(6, 2) DEFAULT NULL');
    $this->addSql('ALTER TABLE club_setting ADD supervisor_can_edit_any_ttdeclaration BOOLEAN DEFAULT false NOT NULL');

  }

  public function down(Schema $schema): void
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql('DROP SEQUENCE member_vehicle_id_seq CASCADE');
    $this->addSql('DROP SEQUENCE time_and_travel_declaration_id_seq CASCADE');
    $this->addSql('ALTER TABLE member_vehicle DROP CONSTRAINT FK_A47453127597D3FE');
    $this->addSql('ALTER TABLE member_vehicle DROP CONSTRAINT FK_A474531261190A32');
    $this->addSql('ALTER TABLE time_and_travel_declaration DROP CONSTRAINT FK_6289DC747597D3FE');
    $this->addSql('ALTER TABLE time_and_travel_declaration DROP CONSTRAINT FK_6289DC74BF1D0938');
    $this->addSql('ALTER TABLE time_and_travel_declaration DROP CONSTRAINT FK_6289DC7461190A32');
    $this->addSql('DROP TABLE member_vehicle');
    $this->addSql('DROP TABLE time_and_travel_declaration');
    $this->addSql('ALTER TABLE club_setting DROP smic_hourly_rate');
    $this->addSql('ALTER TABLE club_setting DROP supervisor_can_edit_any_ttdeclaration');
  }
}
