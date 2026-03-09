<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306111747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL, group_id INTEGER NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_AC74095AFE54D947 FOREIGN KEY (group_id) REFERENCES game_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_AC74095AFE54D947 ON activity (group_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AB03A8386 ON activity (created_by_id)');
        $this->addSql('CREATE TABLE session (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, played_at DATETIME NOT NULL, created_at DATETIME NOT NULL, unlisted_token VARCHAR(64) DEFAULT NULL, activity_id INTEGER NOT NULL, group_id INTEGER NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_D044D5D481C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D044D5D4FE54D947 FOREIGN KEY (group_id) REFERENCES game_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D044D5D4B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D044D5D481C06096 ON session (activity_id)');
        $this->addSql('CREATE INDEX IDX_D044D5D4FE54D947 ON session (group_id)');
        $this->addSql('CREATE INDEX IDX_D044D5D4B03A8386 ON session (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE session');
    }
}
