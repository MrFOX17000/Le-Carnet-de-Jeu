<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306131500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_id columns to EntryScore and homeUser/awayUser columns to EntryMatch';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry_score ADD COLUMN user_id INTEGER DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_B68354F7A76ED395 ON entry_score (user_id)');
        $this->addSql('CREATE TABLE entry_match (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, entry_id INTEGER NOT NULL, home_name VARCHAR(180) NOT NULL, away_name VARCHAR(180) NOT NULL, home_score INTEGER NOT NULL, away_score INTEGER NOT NULL, home_user_id INTEGER DEFAULT NULL, away_user_id INTEGER DEFAULT NULL, CONSTRAINT FK_FE41A6A3BA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FE41A6A3124E18CD FOREIGN KEY (home_user_id) REFERENCES user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FE41A6A3CB1A5639 FOREIGN KEY (away_user_id) REFERENCES user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FE41A6A3BA364942 ON entry_match (entry_id)');
        $this->addSql('CREATE INDEX IDX_FE41A6A3124E18CD ON entry_match (home_user_id)');
        $this->addSql('CREATE INDEX IDX_FE41A6A3CB1A5639 ON entry_match (away_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_B68354F7A76ED395 ON entry_score');
        $this->addSql('ALTER TABLE entry_score DROP COLUMN user_id');
        $this->addSql('DROP TABLE entry_match');
    }
}
