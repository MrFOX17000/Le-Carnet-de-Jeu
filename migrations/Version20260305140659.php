<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260305140659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__group_member AS SELECT id, role, joined_at, group_id, user_id FROM group_member');
        $this->addSql('DROP TABLE group_member');
        $this->addSql('CREATE TABLE group_member (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, role VARCHAR(255) NOT NULL, joined_at DATETIME NOT NULL, group_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_A36222A8FE54D947 FOREIGN KEY (group_id) REFERENCES game_group (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A36222A8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO group_member (id, role, joined_at, group_id, user_id) SELECT id, role, joined_at, group_id, user_id FROM __temp__group_member');
        $this->addSql('DROP TABLE __temp__group_member');
        $this->addSql('CREATE UNIQUE INDEX uniq_group_member_group_user ON group_member (group_id, user_id)');
        $this->addSql('CREATE INDEX IDX_A36222A8FE54D947 ON group_member (group_id)');
        $this->addSql('CREATE INDEX IDX_A36222A8A76ED395 ON group_member (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__group_member AS SELECT id, role, joined_at, group_id, user_id FROM group_member');
        $this->addSql('DROP TABLE group_member');
        $this->addSql('CREATE TABLE group_member (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, role VARCHAR(20) NOT NULL, joined_at DATETIME NOT NULL, group_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_A36222A8FE54D947 FOREIGN KEY (group_id) REFERENCES "game_group" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A36222A8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO group_member (id, role, joined_at, group_id, user_id) SELECT id, role, joined_at, group_id, user_id FROM __temp__group_member');
        $this->addSql('DROP TABLE __temp__group_member');
        $this->addSql('CREATE INDEX IDX_A36222A8FE54D947 ON group_member (group_id)');
        $this->addSql('CREATE INDEX IDX_A36222A8A76ED395 ON group_member (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_group_member_group_user ON group_member (group_id, user_id)');
    }
}
