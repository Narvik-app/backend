<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827163229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite (club_id, date) indexes on sale (created_at), member_presence, external_presence and loan (start_date) to speed up the metrics/stats and history-pagination queries that filter by club + date range.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_sale_club_created_at ON sale (club_id, created_at)');
        $this->addSql('CREATE INDEX idx_external_presence_club_date ON external_presence (club_id, date)');
        $this->addSql('CREATE INDEX idx_loan_club_start_date ON loan (club_id, start_date)');
        $this->addSql('CREATE INDEX idx_member_presence_club_date ON member_presence (club_id, date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_sale_club_created_at');
        $this->addSql('DROP INDEX idx_external_presence_club_date');
        $this->addSql('DROP INDEX idx_loan_club_start_date');
        $this->addSql('DROP INDEX idx_member_presence_club_date');
    }
}
