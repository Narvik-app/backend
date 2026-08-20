<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817120000 extends AbstractMigration {
  public function getDescription(): string {
    return 'Introduce modular member controls (member_control_type / member_control); migrate control activity data then drop it';
  }

  public function up(Schema $schema): void {
    // 1. Schema
    $this->addSql('CREATE SEQUENCE member_control_type_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
    $this->addSql('CREATE TABLE member_control_type (id INT NOT NULL, uuid UUID NOT NULL, club_id INT NOT NULL, activity_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, warning_days INT DEFAULT NULL, alert_days INT DEFAULT NULL, display_on_presence_card BOOLEAN DEFAULT true NOT NULL, weight INT DEFAULT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_B839714D17F50A6 ON member_control_type (uuid)');
    $this->addSql('CREATE INDEX IDX_B83971461190A32 ON member_control_type (club_id)');
    $this->addSql('CREATE INDEX IDX_B83971481C06096 ON member_control_type (activity_id)');
    $this->addSql('ALTER TABLE member_control_type ADD CONSTRAINT FK_MEMBER_CONTROL_TYPE_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('ALTER TABLE member_control_type ADD CONSTRAINT FK_MEMBER_CONTROL_TYPE_ACTIVITY FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

    $this->addSql('CREATE SEQUENCE member_control_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
    $this->addSql('CREATE TABLE member_control (id INT NOT NULL, uuid UUID NOT NULL, club_id INT NOT NULL, member_id INT NOT NULL, type_id INT NOT NULL, date DATE DEFAULT NULL, alert_disabled BOOLEAN DEFAULT false NOT NULL, comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_522F9BDFD17F50A6 ON member_control (uuid)');
    $this->addSql('CREATE INDEX IDX_522F9BDF61190A32 ON member_control (club_id)');
    $this->addSql('CREATE INDEX IDX_522F9BDF7597D3FE ON member_control (member_id)');
    $this->addSql('CREATE INDEX IDX_522F9BDFC54C8C93 ON member_control (type_id)');
    $this->addSql('CREATE UNIQUE INDEX member_control_member_type_unique ON member_control (member_id, type_id)');
    $this->addSql('ALTER TABLE member_control ADD CONSTRAINT FK_MEMBER_CONTROL_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('ALTER TABLE member_control ADD CONSTRAINT FK_MEMBER_CONTROL_MEMBER FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('ALTER TABLE member_control ADD CONSTRAINT FK_MEMBER_CONTROL_TYPE FOREIGN KEY (type_id) REFERENCES member_control_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

    // 2. One "Contrôle" type per club that had a control activity configured.
    //    Delays: 335/365 days (~11 / ~12 months).
    $this->addSql(<<<'SQL'
        INSERT INTO member_control_type (id, uuid, club_id, activity_id, name, icon, warning_days, alert_days, display_on_presence_card, weight)
        SELECT nextval('member_control_type_id_seq'), gen_random_uuid(), cs.club_id, cs.control_activity_id, 'Contrôle', 'shield-check', 335, 365, true, 1
        FROM club_setting cs
        WHERE cs.control_activity_id IS NOT NULL
    SQL
    );

    // 3. One row per member: last presence date on that activity, plus the existing per-member mute flag.
    //    LEFT JOIN so a muted member with no matching presence still keeps their mute.
    $this->addSql(<<<'SQL'
        INSERT INTO member_control (id, uuid, club_id, member_id, type_id, date, alert_disabled, created_at, updated_at)
        SELECT nextval('member_control_id_seq'), gen_random_uuid(), m.club_id, m.id, t.id, p.last_date, m.control_activity_alert_disabled, NOW(), NOW()
        FROM member m
        JOIN member_control_type t ON t.club_id = m.club_id
        LEFT JOIN (
          SELECT mp.member_id, mpa.activity_id, MAX(mp.date) AS last_date
          FROM member_presence mp
          JOIN member_presence_activity mpa ON mpa.member_presence_id = mp.id
          GROUP BY mp.member_id, mpa.activity_id
        ) p ON p.member_id = m.id AND p.activity_id = t.activity_id
        WHERE p.last_date IS NOT NULL OR m.control_activity_alert_disabled = true
    SQL
    );

    // 4. Drop the old single-control feature.
    $this->addSql('ALTER TABLE club_setting DROP CONSTRAINT FK_923C1D1AC94ED4A');
    $this->addSql('DROP INDEX UNIQ_923C1D1A7E82CE84');
    $this->addSql('ALTER TABLE club_setting DROP control_activity_id');
    $this->addSql('ALTER TABLE member DROP control_activity_alert_disabled');
  }

  public function down(Schema $schema): void {
    // Restore the old columns
    $this->addSql('ALTER TABLE club_setting ADD control_activity_id INT DEFAULT NULL');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_923C1D1A7E82CE84 ON club_setting (control_activity_id)');
    $this->addSql('ALTER TABLE club_setting ADD CONSTRAINT FK_923C1D1AC94ED4A FOREIGN KEY (control_activity_id) REFERENCES activity (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('ALTER TABLE member ADD control_activity_alert_disabled BOOLEAN DEFAULT false NOT NULL');

    // Restore data from the lowest-weight automatic type per club
    $this->addSql(<<<'SQL'
        UPDATE club_setting cs
        SET control_activity_id = t.activity_id
        FROM (
          SELECT DISTINCT ON (club_id) club_id, activity_id
          FROM member_control_type
          WHERE activity_id IS NOT NULL
          ORDER BY club_id, weight ASC NULLS LAST
        ) t
        WHERE t.club_id = cs.club_id
    SQL
    );
    $this->addSql(<<<'SQL'
        UPDATE member m
        SET control_activity_alert_disabled = true
        FROM member_control mc
        JOIN member_control_type t ON t.id = mc.type_id AND t.activity_id IS NOT NULL
        WHERE mc.member_id = m.id AND mc.alert_disabled = true
    SQL
    );

    $this->addSql('ALTER TABLE member_control DROP CONSTRAINT FK_MEMBER_CONTROL_MEMBER');
    $this->addSql('ALTER TABLE member_control DROP CONSTRAINT FK_MEMBER_CONTROL_TYPE');
    $this->addSql('DROP TABLE member_control');
    $this->addSql('DROP SEQUENCE member_control_id_seq CASCADE');

    $this->addSql('ALTER TABLE member_control_type DROP CONSTRAINT FK_MEMBER_CONTROL_TYPE_CLUB');
    $this->addSql('ALTER TABLE member_control_type DROP CONSTRAINT FK_MEMBER_CONTROL_TYPE_ACTIVITY');
    $this->addSql('DROP TABLE member_control_type');
    $this->addSql('DROP SEQUENCE member_control_type_id_seq CASCADE');
  }
}
