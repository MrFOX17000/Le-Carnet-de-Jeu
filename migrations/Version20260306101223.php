<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306101223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invite (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, token VARCHAR(64) NOT NULL, role VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, group_id INTEGER NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_C7E210D7FE54D947 FOREIGN KEY (group_id) REFERENCES game_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C7E210D7B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C7E210D7FE54D947 ON invite (group_id)');
        $this->addSql('CREATE INDEX IDX_C7E210D7B03A8386 ON invite (created_by_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_invite_token ON invite (token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE invite');
    }
}
