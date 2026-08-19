<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819100000 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add generic club_job background-job tracking table (replacing the itac/cerbere ClubSetting counters), and an index on member_presence(member_id, date)';
  }

  public function up(Schema $schema): void {
    // 1. Generic per-club background job tracker
    $this->addSql('CREATE SEQUENCE club_job_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
    $this->addSql('CREATE TABLE club_job (id INT NOT NULL, uuid UUID NOT NULL, club_id INT NOT NULL, key VARCHAR(255) NOT NULL, total INT NOT NULL, remaining INT NOT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_58BAC0E1D17F50A6 ON club_job (uuid)');
    $this->addSql('CREATE INDEX IDX_58BAC0E161190A32 ON club_job (club_id)');
    $this->addSql('CREATE UNIQUE INDEX club_job_club_key_unique ON club_job (club_id, key)');
    $this->addSql('ALTER TABLE club_job ADD CONSTRAINT FK_CLUB_JOB_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

    // 2. Backfill any club currently mid-import (best effort: original `total` isn't recoverable,
    //    so we use the current `remaining` as `total` too; `updated_at` falls back to the old
    //    itac_import_date/itac_secondary_import_date when present, else now).
    $this->addSql(<<<'SQL'
        INSERT INTO club_job (id, uuid, club_id, key, total, remaining, status, created_at, updated_at)
        SELECT nextval('club_job_id_seq'), gen_random_uuid(), cs.club_id, 'itac_import', cs.itac_import_remaining, cs.itac_import_remaining, 'in_progress', NOW(), COALESCE(cs.itac_import_date, NOW())
        FROM club_setting cs WHERE cs.itac_import_remaining > 0
    SQL
    );
    $this->addSql(<<<'SQL'
        INSERT INTO club_job (id, uuid, club_id, key, total, remaining, status, created_at, updated_at)
        SELECT nextval('club_job_id_seq'), gen_random_uuid(), cs.club_id, 'itac_secondary_import', cs.itac_secondary_import_remaining, cs.itac_secondary_import_remaining, 'in_progress', NOW(), COALESCE(cs.itac_secondary_import_date, NOW())
        FROM club_setting cs WHERE cs.itac_secondary_import_remaining > 0
    SQL
    );
    $this->addSql(<<<'SQL'
        INSERT INTO club_job (id, uuid, club_id, key, total, remaining, status, created_at, updated_at)
        SELECT nextval('club_job_id_seq'), gen_random_uuid(), cs.club_id, 'cerbere_import', cs.cerbere_import_remaining, cs.cerbere_import_remaining, 'in_progress', NOW(), NOW()
        FROM club_setting cs WHERE cs.cerbere_import_remaining > 0
    SQL
    );

    // 3. Drop the old one-off counters now that ClubJob replaces them
    $this->addSql('ALTER TABLE club_setting DROP itac_import_date');
    $this->addSql('ALTER TABLE club_setting DROP itac_import_remaining');
    $this->addSql('ALTER TABLE club_setting DROP itac_secondary_import_date');
    $this->addSql('ALTER TABLE club_setting DROP itac_secondary_import_remaining');
    $this->addSql('ALTER TABLE club_setting DROP cerbere_import_remaining');

    // 4. findLastOneByActivity() (member-control sync + presence-history modal) sorts
    //    member_presence by date per member with no supporting index today.
    $this->addSql('CREATE INDEX IDX_MEMBER_PRESENCE_MEMBER_DATE ON member_presence (member_id, date)');
  }

  public function down(Schema $schema): void {
    $this->addSql('DROP INDEX IDX_MEMBER_PRESENCE_MEMBER_DATE');

    $this->addSql('ALTER TABLE club_setting ADD itac_import_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    $this->addSql('ALTER TABLE club_setting ADD itac_import_remaining INT DEFAULT 0 NOT NULL');
    $this->addSql('ALTER TABLE club_setting ADD itac_secondary_import_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    $this->addSql('ALTER TABLE club_setting ADD itac_secondary_import_remaining INT DEFAULT 0 NOT NULL');
    $this->addSql('ALTER TABLE club_setting ADD cerbere_import_remaining INT DEFAULT 0 NOT NULL');

    $this->addSql(<<<'SQL'
        UPDATE club_setting cs SET itac_import_remaining = j.remaining, itac_import_date = j.updated_at
        FROM club_job j WHERE j.club_id = cs.club_id AND j.key = 'itac_import'
    SQL
    );
    $this->addSql(<<<'SQL'
        UPDATE club_setting cs SET itac_secondary_import_remaining = j.remaining, itac_secondary_import_date = j.updated_at
        FROM club_job j WHERE j.club_id = cs.club_id AND j.key = 'itac_secondary_import'
    SQL
    );
    $this->addSql(<<<'SQL'
        UPDATE club_setting cs SET cerbere_import_remaining = j.remaining
        FROM club_job j WHERE j.club_id = cs.club_id AND j.key = 'cerbere_import'
    SQL
    );

    $this->addSql('ALTER TABLE club_job DROP CONSTRAINT FK_CLUB_JOB_CLUB');
    $this->addSql('DROP TABLE club_job');
    $this->addSql('DROP SEQUENCE club_job_id_seq CASCADE');
  }
}
