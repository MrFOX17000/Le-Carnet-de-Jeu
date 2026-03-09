<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306113309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE entry (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(255) NOT NULL, label VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, session_id INTEGER NOT NULL, group_id INTEGER NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_2B219D70613FECDF FOREIGN KEY (session_id) REFERENCES session (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2B219D70FE54D947 FOREIGN KEY (group_id) REFERENCES game_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2B219D70B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2B219D70613FECDF ON entry (session_id)');
        $this->addSql('CREATE INDEX IDX_2B219D70FE54D947 ON entry (group_id)');
        $this->addSql('CREATE INDEX IDX_2B219D70B03A8386 ON entry (created_by_id)');
        $this->addSql('CREATE TABLE entry_score (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, participant_name VARCHAR(180) NOT NULL, score DOUBLE PRECISION NOT NULL, entry_id INTEGER NOT NULL, CONSTRAINT FK_B68354F7BA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B68354F7BA364942 ON entry_score (entry_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE entry');
        $this->addSql('DROP TABLE entry_score');
    }
}
